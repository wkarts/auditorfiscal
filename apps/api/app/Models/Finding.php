<?php
namespace App\Models; use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class Finding extends Model {use HasUuids;protected $guarded=[];protected function casts():array{return ['evidence'=>'array','resolved_at'=>'datetime'];}public function fiscalDocument(){return $this->belongsTo(FiscalDocument::class);}public function fiscalItem(){return $this->belongsTo(FiscalItem::class);}public function batch(){return $this->belongsTo(AnalysisBatch::class,'analysis_batch_id');}}
