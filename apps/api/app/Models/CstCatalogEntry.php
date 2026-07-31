<?php
namespace App\Models; use Illuminate\Database\Eloquent\Model; class CstCatalogEntry extends Model {protected $guarded=[];protected function casts():array{return ['applicable_nfe'=>'boolean','indicators'=>'array'];}}
