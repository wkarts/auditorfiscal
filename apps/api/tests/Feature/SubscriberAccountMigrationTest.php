<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SubscriberAccountMigrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        foreach (['company_user', 'tenant_user', 'companies', 'users', 'tenants'] as $table) Schema::dropIfExists($table);
        Schema::create('tenants', fn (Blueprint $table) => [$table->uuid('id')->primary()]);
        Schema::create('users', fn (Blueprint $table) => [$table->id(), $table->string('email')]);
        Schema::create('companies', fn (Blueprint $table) => [$table->uuid('id')->primary(), $table->uuid('tenant_id')]);
        Schema::create('company_user', fn (Blueprint $table) => [$table->uuid('company_id'), $table->unsignedBigInteger('user_id'), $table->boolean('is_default')->default(false), $table->timestamps()]);
        Schema::create('tenant_user', fn (Blueprint $table) => [$table->uuid('tenant_id'), $table->unsignedBigInteger('user_id'), $table->timestamps()]);
    }

    protected function tearDown(): void
    {
        foreach (['company_user', 'tenant_user', 'companies', 'users', 'tenants'] as $table) Schema::dropIfExists($table);
        parent::tearDown();
    }

    public function test_migration_assigns_legacy_user_to_the_clients_account_and_is_reversible(): void
    {
        DB::table('tenants')->insert(['id' => '11111111-1111-4111-8111-111111111111']);
        DB::table('users')->insert(['id' => 1, 'email' => 'user@example.com']);
        DB::table('companies')->insert(['id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'tenant_id' => '11111111-1111-4111-8111-111111111111']);
        DB::table('company_user')->insert(['company_id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'user_id' => 1, 'is_default' => true, 'created_at' => now(), 'updated_at' => now()]);
        $migration = require database_path('migrations/2026_08_03_050000_assign_users_to_subscriber_accounts.php');

        $migration->up();

        $this->assertSame('11111111-1111-4111-8111-111111111111', DB::table('users')->where('id', 1)->value('tenant_id'));
        $migration->down();
        $this->assertFalse(Schema::hasColumn('users', 'tenant_id'));
    }
}
