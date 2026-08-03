<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AnalysisLifecycleMigrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        foreach (['analysis_batches', 'companies', 'users'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::create('users', fn (Blueprint $table) => $table->id());
        Schema::create('companies', fn (Blueprint $table) => $table->uuid('id')->primary());
        Schema::create('analysis_batches', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained('companies');
            $table->string('status', 30)->default('queued');
            $table->timestampsTz();
        });
    }

    protected function tearDown(): void
    {
        foreach (['analysis_batches', 'companies', 'users'] as $table) {
            Schema::dropIfExists($table);
        }
        parent::tearDown();
    }

    public function test_migration_adds_reversible_cancellation_and_soft_deletion_fields(): void
    {
        $migration = require database_path('migrations/2026_08_03_060000_add_analysis_lifecycle_controls.php');

        $migration->up();

        $this->assertTrue(Schema::hasColumns('analysis_batches', [
            'cancel_requested_at',
            'cancelled_at',
            'cancelled_by',
            'cancellation_reason',
            'deleted_at',
            'deleted_by',
            'deletion_reason',
        ]));

        $migration->down();

        $this->assertFalse(Schema::hasColumn('analysis_batches', 'cancel_requested_at'));
        $this->assertFalse(Schema::hasColumn('analysis_batches', 'deleted_at'));
    }
}
