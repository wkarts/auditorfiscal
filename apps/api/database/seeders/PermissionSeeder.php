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
    public const AUDITOR_PERMISSIONS = [
        'clients.view',
        'clients.manage',
        'tenants.view',
        'catalogs.view',
        'analyses.view',
        'analyses.create',
        'analyses.cancel',
        'analyses.resolve',
        'reports.download',
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'users.manage',
            'accounts.view',
            'accounts.manage',
            'clients.view',
            'clients.manage',
            'tenants.view',
            'tenants.manage',
            'companies.manage',
            'catalogs.view',
            'catalogs.manage',
            'catalogs.publish',
            'analyses.view',
            'analyses.create',
            'analyses.cancel',
            'analyses.delete',
            'analyses.restore',
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
        $auditor->syncPermissions(self::AUDITOR_PERMISSIONS);

        $viewer = Role::firstOrCreate(['name' => 'Consulta', 'guard_name' => 'web']);
        $viewer->syncPermissions(['clients.view', 'tenants.view', 'catalogs.view', 'analyses.view', 'reports.download']);

        $email = (string) env('ADMIN_EMAIL');
        $password = (string) env('ADMIN_PASSWORD');
        if ($email === '' || $password === '') {
            throw new RuntimeException('ADMIN_EMAIL e ADMIN_PASSWORD são obrigatórios para executar o seeder.');
        }

        $user = User::firstOrNew(['email' => $email]);
        $user->name = 'Administrador';
        $user->active = true;
        $user->all_clients = true;
        $user->tenant_id = null;
        if (! $user->exists || ! Hash::check($password, (string) $user->password)) {
            $user->password = Hash::make($password);
        }
        $user->save();
        $user->syncRoles([$admin]);

        $tenant = \App\Models\Tenant::query()->where('settings->seed_key', 'demo-account')->first()
            ?? \App\Models\Tenant::query()->where('tax_id', '99999999000191')->first()
            ?? \App\Models\Tenant::create([
                'tax_id' => '99999999000191',
                'legal_name' => 'Conta Sintética de Demonstração',
                'trade_name' => 'Empresa modelo',
                'active' => true,
            ]);
        $tenant->update(['settings' => array_merge($tenant->settings ?? [], ['seed_key' => 'demo-account'])]);
        $company = Company::query()->where('settings->seed_key', 'demo-client')->first()
            ?? Company::query()->where('tax_id', '99999999000191')->first()
            ?? Company::create([
                'tenant_id' => $tenant->id,
                'tax_id' => '99999999000191',
                'legal_name' => 'Cliente Sintético de Demonstração',
                'trade_name' => 'Cliente modelo',
                'active' => true,
            ]);
        $company->update(['settings' => array_merge($company->settings ?? [], ['seed_key' => 'demo-client'])]);
        if (! $company->tenant_id) $company->update(['tenant_id' => $tenant->id]);
    }
}
