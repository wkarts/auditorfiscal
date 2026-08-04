<?php

namespace App\Jobs;

use App\Models\FiscalDocument;
use App\Services\ApplicationLogger;
use App\Services\FiscalAuxiliaryDocumentGenerator;
use App\Services\UnsupportedAuxiliaryDocumentException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Throwable;

class GenerateFiscalAuxiliaryDocument implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 180;

    public int $tries = 1;

    public function __construct(public string $documentId) {}

    public function middleware(): array
    {
        return [(new WithoutOverlapping('fiscal-auxiliary-document:'.$this->documentId))->dontRelease()->expireAfter(240)];
    }

    public function handle(FiscalAuxiliaryDocumentGenerator $generator): void
    {
        $document = FiscalDocument::with('batch')->find($this->documentId);
        if (! $document || ! $document->batch) {
            return;
        }
        if ($document->auxiliary_document_storage_path || $document->danfe_storage_path) {
            $this->preserveImportedDocument($document);

            return;
        }

        try {
            $generated = $generator->generate($document);
            $normalized = $document->normalized ?? [];
            $normalized['auxiliary_document'] = [
                'type' => $generated['type'],
                'source' => $generated['source'],
                'renderer' => 'nfephp-org/sped-da',
                'sha256' => $generated['sha256'],
                'size' => $generated['size'],
            ];
            $document->update([
                'auxiliary_document_storage_path' => $generated['path'],
                'auxiliary_document_type' => $generated['type'],
                'auxiliary_document_source' => $generated['source'],
                'auxiliary_document_status' => 'available',
                'auxiliary_document_error' => null,
                'auxiliary_document_generated_at' => now(),
                'normalized' => $normalized,
            ]);
            ApplicationLogger::record('info', 'auxiliary-document', 'generated', 'Documento auxiliar gerado pelo NFePHP a partir do XML fiscal.', [
                'type' => $generated['type'],
                'renderer' => 'nfephp-org/sped-da',
                'size' => $generated['size'],
            ], $document->batch);
        } catch (UnsupportedAuxiliaryDocumentException $exception) {
            $document->update([
                'auxiliary_document_status' => 'not_supported',
                'auxiliary_document_error' => ['code' => 'AUXILIARY_DOCUMENT_NOT_SUPPORTED', 'message' => $exception->getMessage()],
            ]);
            ApplicationLogger::record('notice', 'auxiliary-document', 'not_supported', 'Documento auxiliar não gerado porque o modelo não possui renderizador configurado.', [
                'model' => $document->model,
                'code' => 'AUXILIARY_DOCUMENT_NOT_SUPPORTED',
            ], $document->batch);
        } catch (Throwable $exception) {
            $document->update([
                'auxiliary_document_status' => 'failed',
                'auxiliary_document_error' => [
                    'code' => 'AUXILIARY_DOCUMENT_GENERATION_FAILED',
                    'message' => 'Não foi possível gerar o documento auxiliar a partir do XML.',
                    'exception' => $exception::class,
                ],
            ]);
            ApplicationLogger::record('error', 'auxiliary-document', 'generation_failed', 'Falha ao gerar o documento auxiliar a partir do XML fiscal.', [
                'model' => $document->model,
                'exception' => $exception,
            ], $document->batch);
        }
    }

    private function preserveImportedDocument(FiscalDocument $document): void
    {
        if ($document->auxiliary_document_storage_path) {
            return;
        }

        $type = FiscalAuxiliaryDocumentGenerator::typeForModel($document->model) ?? 'PDF_AUXILIAR';
        $normalized = $document->normalized ?? [];
        $normalized['auxiliary_document'] = ['type' => $type, 'source' => 'imported_original'];
        $document->update([
            'auxiliary_document_storage_path' => $document->danfe_storage_path,
            'auxiliary_document_type' => $type,
            'auxiliary_document_source' => 'imported_original',
            'auxiliary_document_status' => 'available',
            'auxiliary_document_error' => null,
            'normalized' => $normalized,
        ]);
    }
}
