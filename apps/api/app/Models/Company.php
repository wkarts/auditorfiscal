<?php
namespace App\Models; use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class Company extends Model {use HasUuids;protected $fillable=['legal_name','trade_name','tax_id','state_registration','timezone','active','settings'];protected function casts():array{return ['settings'=>'array','active'=>'boolean'];}public function users(){return $this->belongsToMany(User::class)->withPivot('is_default')->withTimestamps();}public function batches(){return $this->hasMany(AnalysisBatch::class);}}
