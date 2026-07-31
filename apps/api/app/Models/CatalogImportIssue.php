<?php
namespace App\Models; use Illuminate\Database\Eloquent\Model; class CatalogImportIssue extends Model {protected $guarded=[];protected function casts():array{return ['context'=>'array'];}}
