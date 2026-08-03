<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TenantAndDocumentDetailsMigrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        foreach (['tenant_user','tenants','company_user','fiscal_items','fiscal_documents','companies','users'] as $table) Schema::dropIfExists($table);
        Schema::create('users', fn (Blueprint $table) => $table->id());
        Schema::create('companies', function (Blueprint $table): void {$table->uuid('id')->primary();$table->string('legal_name');$table->string('trade_name')->nullable();$table->string('tax_id');$table->boolean('active')->default(true);$table->timestamps();});
        Schema::create('company_user', function (Blueprint $table): void {$table->uuid('company_id');$table->unsignedBigInteger('user_id');$table->timestamps();});
        Schema::create('fiscal_documents', function (Blueprint $table): void {$table->uuid('id')->primary();$table->text('xml_storage_path')->nullable();});
        Schema::create('fiscal_items', function (Blueprint $table): void {$table->uuid('id')->primary();$table->json('catalog_match')->nullable();});
        DB::table('users')->insert(['id'=>1]);
        DB::table('companies')->insert(['id'=>'11111111-1111-4111-8111-111111111111','legal_name'=>'Empresa Teste','tax_id'=>'99999999000191','active'=>true,'created_at'=>now(),'updated_at'=>now()]);
        DB::table('company_user')->insert(['company_id'=>'11111111-1111-4111-8111-111111111111','user_id'=>1,'created_at'=>now(),'updated_at'=>now()]);
    }

    protected function tearDown(): void
    {
        foreach (['tenant_user','tenants','company_user','fiscal_items','fiscal_documents','companies','users'] as $table) Schema::dropIfExists($table);
        parent::tearDown();
    }

    public function test_migration_backfills_tenant_access_and_document_fields(): void
    {
        $migration=require database_path('migrations/2026_08_03_030000_add_tenants_and_document_details.php');$migration->up();
        $tenantId=DB::table('companies')->value('tenant_id');
        $this->assertNotNull($tenantId);$this->assertDatabaseHas('tenants',['id'=>$tenantId,'tax_id'=>'99999999000191']);$this->assertDatabaseHas('tenant_user',['tenant_id'=>$tenantId,'user_id'=>1]);
        $this->assertTrue(Schema::hasColumn('fiscal_documents','danfe_storage_path'));$this->assertTrue(Schema::hasColumn('fiscal_items','details'));
        $migration->down();$this->assertFalse(Schema::hasTable('tenants'));
    }
}
