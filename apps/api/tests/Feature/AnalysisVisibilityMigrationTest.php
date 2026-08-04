<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AnalysisVisibilityMigrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::dropIfExists('users');
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->boolean('all_clients')->default(false);
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('users');
        parent::tearDown();
    }

    public function test_migration_adds_reversible_audit_visibility_scope(): void
    {
        $migration = require database_path('migrations/2026_08_04_100000_add_analysis_visibility_to_users.php');
        $migration->up();

        DB::table('users')->insert(['id' => 1]);
        $this->assertTrue(Schema::hasColumn('users', 'analysis_visibility'));
        $this->assertSame('own', DB::table('users')->where('id', 1)->value('analysis_visibility'));

        $migration->down();
        $this->assertFalse(Schema::hasColumn('users', 'analysis_visibility'));
    }
}
