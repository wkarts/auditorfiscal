<?php

namespace Tests\Feature;

use App\Http\Controllers\CompanyController;
use App\Http\Controllers\TenantController;
use App\Http\Controllers\UserController;
use App\Models\Company;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CompanyAccess;
use App\Services\TenantAccess;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ClientCompanyAccessTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        foreach (['model_has_roles', 'roles', 'tenant_user', 'company_user', 'companies', 'users', 'tenants'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::create('tenants', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('legal_name');
            $table->string('trade_name')->nullable();
            $table->string('tax_id', 14);
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->boolean('active')->default(true);
            $table->json('settings')->default('{}');
            $table->timestamps();
        });
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->uuid('tenant_id')->nullable();
            $table->string('name');
            $table->string('email');
            $table->string('password');
            $table->boolean('active')->default(true);
            $table->boolean('all_clients')->default(false);
            $table->timestamps();
        });
        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
        });
        Schema::create('model_has_roles', function (Blueprint $table): void {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->primary(['role_id', 'model_id', 'model_type']);
        });
        Schema::create('tenant_user', function (Blueprint $table): void {
            $table->uuid('tenant_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamps();
            $table->primary(['tenant_id', 'user_id']);
        });
        Schema::create('companies', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('legal_name');
            $table->string('trade_name')->nullable();
            $table->string('tax_id', 14);
            $table->string('state_registration')->nullable();
            $table->string('timezone')->nullable();
            $table->boolean('active')->default(true);
            $table->json('settings')->default('{}');
            $table->timestamps();
        });
        Schema::create('company_user', function (Blueprint $table): void {
            $table->uuid('company_id');
            $table->unsignedBigInteger('user_id');
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->primary(['company_id', 'user_id']);
        });
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    protected function tearDown(): void
    {
        foreach (['model_has_roles', 'roles', 'tenant_user', 'company_user', 'companies', 'users', 'tenants'] as $table) {
            Schema::dropIfExists($table);
        }
        parent::tearDown();
    }

    public function test_selected_client_is_limited_to_the_users_subscriber_account(): void
    {
        $this->seedScenario();
        $user = User::query()->findOrFail(1);

        $this->assertSame(['aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa'], CompanyAccess::ids($user)->all());
        $this->assertSame(['11111111-1111-4111-8111-111111111111'], TenantAccess::ids($user)->all());
        $this->assertSame('Dubahia', CompanyAccess::ensure($user, 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa')->legal_name);

        $this->expectException(ModelNotFoundException::class);
        CompanyAccess::ensure($user, 'cccccccc-cccc-4ccc-8ccc-cccccccccccc');
    }

    public function test_all_clients_never_crosses_the_subscriber_account_boundary(): void
    {
        $this->seedScenario();
        $user = User::query()->findOrFail(1);
        $user->update(['all_clients' => true]);

        $this->assertEqualsCanonicalizing([
            'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
        ], CompanyAccess::ids($user->fresh())->all());
        $this->assertNotContains('cccccccc-cccc-4ccc-8ccc-cccccccccccc', CompanyAccess::ids($user->fresh())->all());
    }

    public function test_platform_master_has_unrestricted_access_to_all_accounts(): void
    {
        $this->seedScenario();
        DB::table('users')->insert(['id' => 9, 'tenant_id' => null, 'name' => 'Master', 'email' => 'master@example.test', 'password' => 'hash', 'active' => true, 'all_clients' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('roles')->insert(['id' => 9, 'name' => 'Administrador', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('model_has_roles')->insert(['role_id' => 9, 'model_type' => User::class, 'model_id' => 9]);
        $master = User::query()->findOrFail(9);

        $this->assertTrue(TenantAccess::isPlatformAdmin($master));
        $this->assertCount(2, TenantAccess::ids($master));
        $this->assertCount(3, CompanyAccess::ids($master));
    }

    public function test_account_and_audited_client_cnpj_can_be_corrected(): void
    {
        $this->seedScenario();
        $user = User::query()->findOrFail(1);

        $clientRequest = Request::create('/', 'PATCH', ['tax_id' => '55.555.555/0001-55']);
        $clientRequest->setUserResolver(fn () => $user);
        (new CompanyController)->update($clientRequest, Company::query()->findOrFail('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa'));

        $accountRequest = Request::create('/', 'PATCH', ['tax_id' => '66.666.666/0001-66']);
        $accountRequest->setUserResolver(fn () => $user);
        (new TenantController)->update($accountRequest, Tenant::query()->findOrFail('11111111-1111-4111-8111-111111111111'));

        $this->assertDatabaseHas('companies', ['id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'tax_id' => '55555555000155']);
        $this->assertDatabaseHas('tenants', ['id' => '11111111-1111-4111-8111-111111111111', 'tax_id' => '66666666000166']);
    }

    public function test_master_creates_user_for_account_with_selected_clients(): void
    {
        $this->seedScenario();
        DB::table('users')->insert(['id' => 9, 'tenant_id' => null, 'name' => 'Master', 'email' => 'master@example.test', 'password' => 'hash', 'active' => true, 'all_clients' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('roles')->insert([
            ['id' => 1, 'name' => 'Auditor Fiscal', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 9, 'name' => 'Administrador', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('model_has_roles')->insert(['role_id' => 9, 'model_type' => User::class, 'model_id' => 9]);
        $master = User::query()->findOrFail(9);
        $request = Request::create('/', 'POST', [
            'name' => 'Usuário Codesplan',
            'email' => 'codesplan@example.test',
            'password' => 'SenhaForte123!',
            'role' => 'Auditor Fiscal',
            'account_id' => '11111111-1111-4111-8111-111111111111',
            'all_clients' => false,
            'client_ids' => ['aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa'],
        ]);
        $request->setUserResolver(fn () => $master);

        (new UserController)->store($request);

        $created = User::query()->where('email', 'codesplan@example.test')->firstOrFail();
        $this->assertSame('11111111-1111-4111-8111-111111111111', $created->tenant_id);
        $this->assertFalse($created->all_clients);
        $this->assertDatabaseHas('company_user', ['company_id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'user_id' => $created->id]);
        $this->assertSame(['aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa'], CompanyAccess::ids($created)->all());
    }

    private function seedScenario(): void
    {
        DB::table('tenants')->insert([
            ['id' => '11111111-1111-4111-8111-111111111111', 'legal_name' => 'Codesplan', 'tax_id' => '11111111000111', 'active' => true, 'settings' => '{}', 'created_at' => now(), 'updated_at' => now()],
            ['id' => '22222222-2222-4222-8222-222222222222', 'legal_name' => 'Outra Assinante', 'tax_id' => '22222222000122', 'active' => true, 'settings' => '{}', 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('users')->insert(['id' => 1, 'tenant_id' => '11111111-1111-4111-8111-111111111111', 'name' => 'Auditor Codesplan', 'email' => 'auditor@example.test', 'password' => 'hash', 'active' => true, 'all_clients' => false, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('tenant_user')->insert(['tenant_id' => '11111111-1111-4111-8111-111111111111', 'user_id' => 1, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('companies')->insert([
            ['id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'tenant_id' => '11111111-1111-4111-8111-111111111111', 'legal_name' => 'Dubahia', 'tax_id' => '33333333000133', 'active' => true, 'settings' => '{}', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb', 'tenant_id' => '11111111-1111-4111-8111-111111111111', 'legal_name' => 'Unifrio', 'tax_id' => '44444444000144', 'active' => true, 'settings' => '{}', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 'cccccccc-cccc-4ccc-8ccc-cccccccccccc', 'tenant_id' => '22222222-2222-4222-8222-222222222222', 'legal_name' => 'Cliente de Outra Conta', 'tax_id' => '55555555000155', 'active' => true, 'settings' => '{}', 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('company_user')->insert(['company_id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'user_id' => 1, 'is_default' => true, 'created_at' => now(), 'updated_at' => now()]);
    }
}
