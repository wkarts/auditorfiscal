<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ClientAccessScopeMigrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::dropIfExists('users');
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->boolean('active')->default(true);
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('users');
        parent::tearDown();
    }

    public function test_migration_adds_reversible_all_clients_scope(): void
    {
        $migration = require database_path('migrations/2026_08_03_040000_add_client_access_scope_to_users.php');
        $migration->up();

        $this->assertTrue(Schema::hasColumn('users', 'all_clients'));
        DB::table('users')->insert(['id' => 1]);
        $this->assertFalse((bool) DB::table('users')->where('id', 1)->value('all_clients'));

        $migration->down();
        $this->assertFalse(Schema::hasColumn('users', 'all_clients'));
    }
}
