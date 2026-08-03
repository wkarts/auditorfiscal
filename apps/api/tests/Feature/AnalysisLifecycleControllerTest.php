<?php

namespace Tests\Feature;

use App\Http\Controllers\AnalysisController;
use App\Models\AnalysisBatch;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AnalysisLifecycleControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        foreach (['model_has_roles', 'roles', 'analysis_batches', 'companies', 'users', 'tenants'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::create('tenants', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('legal_name');
            $table->boolean('active')->default(true);
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
        Schema::create('companies', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('legal_name');
            $table->timestamps();
        });
        Schema::create('analysis_batches', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->string('status', 30);
            $table->decimal('progress', 7, 4)->default(0);
            $table->json('error')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('cancel_requested_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->unsignedBigInteger('cancelled_by')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->softDeletes();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->text('deletion_reason')->nullable();
            $table->timestamps();
        });
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        DB::table('tenants')->insert(['id' => '11111111-1111-4111-8111-111111111111', 'legal_name' => 'Codesplan', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('users')->insert(['id' => 1, 'tenant_id' => '11111111-1111-4111-8111-111111111111', 'name' => 'Administrador', 'email' => 'admin@example.com', 'password' => 'hash', 'active' => true, 'all_clients' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('roles')->insert(['id' => 1, 'name' => 'Administrador', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('model_has_roles')->insert(['role_id' => 1, 'model_type' => User::class, 'model_id' => 1]);
        DB::table('companies')->insert(['id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'tenant_id' => '11111111-1111-4111-8111-111111111111', 'legal_name' => 'Dubahia', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('analysis_batches')->insert(['id' => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb', 'company_id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'status' => 'queued', 'progress' => 0, 'created_at' => now(), 'updated_at' => now()]);
    }

    protected function tearDown(): void
    {
        foreach (['model_has_roles', 'roles', 'analysis_batches', 'companies', 'users', 'tenants'] as $table) {
            Schema::dropIfExists($table);
        }
        parent::tearDown();
    }

    public function test_cancel_delete_and_restore_are_idempotent_and_preserve_the_record(): void
    {
        $controller = new AnalysisController;
        $user = User::query()->findOrFail(1);
        $batch = AnalysisBatch::query()->findOrFail('bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb');

        $cancel = Request::create('/', 'POST', ['reason' => 'Solicitação operacional']);
        $cancel->setUserResolver(fn () => $user);
        $controller->cancel($cancel, $batch);
        $controller->cancel($cancel, $batch->fresh());

        $this->assertDatabaseHas('analysis_batches', [
            'id' => $batch->id,
            'status' => 'cancelled',
            'cancelled_by' => 1,
            'cancellation_reason' => 'Solicitação operacional',
        ]);

        $delete = Request::create('/', 'DELETE', ['reason' => 'Organização da lista']);
        $delete->setUserResolver(fn () => $user);
        $controller->destroy($delete, $batch->fresh());
        $this->assertSoftDeleted('analysis_batches', ['id' => $batch->id, 'deleted_by' => 1]);

        $restore = Request::create('/', 'POST', ['reason' => 'Revisão necessária']);
        $restore->setUserResolver(fn () => $user);
        $controller->restore($restore, $batch->id);

        $this->assertDatabaseHas('analysis_batches', ['id' => $batch->id, 'deleted_at' => null, 'status' => 'cancelled']);
    }
}
