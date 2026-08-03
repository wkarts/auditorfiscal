<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fiscal_items')) {
            return;
        }

        if (! Schema::hasColumn('fiscal_items', 'chassis')) {
            Schema::table('fiscal_items', function (Blueprint $table): void {
                $table->string('chassis', 17)->nullable()->index();
            });
        }

        if (! Schema::hasColumn('fiscal_items', 'plate')) {
            Schema::table('fiscal_items', function (Blueprint $table): void {
                $table->string('plate', 10)->nullable()->index();
            });
        }

        if (! Schema::hasColumn('fiscal_items', 'used_movable_good')) {
            Schema::table('fiscal_items', function (Blueprint $table): void {
                $table->boolean('used_movable_good')->default(false);
            });
        }

        if (! Schema::hasColumn('fiscal_items', 'pis_cofins')) {
            Schema::table('fiscal_items', function (Blueprint $table): void {
                $table->decimal('pis_cofins', 18, 2)->default(0);
            });
        }

        if (! Schema::hasColumn('fiscal_items', 'pis_cofins_base')) {
            Schema::table('fiscal_items', function (Blueprint $table): void {
                $table->decimal('pis_cofins_base', 18, 2)->default(0);
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('fiscal_items')) {
            return;
        }

        foreach (['plate', 'chassis'] as $column) {
            if (Schema::hasColumn('fiscal_items', $column) && Schema::hasIndex('fiscal_items', [$column])) {
                Schema::table('fiscal_items', function (Blueprint $table) use ($column): void {
                    $table->dropIndex([$column]);
                });
            }
        }

        foreach (['pis_cofins_base', 'pis_cofins', 'used_movable_good', 'plate', 'chassis'] as $column) {
            if (Schema::hasColumn('fiscal_items', $column)) {
                Schema::table('fiscal_items', function (Blueprint $table) use ($column): void {
                    $table->dropColumn($column);
                });
            }
        }
    }
};
