<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('legal_name');
            $table->string('trade_name')->nullable();
            $table->string('tax_id', 14)->unique();
            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            $table->boolean('active')->default(true);
            $table->jsonb('settings')->default('{}');
            $table->timestampsTz();
        });

        Schema::create('tenant_user', function (Blueprint $table): void {
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['tenant_id', 'user_id']);
        });

        Schema::table('companies', function (Blueprint $table): void {
            $table->foreignUuid('tenant_id')->nullable()->after('id')->constrained('tenants')->nullOnDelete();
        });

        foreach (DB::table('companies')->orderBy('created_at')->get() as $company) {
            $tenantId = (string) Str::uuid();
            DB::table('tenants')->insert([
                'id' => $tenantId,
                'legal_name' => $company->legal_name,
                'trade_name' => $company->trade_name,
                'tax_id' => $company->tax_id,
                'active' => $company->active,
                'settings' => '{}',
                'created_at' => $company->created_at ?? now(),
                'updated_at' => $company->updated_at ?? now(),
            ]);
            DB::table('companies')->where('id', $company->id)->update(['tenant_id' => $tenantId]);

            foreach (DB::table('company_user')->where('company_id', $company->id)->pluck('user_id') as $userId) {
                DB::table('tenant_user')->insertOrIgnore([
                    'tenant_id' => $tenantId,
                    'user_id' => $userId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        Schema::table('fiscal_documents', function (Blueprint $table): void {
            $table->text('danfe_storage_path')->nullable()->after('xml_storage_path');
        });
        Schema::table('fiscal_items', function (Blueprint $table): void {
            $table->jsonb('details')->default('{}')->after('catalog_match');
        });
    }

    public function down(): void
    {
        Schema::table('fiscal_items', fn (Blueprint $table) => $table->dropColumn('details'));
        Schema::table('fiscal_documents', fn (Blueprint $table) => $table->dropColumn('danfe_storage_path'));
        Schema::table('companies', fn (Blueprint $table) => $table->dropConstrainedForeignId('tenant_id'));
        Schema::dropIfExists('tenant_user');
        Schema::dropIfExists('tenants');
    }
};
