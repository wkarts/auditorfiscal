<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\User;
use App\Services\CompanyAccess;
use App\Services\AnalysisAccess;
use App\Services\TenantAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::with(
            'roles',
            'account:id,legal_name,trade_name,tax_id,active',
            'clients:id,tenant_id,legal_name,trade_name,tax_id,active',
        );

        // Instalações antigas e alguns bancos efêmeros de teste ainda podem
        // não ter a tabela do Sanctum. A listagem de usuários continua
        // disponível e simplesmente informa todos como offline até a tabela
        // existir e os tokens começarem a registrar atividade.
        if (Schema::hasTable('personal_access_tokens')) {
            $query->withMax('tokens as last_seen_at', 'last_used_at');
        }

        if (! TenantAccess::isPlatformAdmin($request->user())) {
            $query->where('tenant_id', $request->user()->tenant_id ?? '__sem-conta__');
        }

        $users = $query->orderBy('name')->paginate(50);
        $threshold = now()->subMinutes(5);
        $users->getCollection()->each(function (User $user) use ($threshold): void {
            $seenAt = $user->last_seen_at ? Carbon::parse($user->last_seen_at) : null;
            $user->setAttribute('online', $seenAt?->greaterThanOrEqualTo($threshold) ?? false);
        });

        return $users;
    }

    public function store(Request $request)
    {
        $data = $this->normalize($request->validate($this->rules()), $request->user());
        $this->ensureAssignableAccess($request->user(), $data);

        return DB::transaction(function () use ($data) {
            $user = User::create([
                'tenant_id' => $data['tenant_id'],
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'active' => $data['active'] ?? true,
                'all_clients' => $data['all_clients'],
                'analysis_visibility' => $data['analysis_visibility'],
            ]);
            $this->syncAccess($user, $data);

            return response()->json($user->load('roles', 'account', 'clients'), 201);
        });
    }

    public function update(Request $request, User $user)
    {
        $this->ensureManageableUser($request->user(), $user);
        $data = $this->normalize($request->validate($this->rules($user->id, true)), $request->user(), $user);
        $this->ensureAssignableAccess($request->user(), $data);

        $attributes = collect($data)->only(['tenant_id', 'name', 'email', 'password', 'active', 'all_clients', 'analysis_visibility'])->all();
        if (empty($attributes['password'])) {
            unset($attributes['password']);
        } else {
            $attributes['password'] = Hash::make($attributes['password']);
        }

        return DB::transaction(function () use ($attributes, $data, $user) {
            $user->update($attributes);
            $this->syncAccess($user, $data);

            return $user->load('roles', 'account', 'clients');
        });
    }

    public function roles()
    {
        return Role::with('permissions')->get();
    }

    private function rules(?int $userId = null, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';
        $uniqueEmail = Rule::unique('users', 'email');
        if ($userId !== null) {
            $uniqueEmail->ignore($userId);
        }

        return [
            'name' => "$required|string|max:255",
            'email' => [$required, 'email', 'max:255', $uniqueEmail],
            'password' => $partial ? 'nullable|string|min:12' : 'required|string|min:12',
            'role' => "$required|exists:roles,name",
            'account_id' => 'sometimes|uuid|exists:tenants,id',
            'tenant_id' => 'sometimes|uuid|exists:tenants,id',
            'all_clients' => 'sometimes|boolean',
            'analysis_visibility' => 'sometimes|in:own,all',
            'client_ids' => 'sometimes|array',
            'client_ids.*' => 'uuid|exists:companies,id',
            'company_ids' => 'sometimes|array',
            'company_ids.*' => 'uuid|exists:companies,id',
            'active' => 'sometimes|boolean',
        ];
    }

    private function normalize(array $data, User $actor, ?User $user = null): array
    {
        $requestedTenantId = $data['account_id'] ?? $data['tenant_id'] ?? $user?->tenant_id;
        $data['tenant_id'] = TenantAccess::resolveTarget($actor, $requestedTenantId ? (string) $requestedTenantId : null);

        if (! array_key_exists('client_ids', $data) && array_key_exists('company_ids', $data)) {
            $data['client_ids'] = $data['company_ids'];
        }
        $data['client_ids'] = array_values(array_unique(
            $data['client_ids'] ?? $user?->clients()->pluck('companies.id')->all() ?? [],
        ));
        $data['all_clients'] = array_key_exists('all_clients', $data)
            ? (bool) $data['all_clients']
            : (bool) ($user?->all_clients ?? false);

        $role = $data['role'] ?? $user?->roles()->value('name');
        if ($role === 'Administrador') {
            $data['all_clients'] = true;
            $data['analysis_visibility'] = 'all';
        }
        $data['analysis_visibility'] = $data['analysis_visibility'] ?? $user?->analysis_visibility ?? 'own';
        if ($data['analysis_visibility'] === 'all' && ! AnalysisAccess::canAssignAll($actor)) {
            throw ValidationException::withMessages([
                'analysis_visibility' => 'Somente um administrador pode conceder acesso a todas as auditorias da empresa.',
            ]);
        }
        if ($data['all_clients']) {
            $data['client_ids'] = [];
        }

        unset($data['account_id'], $data['company_ids']);

        return $data;
    }

    private function ensureManageableUser(User $actor, User $target): void
    {
        abort_if($target->tenant_id === null, 404);
        if (! TenantAccess::isPlatformAdmin($actor)) {
            abort_unless((string) $actor->tenant_id === (string) $target->tenant_id, 404);
        }
    }

    private function ensureAssignableAccess(User $actor, array $data): void
    {
        if ($data['all_clients'] && ! TenantAccess::hasAllClients($actor)) {
            throw ValidationException::withMessages([
                'all_clients' => 'Você não pode conceder acesso a todos os clientes da empresa.',
            ]);
        }
        if (! $data['all_clients'] && $data['client_ids'] === []) {
            throw ValidationException::withMessages([
                'client_ids' => 'Selecione pelo menos um cliente auditado ou marque Todos os clientes.',
            ]);
        }

        $clientsInTargetAccount = Company::query()
            ->where('tenant_id', $data['tenant_id'])
            ->whereIn('id', $data['client_ids'])
            ->pluck('id');
        if ($clientsInTargetAccount->count() !== count($data['client_ids'])) {
            throw ValidationException::withMessages([
                'client_ids' => 'Um ou mais clientes não pertencem à conta selecionada.',
            ]);
        }

        if (! TenantAccess::isPlatformAdmin($actor)) {
            $invalid = collect($data['client_ids'])
                ->diff(CompanyAccess::ids($actor)->map(fn ($id) => (string) $id));
            if ($invalid->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'client_ids' => 'Você não pode conceder acesso a clientes que não estão disponíveis para o seu usuário.',
                ]);
            }
        }
    }

    private function syncAccess(User $user, array $data): void
    {
        if (isset($data['role'])) {
            $user->syncRoles([$data['role']]);
        }
        $user->clients()->sync($data['all_clients'] ? [] : $data['client_ids']);

        // Mantém o vínculo legado durante a transição de contrato.
        $user->tenants()->sync([$data['tenant_id']]);
    }
}
