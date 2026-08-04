<?php

namespace App\Jobs;

use App\Models\AnalysisBatch;
use App\Models\Finding;
use App\Models\FiscalDocument;
use App\Models\FiscalItem;
use App\Models\ReportArtifact;
use App\Services\AnalysisFailure;
use App\Services\AnalysisResultDeduplicator;
use App\Services\AnalysisResultValidator;
use App\Services\ApplicationLogger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ProcessAnalysisBatch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 7200;

    public int $tries = 3;

    public function __construct(public string $batchId) {}

    public function backoff(): array
    {
        return [10, 30];
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping('analysis-batch:'.$this->batchId))
            ->dontRelease()
            ->expireAfter($this->timeout + 300)];
    }

    public function handle(AnalysisResultDeduplicator $deduplicator, AnalysisResultValidator $validator): void
    {
        $batch = AnalysisBatch::with('company', 'catalogVersion', 'sourceFiles')->findOrFail($this->batchId);
        if ($batch->cancellationRequested()) {
            $this->finalizeCancellation($batch, 'Cancelamento aplicado antes do início desta tentativa.');

            return;
        }
        if (in_array($batch->status, ['completed', 'failed', 'cancelled', 'superseded'], true)) {
            ApplicationLogger::record('notice', 'audit-worker', 'terminal_job_skipped', 'Job duplicado ignorado porque o lote já está em estado terminal.', [
                'status' => $batch->status,
            ], $batch);

            return;
        }

        $attempt = max(1, $this->attempts());
        $batch->update([
            'status' => 'processing',
            'started_at' => $batch->started_at ?? now(),
            'last_attempt_at' => now(),
            'attempt_count' => max((int) $batch->attempt_count, $attempt),
            'progress' => 0.01,
            'error' => null,
            'finished_at' => null,
        ]);
        ApplicationLogger::record('info', 'audit-worker', 'processing_started', 'Tentativa de processamento iniciada.', [
            'queue_job_id' => $this->job?->getJobId(),
            'source_file_count' => $batch->sourceFiles->count(),
        ], $batch, attempt: $attempt);

        try {
            $payload = $this->payload($batch);
            $requestStartedAt = microtime(true);
            ApplicationLogger::record('info', 'fiscal-engine', 'request_started', 'Solicitação enviada ao motor fiscal.', [
                'endpoint' => '/v1/audits/run',
                'timeout_seconds' => 7200,
            ], $batch, attempt: $attempt);
            $response = Http::timeout(7200)
                ->retry(2, 1000, throw: false)
                ->withToken(config('services.fiscal_engine.token'))
                ->post(config('services.fiscal_engine.url').'/v1/audits/run', $payload);
            $durationMs = (int) round((microtime(true) - $requestStartedAt) * 1000);
            ApplicationLogger::record(
                $response->successful() || $this->isEngineCancellation($response->status(), $response->json()) ? 'info' : 'error',
                'fiscal-engine',
                'response_received',
                'Resposta recebida do motor fiscal.',
                ['http_status' => $response->status(), 'duration_ms' => $durationMs],
                $batch,
                attempt: $attempt,
            );
            if ($this->isEngineCancellation($response->status(), $response->json())) {
                $this->finalizeCancellation($batch, 'O motor fiscal confirmou o encerramento no ponto seguro.');

                return;
            }
            $response->throw();
            $result = $response->json();
            if (! is_array($result) || ! isset($result['documents'], $result['findings'])) {
                throw new RuntimeException('Resposta inválida do motor fiscal: documents/findings ausentes.');
            }
            if ($this->cancellationWasRequested($batch)) {
                $this->finalizeCancellation($batch, 'Cancelamento aplicado antes da persistência dos resultados.');

                return;
            }

            $engineDocumentCount = count($result['documents']);
            $normalized = $deduplicator->normalize($result);
            $result = $normalized['result'];
            $validator->validate($result, (string) $batch->company->tax_id);
            $receivedDocumentCount = max($engineDocumentCount, (int) ($result['summary']['received_document_count'] ?? 0));
            $duplicateCount = max(count($normalized['duplicates']), (int) ($result['summary']['duplicate_occurrence_count'] ?? 0));
            ApplicationLogger::record('info', 'audit-worker', 'persistence_started', 'Persistência dos resultados iniciada.', [
                'received_document_count' => $receivedDocumentCount,
                'document_count' => count($result['documents']),
                'duplicate_occurrence_count' => $duplicateCount,
                'finding_count' => count($result['findings']),
                'report_count' => count($result['reports'] ?? []),
            ], $batch, attempt: $attempt);
            if (! $this->persistResults($batch, $result)) {
                $cancelled = AnalysisBatch::find($batch->id);
                if ($cancelled?->status === 'cancelled') {
                    $this->recordCancellation($cancelled, 'Cancelamento aplicado durante a proteção transacional dos resultados.');
                }

                return;
            }
            $batch->refresh();
            if ($duplicateCount > 0) {
                ApplicationLogger::record('warning', 'audit-worker', 'duplicate_documents_consolidated', 'Documentos repetidos foram consolidados sem interromper a auditoria.', [
                    'duplicate_occurrence_count' => $duplicateCount,
                    'canonical_document_count' => count($result['documents']),
                    'strategy' => 'first_occurrence_is_canonical',
                ], $batch, attempt: $attempt);
            }
            ApplicationLogger::record('info', 'audit-worker', 'completed', 'Auditoria concluída com sucesso.', [
                'document_count' => $batch->document_count,
                'item_count' => $batch->item_count,
                'finding_count' => $batch->finding_count,
            ], $batch, attempt: $attempt);
        } catch (Throwable $exception) {
            if ($this->cancellationWasRequested($batch)) {
                $this->finalizeCancellation($batch, 'Processamento encerrado após solicitação de cancelamento.');

                return;
            }
            $failure = AnalysisFailure::from($exception, $attempt);
            $willRetry = $attempt < $this->tries && AnalysisFailure::isRetryable($exception);
            $batch->update([
                'status' => $willRetry ? 'retrying' : 'failed',
                'error' => $failure,
                'finished_at' => $willRetry ? null : now(),
            ]);
            ApplicationLogger::record(
                $willRetry ? 'warning' : 'error',
                'audit-worker',
                $willRetry ? 'retry_scheduled' : 'processing_failed',
                $failure['message'],
                $failure + ['retry_in_seconds' => $willRetry ? $this->backoff()[$attempt - 1] : null],
                $batch,
                incidentId: $failure['incident_id'],
                attempt: $attempt,
            );

            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        $batch = AnalysisBatch::withTrashed()->find($this->batchId);
        if (! $batch || $batch->trashed() || in_array($batch->status, ['completed', 'cancelled', 'superseded'], true)) {
            return;
        }
        if ($batch->cancellationRequested()) {
            $this->finalizeCancellation($batch, 'Cancelamento aplicado no encerramento definitivo do job.');

            return;
        }
        $failure = $batch->error ?: AnalysisFailure::from($exception ?? new RuntimeException('Falha definitiva sem exceção disponível.'), $batch->attempt_count);
        $batch->update(['status' => 'failed', 'error' => $failure, 'finished_at' => now()]);
        if (! $batch->applicationLogs()->where('event', 'queue_exhausted')->exists()) {
            ApplicationLogger::record('critical', 'audit-worker', 'queue_exhausted', 'Todas as tentativas automáticas foram esgotadas.', $failure,
                $batch, incidentId: $failure['incident_id'] ?? null, attempt: $batch->attempt_count);
        }
    }

    private function payload(AnalysisBatch $batch): array
    {
        return [
            'batch_id' => $batch->id,
            'company' => $batch->company->only(['id', 'legal_name', 'trade_name', 'tax_id', 'state_registration', 'timezone']),
            'catalog_version_id' => $batch->catalog_version_id,
            'catalog_version' => $batch->catalogVersion->version,
            'catalog_sha256' => $batch->catalogVersion->source_sha256,
            'period_start' => optional($batch->period_start)->format('Y-m-d'),
            'period_end' => optional($batch->period_end)->format('Y-m-d'),
            'source_files' => $batch->sourceFiles->map->only(['id', 'original_name', 'sha256', 'storage_path'])->values()->all(),
        ];
    }

    private function persistResults(AnalysisBatch $batch, array $result): bool
    {
        return DB::transaction(function () use ($batch, $result): bool {
            $lockedBatch = AnalysisBatch::withTrashed()->lockForUpdate()->findOrFail($batch->id);
            if ($lockedBatch->status === 'completed') {
                return true;
            }
            if ($lockedBatch->trashed() || $lockedBatch->cancellationRequested()) {
                if (! $lockedBatch->trashed()) {
                    $lockedBatch->update([
                        'status' => 'cancelled',
                        'cancelled_at' => $lockedBatch->cancelled_at ?? now(),
                        'finished_at' => now(),
                        'error' => null,
                    ]);
                }

                return false;
            }

            $documentMap = [];
            $itemMap = [];

            foreach ($result['documents'] as $documentData) {
                $items = $documentData['items'] ?? [];
                unset($documentData['items']);
                $reference = $documentData['document_ref'];
                unset($documentData['document_ref']);
                $documentData['id'] = (string) Str::uuid();
                $documentData['analysis_batch_id'] = $lockedBatch->id;
                $documentData['normalized'] = json_encode($documentData['normalized'] ?? [], JSON_UNESCAPED_UNICODE);
                $document = FiscalDocument::create($documentData);
                $documentMap[$reference] = $document->id;

                foreach ($items as $itemData) {
                    $itemNumber = $itemData['item_number'];
                    $itemData['id'] = (string) Str::uuid();
                    $itemData['fiscal_document_id'] = $document->id;
                    $itemData['tax_components'] = json_encode($itemData['tax_components'] ?? [], JSON_UNESCAPED_UNICODE);
                    $itemData['catalog_match'] = json_encode($itemData['catalog_match'] ?? [], JSON_UNESCAPED_UNICODE);
                    $itemData['details'] = json_encode($itemData['details'] ?? [], JSON_UNESCAPED_UNICODE);
                    $item = FiscalItem::create($itemData);
                    $itemMap[$reference.':'.$itemNumber] = $item->id;
                }
            }

            foreach ($result['findings'] as $findingData) {
                $reference = $findingData['document_ref'] ?? null;
                $itemNumber = $findingData['item_number'] ?? null;
                unset($findingData['document_ref'], $findingData['item_number']);
                $findingData['id'] = (string) Str::uuid();
                $findingData['analysis_batch_id'] = $lockedBatch->id;
                $findingData['fiscal_document_id'] = $reference ? ($documentMap[$reference] ?? null) : null;
                $findingData['fiscal_item_id'] = $reference && $itemNumber ? ($itemMap[$reference.':'.$itemNumber] ?? null) : null;
                $findingData['evidence'] = json_encode($findingData['evidence'] ?? [], JSON_UNESCAPED_UNICODE);
                Finding::create($findingData);
            }

            foreach ($result['reports'] ?? [] as $report) {
                ReportArtifact::create([
                    'analysis_batch_id' => $lockedBatch->id,
                    'type' => $report['type'],
                    'template_version' => $report['template_version'],
                    'storage_path' => $report['storage_path'],
                    'sha256' => $report['sha256'] ?? null,
                    'size' => $report['size'] ?? null,
                    'metadata' => $report['metadata'] ?? [],
                ]);
            }

            $lockedBatch->update([
                'status' => 'completed',
                'processed_files' => $batch->total_files,
                'document_count' => count($result['documents']),
                'item_count' => collect($result['documents'])->sum(fn ($document) => count($document['items'] ?? [])),
                'finding_count' => count($result['findings']),
                'progress' => 1,
                'summary' => $result['summary'] ?? [],
                'error' => null,
                'finished_at' => now(),
            ]);

            return true;
        }, 3);
    }

    private function cancellationWasRequested(AnalysisBatch $batch): bool
    {
        $current = AnalysisBatch::withTrashed()->find($batch->id);

        return ! $current || $current->trashed() || $current->cancellationRequested();
    }

    private function isEngineCancellation(int $status, mixed $body): bool
    {
        return $status === 409 && is_array($body) && ($body['error_code'] ?? null) === 'AUDIT_CANCELLED';
    }

    private function finalizeCancellation(AnalysisBatch $batch, string $message): void
    {
        $cancelled = DB::transaction(function () use ($batch): ?AnalysisBatch {
            $locked = AnalysisBatch::withTrashed()->lockForUpdate()->find($batch->id);
            if (! $locked || $locked->trashed() || in_array($locked->status, ['completed', 'failed', 'superseded'], true)) {
                return null;
            }
            $locked->update([
                'status' => 'cancelled',
                'cancel_requested_at' => $locked->cancel_requested_at ?? now(),
                'cancelled_at' => $locked->cancelled_at ?? now(),
                'finished_at' => now(),
                'error' => null,
            ]);

            return $locked;
        }, 3);

        if ($cancelled) {
            $this->recordCancellation($cancelled, $message);
        }
    }

    private function recordCancellation(AnalysisBatch $batch, string $message): void
    {
        $batch = AnalysisBatch::find($batch->id);
        if (! $batch) {
            return;
        }
        if ($batch->applicationLogs()->where('event', 'processing_cancelled')->exists()) {
            return;
        }
        ApplicationLogger::record('notice', 'audit-worker', 'processing_cancelled', $message, [
            'cancel_requested_at' => optional($batch->cancel_requested_at)->toIso8601String(),
            'cancelled_at' => optional($batch->cancelled_at)->toIso8601String(),
        ], $batch, attempt: max(1, $this->attempts()));
    }
}
