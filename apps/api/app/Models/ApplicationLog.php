<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;

class ApplicationLog extends Model
{
    use MassPrunable;

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function prunable(): Builder
    {
        return static::where('created_at', '<=', now()->subDays((int) config('analysis.log_retention_days', 90)));
    }

    public function analysisBatch()
    {
        return $this->belongsTo(AnalysisBatch::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
