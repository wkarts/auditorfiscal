<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FiscalItemsAuditFieldsMigrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('fiscal_items');
        Schema::create('fiscal_items', function (Blueprint $table): void {
            $table->uuid('id')->primary();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('fiscal_items');

        parent::tearDown();
    }

    public function test_migration_adds_and_removes_audit_fields_idempotently(): void
    {
        $migration = require database_path('migrations/2026_08_03_000000_add_audit_fields_to_fiscal_items_table.php');
        $columns = ['chassis', 'plate', 'used_movable_good', 'pis_cofins', 'pis_cofins_base'];

        $migration->up();
        $migration->up();

        $this->assertTrue(Schema::hasColumns('fiscal_items', $columns));

        $migration->down();
        $migration->down();

        $this->assertFalse(Schema::hasColumns('fiscal_items', $columns));
    }
}
