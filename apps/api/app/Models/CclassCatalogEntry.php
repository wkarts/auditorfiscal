<?php
namespace App\Models; use Illuminate\Database\Eloquent\Model; class CclassCatalogEntry extends Model {protected $guarded=[];protected function casts():array{return ['applicable_nfe'=>'boolean','format_warning'=>'boolean','indicators'=>'array','valid_from'=>'date','valid_to'=>'date','updated_at_source'=>'date'];}}
