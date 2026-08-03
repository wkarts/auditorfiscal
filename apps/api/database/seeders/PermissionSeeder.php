<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'users.manage',
            'companies.manage',
            'catalogs.view',
            'catalogs.manage',
            'catalogs.publish',
            'analyses.view',
            'analyses.create',
            'analyses.resolve',
            'reports.download',
            'audit.view',
            'logs.view',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $admin = Role::firstOrCreate(['name' => 'Administrador', 'guard_name' => 'web']);
        $admin->syncPermissions($permissions);

        $auditor = Role::firstOrCreate(['name' => 'Auditor Fiscal', 'guard_name' => 'web']);
        $auditor->syncPermissions([
            'catalogs.view',
            'analyses.view',
            'analyses.create',
            'analyses.resolve',
            'reports.download',
        ]);

        $viewer = Role::firstOrCreate(['name' => 'Consulta', 'guard_name' => 'web']);
        $viewer->syncPermissions(['catalogs.view', 'analyses.view', 'reports.download']);

        $email = (string) env('ADMIN_EMAIL');
        $password = (string) env('ADMIN_PASSWORD');
        if ($email === '' || $password === '') {
            throw new RuntimeException('ADMIN_EMAIL e ADMIN_PASSWORD são obrigatórios para executar o seeder.');
        }

        $user = User::firstOrNew(['email' => $email]);
        $user->name = 'Administrador';
        $user->active = true;
        if (! $user->exists || ! Hash::check($password, (string) $user->password)) {
            $user->password = Hash::make($password);
        }
        $user->save();
        $user->syncRoles([$admin]);

        $company = Company::firstOrCreate(
            ['tax_id' => '99999999000191'],
            ['legal_name' => 'Empresa Sintética de Demonstração', 'trade_name' => 'Demonstração', 'active' => true],
        );
        $company->users()->syncWithoutDetaching([$user->id => ['is_default' => true]]);
    }
}
