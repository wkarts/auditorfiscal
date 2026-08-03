<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AnalysisBatch extends Model
{
    use HasUuids, SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'summary' => 'array',
            'error' => 'array',
            'period_start' => 'date',
            'period_end' => 'date',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'last_attempt_at' => 'datetime',
            'cancel_requested_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function canBeCancelled(): bool
    {
        return in_array($this->status, ['uploading', 'queued', 'processing', 'retrying', 'cancelling'], true);
    }

    public function cancellationRequested(): bool
    {
        return $this->cancel_requested_at !== null
            || in_array($this->status, ['cancelling', 'cancelled'], true);
    }

    public function canBeDeleted(): bool
    {
        return in_array($this->status, ['completed', 'failed', 'cancelled', 'superseded'], true);
    }

    public function canBeReprocessed(): bool
    {
        if (in_array($this->status, ['failed', 'completed', 'cancelled'], true)) {
            return true;
        }

        return $this->status === 'queued'
            && $this->updated_at?->lte(now()->subMinutes((int) config('analysis.stale_queue_minutes', 15)));
    }

    public function reprocessBlockReason(): ?string
    {
        if ($this->canBeReprocessed()) {
            return null;
        }

        return match ($this->status) {
            'uploading', 'queued' => 'A auditoria ainda está na fila e não atingiu o tempo limite para reprocessamento.',
            'processing', 'retrying', 'cancelling' => 'A auditoria ainda está sendo processada ou cancelada.',
            'superseded' => 'Esta auditoria já foi substituída por um reprocessamento.',
            default => 'O status atual não permite reprocessamento.',
        };
    }

    public function company() { return $this->belongsTo(Company::class); }
    public function catalogVersion() { return $this->belongsTo(FiscalCatalogVersion::class, 'catalog_version_id'); }
    public function sourceFiles() { return $this->hasMany(SourceFile::class); }
    public function documents() { return $this->hasMany(FiscalDocument::class); }
    public function findings() { return $this->hasMany(Finding::class); }
    public function reports() { return $this->hasMany(ReportArtifact::class); }
    public function applicationLogs() { return $this->hasMany(ApplicationLog::class); }
    public function reprocessedFrom() { return $this->belongsTo(self::class, 'reprocessed_from_id'); }
    public function reprocesses() { return $this->hasMany(self::class, 'reprocessed_from_id'); }
}
