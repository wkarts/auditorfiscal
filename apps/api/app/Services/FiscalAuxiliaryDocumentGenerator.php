<?php

namespace App\Services;

use App\Models\FiscalDocument;
use DOMDocument;
use Illuminate\Support\Facades\Storage;
use NFePHP\DA\CTe\Dacte;
use NFePHP\DA\CTe\DacteOS;
use NFePHP\DA\MDFe\Damdfe;
use NFePHP\DA\NFe\Danfe;
use NFePHP\DA\NFe\Danfce;
use RuntimeException;

class FiscalAuxiliaryDocumentGenerator
{
    /** @var array<string, string> */
    private const TYPES_BY_MODEL = [
        '55' => 'DANFE',
        '65' => 'DANFCE',
        '57' => 'DACTE',
        '67' => 'DACTE_OS',
        '58' => 'DAMDFE',
    ];

    public static function typeForModel(?string $model): ?string
    {
        return self::TYPES_BY_MODEL[(string) $model] ?? null;
    }

    public function generate(FiscalDocument $document): array
    {
        $type = self::typeForModel($document->model);
        if (! $type) {
            throw new UnsupportedAuxiliaryDocumentException((string) $document->model);
        }

        $disk = Storage::disk(config('filesystems.default'));
        if (! $document->xml_storage_path || ! $disk->exists($document->xml_storage_path)) {
            throw new RuntimeException('O XML fiscal original não está disponível para gerar o documento auxiliar.');
        }

        $xml = $disk->get($document->xml_storage_path);
        $xmlModel = $this->modelFromXml($xml);
        if ($xmlModel !== (string) $document->model) {
            throw new RuntimeException('O modelo informado no registro não corresponde ao XML fiscal original.');
        }

        $pdf = $this->render($xml, $type);
        if (! str_starts_with($pdf, '%PDF-')) {
            throw new RuntimeException('O renderizador fiscal não retornou um PDF válido.');
        }

        $reference = preg_replace('/[^0-9A-Za-z_-]/', '', (string) ($document->access_key ?: $document->id));
        $path = sprintf('batches/%s/auxiliary-documents/%s-%s.pdf', $document->analysis_batch_id, $type, $reference);
        $disk->put($path, $pdf, ['ContentType' => 'application/pdf', 'visibility' => 'private']);

        return [
            'path' => $path,
            'type' => $type,
            'source' => 'nfephp_generated',
            'sha256' => hash('sha256', $pdf),
            'size' => strlen($pdf),
        ];
    }

    private function render(string $xml, string $type): string
    {
        $renderer = match ($type) {
            'DANFE' => new Danfe($xml),
            'DANFCE' => new Danfce($xml),
            'DACTE' => new Dacte($xml),
            'DACTE_OS' => new DacteOS($xml),
            'DAMDFE' => new Damdfe($xml),
            default => throw new UnsupportedAuxiliaryDocumentException($type),
        };

        $renderer->creditsIntegratorFooter('Documento auxiliar gerado pelo Auditor Fiscal a partir do XML autorizado.', false);

        return $renderer->render();
    }

    private function modelFromXml(string $xml): string
    {
        $previous = libxml_use_internal_errors(true);
        try {
            $dom = new DOMDocument();
            if (! $dom->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT)) {
                throw new RuntimeException('O XML fiscal original não pôde ser validado para a geração do documento auxiliar.');
            }
            $models = $dom->getElementsByTagName('mod');
            $model = trim((string) ($models->item(0)?->textContent ?? ''));
            if (! array_key_exists($model, self::TYPES_BY_MODEL)) {
                throw new UnsupportedAuxiliaryDocumentException($model);
            }

            return $model;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }
}
