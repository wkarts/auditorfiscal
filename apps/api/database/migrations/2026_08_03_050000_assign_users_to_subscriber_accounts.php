<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->foreignUuid('tenant_id')->nullable()->after('id')->constrained('tenants')->nullOnDelete();
        });

        $platformAdminEmail = (string) env('ADMIN_EMAIL', '');
        foreach (DB::table('users')->orderBy('id')->get(['id', 'email']) as $user) {
            if ($platformAdminEmail !== '' && $user->email === $platformAdminEmail) {
                continue;
            }

            $tenantId = DB::table('company_user')
                ->join('companies', 'companies.id', '=', 'company_user.company_id')
                ->where('company_user.user_id', $user->id)
                ->orderByDesc('company_user.is_default')
                ->orderBy('company_user.created_at')
                ->value('companies.tenant_id');

            $tenantId ??= DB::table('tenant_user')
                ->where('user_id', $user->id)
                ->orderBy('created_at')
                ->value('tenant_id');

            if ($tenantId) {
                DB::table('users')->where('id', $user->id)->update(['tenant_id' => $tenantId]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropConstrainedForeignId('tenant_id'));
    }
};
