<?php
namespace Database\Seeders;
use App\Models\Company; use App\Models\User; use Illuminate\Database\Seeder; use Illuminate\Support\Facades\Hash; use Spatie\Permission\Models\Permission; use Spatie\Permission\Models\Role; use Spatie\Permission\PermissionRegistrar;
class PermissionSeeder extends Seeder {
 public function run():void{
  app(PermissionRegistrar::class)->forgetCachedPermissions();
  $permissions=['users.manage','companies.manage','catalogs.view','catalogs.manage','catalogs.publish','analyses.view','analyses.create','analyses.resolve','reports.download','audit.view'];
  foreach($permissions as $p)Permission::firstOrCreate(['name'=>$p,'guard_name'=>'web']);
  $admin=Role::firstOrCreate(['name'=>'Administrador','guard_name'=>'web']);$admin->syncPermissions($permissions);
  $auditor=Role::firstOrCreate(['name'=>'Auditor Fiscal','guard_name'=>'web']);$auditor->syncPermissions(['catalogs.view','analyses.view','analyses.create','analyses.resolve','reports.download']);
  $viewer=Role::firstOrCreate(['name'=>'Consulta','guard_name'=>'web']);$viewer->syncPermissions(['catalogs.view','analyses.view','reports.download']);
  $user=User::firstOrCreate(['email'=>env('ADMIN_EMAIL','admin@auditor.local')],['name'=>'Administrador','password'=>Hash::make(env('ADMIN_PASSWORD','ChangeMe!123')),'active'=>true]);$user->syncRoles([$admin]);
  $company=Company::firstOrCreate(['tax_id'=>'00000000000000'],['legal_name'=>'Empresa de Demonstração','trade_name'=>'Demonstração','active'=>true]);$company->users()->syncWithoutDetaching([$user->id=>['is_default'=>true]]);
 }
}
