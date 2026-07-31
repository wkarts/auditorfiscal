<?php
namespace App\Models; use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model; class FiscalItem extends Model {use HasUuids;protected $guarded=[];protected function casts():array{return ['tax_components'=>'array','catalog_match'=>'array'];}public function document(){return $this->belongsTo(FiscalDocument::class,'fiscal_document_id');}}
