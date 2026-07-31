<?php
use Illuminate\Database\Migrations\Migration; use Illuminate\Database\Schema\Blueprint; use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  $names=config('permission.table_names');$cols=config('permission.column_names');
  Schema::create($names['permissions'],fn(Blueprint $t)=>[$t->bigIncrements('id'),$t->string('name'),$t->string('guard_name'),$t->timestamps(),$t->unique(['name','guard_name'])]);
  Schema::create($names['roles'],fn(Blueprint $t)=>[$t->bigIncrements('id'),$t->string('name'),$t->string('guard_name'),$t->timestamps(),$t->unique(['name','guard_name'])]);
  Schema::create($names['model_has_permissions'],function(Blueprint $t)use($names,$cols){$t->unsignedBigInteger('permission_id');$t->string('model_type');$t->unsignedBigInteger($cols['model_morph_key']);$t->index([$cols['model_morph_key'],'model_type']);$t->foreign('permission_id')->references('id')->on($names['permissions'])->cascadeOnDelete();$t->primary(['permission_id',$cols['model_morph_key'],'model_type']);});
  Schema::create($names['model_has_roles'],function(Blueprint $t)use($names,$cols){$t->unsignedBigInteger('role_id');$t->string('model_type');$t->unsignedBigInteger($cols['model_morph_key']);$t->index([$cols['model_morph_key'],'model_type']);$t->foreign('role_id')->references('id')->on($names['roles'])->cascadeOnDelete();$t->primary(['role_id',$cols['model_morph_key'],'model_type']);});
  Schema::create($names['role_has_permissions'],function(Blueprint $t)use($names){$t->unsignedBigInteger('permission_id');$t->unsignedBigInteger('role_id');$t->foreign('permission_id')->references('id')->on($names['permissions'])->cascadeOnDelete();$t->foreign('role_id')->references('id')->on($names['roles'])->cascadeOnDelete();$t->primary(['permission_id','role_id']);});
 }
 public function down():void{foreach(['role_has_permissions','model_has_roles','model_has_permissions','roles','permissions'] as $k)Schema::dropIfExists(config('permission.table_names')[$k]);}
};
