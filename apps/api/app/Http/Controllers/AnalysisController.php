<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessAnalysisBatch;
use App\Jobs\GenerateFiscalAuxiliaryDocument;
use App\Models\AnalysisBatch;
use App\Models\FiscalCatalogVersion;
use App\Models\FiscalDocument;
use App\Models\Finding;
use App\Models\SourceFile;
use App\Services\AnalysisFailure;
use App\Services\AnalysisAnalytics;
use App\Services\ApplicationLogger;
use App\Services\CompanyAccess;
use App\Services\AnalysisAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class AnalysisController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'company_id' => 'nullable|uuid',
            'status' => 'nullable|string|in:uploading,queued,processing,retrying,cancelling,completed,failed,cancelled,superseded',
            'visibility' => 'nullable|string|in:active,deleted',
            'search' => 'nullable|string|max:120',
            'page' => 'nullable|integer|min:1',
        ]);
        $visibility = $data['visibility'] ?? 'active';
        if ($visibility === 'deleted') {
            abort_unless($request->user()->can('analyses.restore'), 403, 'Você não possui permissão para consultar auditorias excluídas.');
        }

        $query = AnalysisAccess::query($request->user());
        if ($visibility === 'deleted') {
            $query->onlyTrashed();
        }
        $query->with('company', 'catalogVersion')
            ->withCount(['documents', 'findings', 'reports']);

        if (! empty($data['company_id'])) {
            $query->where('company_id', $data['company_id']);
        }
        if (! empty($data['search'])) {
            $term = '%'.mb_strtolower(trim($data['search'])).'%';
            $query->where(function ($search) use ($term): void {
                $search->whereRaw('LOWER(name) LIKE ?', [$term])
                    ->orWhereHas('company', function ($company) use ($term): void {
                        $company->whereRaw('LOWER(legal_name) LIKE ?', [$term])
                            ->orWhereRaw('LOWER(COALESCE(trade_name, \'\')) LIKE ?', [$term])
                            ->orWhere('tax_id', 'like', str_replace('%', '', $term).'%');
                    });
            });
        }

        $summaryQuery = clone $query;
        $summary = [
            'total' => (clone $summaryQuery)->count(),
            'active' => (clone $summaryQuery)->whereIn('status', ['uploading', 'queued', 'processing', 'retrying', 'cancelling'])->count(),
            'completed' => (clone $summaryQuery)->where('status', 'completed')->count(),
            'failed' => (clone $summaryQuery)->where('status', 'failed')->count(),
            'cancelled' => (clone $summaryQuery)->where('status', 'cancelled')->count(),
        ];

        if (! empty($data['status'])) {
            $query->where('status', $data['status']);
        }

        $batches = $query->latest()->paginate(30);
        $canCancel = $request->user()->can('analyses.cancel');
        $canDelete = $request->user()->can('analyses.delete');
        $canRestore = $request->user()->can('analyses.restore');
        $batches->getCollection()->each(function (AnalysisBatch $batch) use ($canCancel, $canDelete, $canRestore): void {
            if (is_array($batch->error)) {
                $batch->setAttribute('error', ApplicationLogger::sanitize($batch->error));
            }
            $batch->setAttribute('can_cancel', $canCancel && ! $batch->trashed() && $batch->canBeCancelled());
            $batch->setAttribute('can_delete', $canDelete && ! $batch->trashed() && $batch->canBeDeleted());
            $batch->setAttribute('can_restore', $canRestore && $batch->trashed());
        });

        return response()->json(array_merge($batches->toArray(), ['summary' => $summary]));
    }

    public function store(Request $request)
    {
        $max = (int) env('MAX_UPLOAD_MB', 500) * 1024;
        $data = $request->validate([
            'company_id' => 'required|uuid|exists:companies,id',
            'name' => 'required|string|max:255',
            'period_start' => 'nullable|date',
            'period_end' => 'nullable|date|after_or_equal:period_start',
            'catalog_version_id' => 'nullable|uuid|exists:fiscal_catalog_versions,id',
            'files' => 'required|array|min:1',
            'files.*' => "file|max:$max|mimes:xml,zip,pdf",
        ]);
        $company = CompanyAccess::ensure($request->user(), $data['company_id']);
        abort_if(! $company->active || ! $company->tenant?->active, 422, 'O cliente auditado ou a empresa da plataforma está inativo para novas auditorias.');
        $catalog = $data['catalog_version_id']
            ? FiscalCatalogVersion::whereKey($data['catalog_version_id'])->where('status', 'published')->firstOrFail()
            : FiscalCatalogVersion::published()->latest('published_at')->firstOrFail();
        $disk = Storage::disk(config('filesystems.default'));
        $uploadedPaths = [];
        $batch = null;

        try {
            $batch = AnalysisBatch::create([
                'company_id' => $data['company_id'],
                'catalog_version_id' => $catalog->id,
                'name' => $data['name'],
                'period_start' => $data['period_start'] ?? null,
                'period_end' => $data['period_end'] ?? null,
                'status' => 'uploading',
                'total_files' => count($data['files']),
                'created_by' => $request->user()->id,
            ]);

            ApplicationLogger::record('info', 'audit-api', 'upload_started', 'Recebimento dos arquivos iniciado.', [
                'file_count' => count($data['files']),
            ], $batch, $request->user()->id, $request->attributes->get('request_id'));

            foreach ($data['files'] as $file) {
                $hash = hash_file('sha256', $file->getRealPath());
                $safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', $file->getClientOriginalName());
                $path = "batches/{$batch->id}/sources/".Str::uuid().'-'.$safeName;
                $disk->put($path, file_get_contents($file->getRealPath()));
                $uploadedPaths[] = $path;
                SourceFile::create([
                    'analysis_batch_id' => $batch->id,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                    'size' => $file->getSize(),
                    'sha256' => $hash,
                    'storage_path' => $path,
                ]);
            }

            $batch->update(['status' => 'queued']);
            ApplicationLogger::record('info', 'audit-api', 'queued', 'Auditoria enviada para a fila de processamento.', [
                'queue' => 'high',
                'file_count' => count($data['files']),
            ], $batch, $request->user()->id, $request->attributes->get('request_id'));
            ProcessAnalysisBatch::dispatch($batch->id)->onQueue('high');
        } catch (Throwable $exception) {
            $failure = AnalysisFailure::from($exception);
            if ($batch) {
                ApplicationLogger::record('error', 'audit-api', 'queue_submission_failed', $failure['message'], $failure, $batch,
                    $request->user()->id, $request->attributes->get('request_id'), $failure['incident_id']);
            }
            if ($uploadedPaths !== []) {
                try {
                    $disk->delete($uploadedPaths);
                } catch (Throwable $cleanupException) {
                    report($cleanupException);
                }
            }
            try {
                $batch?->forceDelete();
            } catch (Throwable $cleanupException) {
                report($cleanupException);
            }
            report($exception);
            abort(503, 'Não foi possível iniciar a auditoria. Verifique o armazenamento de objetos e a fila de processamento e tente novamente.');
        }

        return response()->json($batch->load('sourceFiles', 'catalogVersion'), 202);
    }

    public function show(Request $request, AnalysisBatch $batch)
    {
        AnalysisAccess::ensure($request->user(), $batch);
        $batch->load(
            'company',
            'catalogVersion',
            'sourceFiles',
            'reports',
            'reprocessedFrom:id,name,status',
            'reprocesses:id,reprocessed_from_id,name,status,created_at',
        )->loadCount(['documents', 'findings', 'applicationLogs']);
        $batch->setAttribute('can_reprocess', $batch->canBeReprocessed());
        $batch->setAttribute('can_cancel', $request->user()->can('analyses.cancel') && $batch->canBeCancelled());
        $batch->setAttribute('can_delete', $request->user()->can('analyses.delete') && $batch->canBeDeleted());
        $batch->setAttribute('reprocess_block_reason', $batch->reprocessBlockReason());
        if (is_array($batch->error)) {
            $batch->setAttribute('error', ApplicationLogger::sanitize($batch->error));
        }

        return $batch;
    }

    public function logs(Request $request, AnalysisBatch $batch)
    {
        AnalysisAccess::ensure($request->user(), $batch);
        $perPage = min(max((int) $request->input('per_page', 100), 1), 200);

        return $batch->applicationLogs()->latest('id')->paginate($perPage);
    }

    public function log(Request $request, AnalysisBatch $batch, \App\Models\ApplicationLog $log)
    {
        AnalysisAccess::ensure($request->user(), $batch);
        abort_unless($log->analysis_batch_id === $batch->id, 404);
        return response()->json($log, 200, ['Content-Disposition' => 'attachment; filename="audit-log-'.$log->id.'.json"'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    public function exportLogs(Request $request, AnalysisBatch $batch)
    {
        AnalysisAccess::ensure($request->user(), $batch);
        $query = $batch->applicationLogs()->oldest('id');
        return response()->streamDownload(function () use ($query): void {
            $query->chunkById(500, function ($logs): void {
                foreach ($logs as $log) echo json_encode($log->toArray(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n";
            });
        }, 'auditoria-'.$batch->id.'-logs.ndjson', ['Content-Type' => 'application/x-ndjson; charset=UTF-8']);
    }

    public function analytics(Request $request, AnalysisBatch $batch, AnalysisAnalytics $analytics)
    {
        AnalysisAccess::ensure($request->user(), $batch);
        return $analytics->build($batch);
    }

    public function documents(Request $request, AnalysisBatch $batch)
    {
        AnalysisAccess::ensure($request->user(), $batch);
        $query = $batch->documents()->withCount('items');
        foreach (['direction', 'status', 'number', 'issuer_tax_id', 'recipient_tax_id'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, $request->$field);
            }
        }

        return $query->orderByDesc('issued_at')->paginate(min((int) $request->input('per_page', 100), 500));
    }

    public function document(Request $request, AnalysisBatch $batch, FiscalDocument $document)
    {
        AnalysisAccess::ensure($request->user(), $batch);
        abort_unless($document->analysis_batch_id === $batch->id, 404);

        return $this->normalizeDocumentJson($document->load('items', 'findings'));
    }

    /**
     * Compatibilidade para lotes processados antes da correção de serialização.
     * Alguns campos JSONB foram persistidos como uma string que contém JSON.
     * A resposta normaliza somente em memória, sem alterar a evidência original
     * nem exigir migração bloqueante em bases produtivas.
     */
    private function normalizeDocumentJson(FiscalDocument $document): FiscalDocument
    {
        $document->setAttribute('normalized', $this->jsonObject($document->getAttribute('normalized')));

        $document->items->each(function ($item): void {
            foreach (['details', 'tax_components', 'catalog_match'] as $attribute) {
                $item->setAttribute($attribute, $this->jsonObject($item->getAttribute($attribute)));
            }
        });

        $document->findings->each(function ($finding): void {
            $finding->setAttribute('evidence', $this->jsonObject($finding->getAttribute('evidence')));
        });

        return $document;
    }

    private function jsonObject(mixed $value): array
    {
        while (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return [];
            }
            $value = $decoded;
        }

        return is_array($value) ? $value : [];
    }

    public function xml(Request $request, AnalysisBatch $batch, FiscalDocument $document)
    {
        return $this->documentFile($request, $batch, $document, 'xml_storage_path', 'application/xml; charset=UTF-8', 'xml');
    }

    public function danfe(Request $request, AnalysisBatch $batch, FiscalDocument $document)
    {
        return $this->auxiliaryDocument($request, $batch, $document);
    }

    public function auxiliaryDocument(Request $request, AnalysisBatch $batch, FiscalDocument $document)
    {
        $field = $document->auxiliary_document_storage_path ? 'auxiliary_document_storage_path' : 'danfe_storage_path';

        return $this->documentFile($request, $batch, $document, $field, 'application/pdf', 'pdf');
    }

    public function generateAuxiliaryDocument(Request $request, AnalysisBatch $batch, FiscalDocument $document)
    {
        AnalysisAccess::ensure($request->user(), $batch);
        abort_unless($document->analysis_batch_id === $batch->id, 404);
        if ($document->auxiliary_document_storage_path || $document->danfe_storage_path) {
            return response()->json(['data' => $document, 'message' => 'O documento auxiliar já está disponível.']);
        }
        if ($document->auxiliary_document_status === 'not_supported') {
            return response()->json(['data' => $document, 'message' => 'Este modelo não possui renderizador configurado.'], 422);
        }

        $document->update(['auxiliary_document_status' => 'queued', 'auxiliary_document_error' => null]);
        GenerateFiscalAuxiliaryDocument::dispatch($document->id)->onQueue('reports');
        ApplicationLogger::record('info', 'auxiliary-document', 'generation_requested', 'Geração manual do documento auxiliar solicitada.', [
            'model' => $document->model,
        ], $batch, $request->user()->id, $request->attributes->get('request_id'));

        return response()->json(['data' => $document->fresh(), 'message' => 'Geração do documento auxiliar enviada para a fila.'], 202);
    }

    public function findings(Request $request, AnalysisBatch $batch)
    {
        AnalysisAccess::ensure($request->user(), $batch);
        $query = $batch->findings()->with([
            'fiscalDocument:id,number,access_key',
            'fiscalItem:id,item_number,ncm,description',
        ]);
        foreach (['severity', 'category', 'status', 'rule_code'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, $request->$field);
            }
        }

        return $query->orderByRaw("CASE severity WHEN 'critical' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 ELSE 4 END")
            ->paginate(100);
    }

    public function resolve(Request $request, AnalysisBatch $batch, Finding $finding)
    {
        AnalysisAccess::ensure($request->user(), $batch);
        abort_unless($finding->analysis_batch_id === $batch->id, 404);
        $data = $request->validate([
            'status' => 'required|in:open,in_review,resolved,dismissed',
            'resolution_notes' => 'nullable|string',
            'assigned_to' => 'nullable|exists:users,id',
        ]);
        $finding->update($data + [
            'resolved_at' => in_array($data['status'], ['resolved', 'dismissed'], true) ? now() : null,
        ]);

        return $finding;
    }

    public function cancel(Request $request, AnalysisBatch $batch)
    {
        AnalysisAccess::ensure($request->user(), $batch);
        $data = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $result = DB::transaction(function () use ($batch, $request, $data): array {
            $locked = AnalysisBatch::query()->lockForUpdate()->findOrFail($batch->id);
            if ($locked->status === 'cancelled') {
                return [$locked, false, 'A auditoria já estava cancelada.'];
            }
            if ($locked->status === 'cancelling') {
                return [$locked, false, 'O cancelamento já foi solicitado e está em andamento.'];
            }
            abort_unless($locked->canBeCancelled(), 409, 'Esta auditoria já terminou e não pode mais ser cancelada.');

            $immediate = in_array($locked->status, ['uploading', 'queued'], true);
            $locked->update([
                'status' => $immediate ? 'cancelled' : 'cancelling',
                'cancel_requested_at' => now(),
                'cancelled_at' => $immediate ? now() : null,
                'cancelled_by' => $request->user()->id,
                'cancellation_reason' => $data['reason'] ?? null,
                'finished_at' => $immediate ? now() : null,
                'error' => null,
            ]);

            return [
                $locked,
                true,
                $immediate
                    ? 'Auditoria cancelada antes do início do processamento.'
                    : 'Cancelamento solicitado. O motor encerrará no próximo ponto seguro.',
            ];
        }, 3);

        /** @var AnalysisBatch $updated */
        [$updated, $created, $message] = $result;
        if ($created) {
            ApplicationLogger::record(
                $updated->status === 'cancelled' ? 'notice' : 'warning',
                'audit-api',
                $updated->status === 'cancelled' ? 'cancelled' : 'cancellation_requested',
                $message,
                [
                    'previous_status' => $batch->status,
                    'reason' => $data['reason'] ?? null,
                ],
                $updated,
                $request->user()->id,
                $request->attributes->get('request_id'),
            );
        }

        return response()->json(['data' => $updated->fresh(), 'message' => $message]);
    }

    public function destroy(Request $request, AnalysisBatch $batch)
    {
        AnalysisAccess::ensure($request->user(), $batch);
        $data = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        DB::transaction(function () use ($batch, $request, $data): void {
            $locked = AnalysisBatch::query()->lockForUpdate()->findOrFail($batch->id);
            abort_unless($locked->canBeDeleted(), 409, 'Cancele e aguarde o encerramento da auditoria antes de excluí-la.');
            $locked->update([
                'deleted_by' => $request->user()->id,
                'deletion_reason' => $data['reason'] ?? null,
            ]);
            ApplicationLogger::record('notice', 'audit-api', 'soft_deleted', 'Auditoria movida para excluídas.', [
                'status' => $locked->status,
                'reason' => $data['reason'] ?? null,
            ], $locked, $request->user()->id, $request->attributes->get('request_id'));
            $locked->delete();
        }, 3);

        return response()->json(['message' => 'Auditoria movida para excluídas. Os arquivos e registros foram preservados.']);
    }

    public function restore(Request $request, string $batchId)
    {
        $data = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);
        $batch = AnalysisBatch::onlyTrashed()->findOrFail($batchId);
        AnalysisAccess::ensure($request->user(), $batch);

        DB::transaction(function () use ($batch, $request, $data): void {
            $locked = AnalysisBatch::onlyTrashed()->lockForUpdate()->findOrFail($batch->id);
            $locked->restore();
            ApplicationLogger::record('notice', 'audit-api', 'restored', 'Auditoria restaurada da área de excluídas.', [
                'reason' => $data['reason'] ?? null,
                'previous_deletion_reason' => $locked->deletion_reason,
            ], $locked, $request->user()->id, $request->attributes->get('request_id'));
        }, 3);

        return response()->json(['data' => $batch->fresh(), 'message' => 'Auditoria restaurada com sucesso.']);
    }

    public function reprocess(Request $request, AnalysisBatch $batch)
    {
        AnalysisAccess::ensure($request->user(), $batch);
        $data = $request->validate([
            'catalog_version_id' => 'nullable|uuid|exists:fiscal_catalog_versions,id',
        ]);
        $catalogId = $data['catalog_version_id'] ?? $batch->catalog_version_id;
        FiscalCatalogVersion::whereKey($catalogId)->where('status', 'published')->firstOrFail();
        $batch->loadMissing('sourceFiles');
        abort_if($batch->sourceFiles->isEmpty(), 422, 'A auditoria não possui arquivos de origem para reprocessar.');
        $disk = Storage::disk(config('filesystems.default'));
        $missingFiles = $batch->sourceFiles->filter(fn (SourceFile $file) => ! $disk->exists($file->storage_path));
        abort_if($missingFiles->isNotEmpty(), 422, 'Um ou mais arquivos de origem não estão mais disponíveis no armazenamento.');

        $newBatch = DB::transaction(function () use ($batch, $catalogId, $request): AnalysisBatch {
            $source = AnalysisBatch::query()->lockForUpdate()->with('sourceFiles')->findOrFail($batch->id);
            abort_unless($source->canBeReprocessed(), 409, $source->reprocessBlockReason());
            $activeReprocess = $source->reprocesses()->whereIn('status', ['uploading', 'queued', 'processing', 'retrying', 'cancelling'])->first();
            abort_if($activeReprocess, 409, 'Já existe um reprocessamento ativo para esta auditoria.');

            $new = $source->replicate([
                'status', 'progress', 'processed_files', 'document_count', 'item_count', 'finding_count',
                'summary', 'error', 'attempt_count', 'last_attempt_at', 'started_at', 'finished_at',
                'reprocessed_from_id', 'cancel_requested_at', 'cancelled_at', 'cancelled_by',
                'cancellation_reason', 'deleted_at', 'deleted_by', 'deletion_reason',
            ]);
            $new->id = (string) Str::uuid();
            $new->reprocessed_from_id = $source->id;
            $new->status = 'queued';
            $new->progress = 0;
            $new->attempt_count = 0;
            $new->name = Str::limit($source->name.' — reprocessamento', 255, '');
            $new->catalog_version_id = $catalogId;
            $new->created_by = $request->user()->id;
            $new->save();

            foreach ($source->sourceFiles as $file) {
                $copy = $file->replicate();
                $copy->id = (string) Str::uuid();
                $copy->analysis_batch_id = $new->id;
                $copy->save();
            }

            if ($source->status === 'queued') {
                $source->update(['status' => 'superseded', 'finished_at' => now()]);
            }

            ApplicationLogger::record('notice', 'audit-api', 'reprocess_requested', 'Reprocessamento solicitado pelo usuário.', [
                'new_batch_id' => $new->id,
                'catalog_version_id' => $catalogId,
            ], $source, $request->user()->id, $request->attributes->get('request_id'));
            ApplicationLogger::record('info', 'audit-api', 'queued', 'Reprocessamento enviado para a fila.', [
                'source_batch_id' => $source->id,
                'queue' => 'high',
            ], $new, $request->user()->id, $request->attributes->get('request_id'));

            return $new;
        }, 3);

        try {
            ProcessAnalysisBatch::dispatch($newBatch->id)->onQueue('high');
        } catch (Throwable $exception) {
            $failure = AnalysisFailure::from($exception);
            $newBatch->update(['status' => 'failed', 'error' => $failure, 'finished_at' => now()]);
            ApplicationLogger::record('error', 'audit-api', 'reprocess_queue_failed', $failure['message'], $failure,
                $newBatch, $request->user()->id, $request->attributes->get('request_id'), $failure['incident_id']);
            abort(503, 'O lote de reprocessamento foi criado, mas não pôde ser enviado à fila. Consulte o log do lote.');
        }

        return response()->json($newBatch->load('sourceFiles', 'catalogVersion'), 202);
    }

    private function documentFile(Request $request, AnalysisBatch $batch, FiscalDocument $document, string $field, string $contentType, string $extension)
    {
        AnalysisAccess::ensure($request->user(), $batch);
        abort_unless($document->analysis_batch_id === $batch->id, 404);
        $path = $document->{$field};
        abort_unless($path && Storage::disk(config('filesystems.default'))->exists($path), 404, strtoupper($extension).' não disponível.');
        $disk = Storage::disk(config('filesystems.default'));
        $prefix = $extension === 'pdf'
            ? ($document->auxiliary_document_type ?: ($document->model === '65' ? 'DANFCE' : 'DANFE'))
            : 'documento-fiscal';
        $filename = $prefix.'-'.($document->access_key ?: $document->id).'.'.$extension;
        $disposition = $request->boolean('download') ? 'attachment' : 'inline';
        return response()->stream(function () use ($disk, $path): void {
            $stream = $disk->readStream($path);
            if (! is_resource($stream)) abort(404);
            fpassthru($stream);
            fclose($stream);
        }, 200, ['Content-Type' => $contentType, 'Content-Disposition' => $disposition.'; filename="'.$filename.'"', 'X-Content-Type-Options' => 'nosniff']);
    }
}
