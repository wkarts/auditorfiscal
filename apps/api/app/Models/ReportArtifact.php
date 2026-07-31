<?php
namespace App\Models; use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model; class ReportArtifact extends Model {use HasUuids;protected $guarded=[];protected function casts():array{return ['metadata'=>'array'];}public function batch(){return $this->belongsTo(AnalysisBatch::class,'analysis_batch_id');}}
