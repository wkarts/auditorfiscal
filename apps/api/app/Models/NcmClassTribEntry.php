<?php
namespace App\Models; use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class NcmClassTribEntry extends Model {use HasUuids;protected $guarded=[];protected function casts():array{return ['conditions'=>'array','validation_issues'=>'array','valid_from'=>'date','valid_to'=>'date','allow_child_inheritance'=>'boolean','inherited_ncm'=>'boolean'];}public function version(){return $this->belongsTo(FiscalCatalogVersion::class,'catalog_version_id');}}
