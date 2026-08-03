<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index()
    {
        return User::with('roles', 'tenants:id,legal_name,tax_id', 'companies:id,tenant_id,legal_name,tax_id')->paginate(50);
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules());
        $this->ensureCompanyTenantConsistency($data);
        $user = User::create(['name' => $data['name'], 'email' => $data['email'], 'password' => Hash::make($data['password'])]);
        $this->syncAccess($user, $data);
        return response()->json($user->load('roles', 'tenants', 'companies'), 201);
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate($this->rules($user->id, true));
        $merged = [
            'tenant_ids' => $data['tenant_ids'] ?? $user->tenants()->pluck('tenants.id')->all(),
            'company_ids' => $data['company_ids'] ?? $user->companies()->pluck('companies.id')->all(),
        ];
        $this->ensureCompanyTenantConsistency($merged);
        $attributes = collect($data)->except(['role', 'tenant_ids', 'company_ids'])->all();
        if (empty($attributes['password'])) unset($attributes['password']);
        elseif (isset($attributes['password'])) $attributes['password'] = Hash::make($attributes['password']);
        $user->update($attributes);
        $this->syncAccess($user, $data);
        return $user->load('roles', 'tenants', 'companies');
    }

    public function roles() { return Role::with('permissions')->get(); }

    private function rules(?int $userId = null, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';
        return [
            'name' => "$required|string|max:255",
            'email' => "$required|email|max:255|unique:users,email,$userId",
            'password' => $partial ? 'nullable|string|min:12' : 'required|string|min:12',
            'role' => "$required|exists:roles,name",
            'tenant_ids' => 'sometimes|array',
            'tenant_ids.*' => 'uuid|exists:tenants,id',
            'company_ids' => 'sometimes|array',
            'company_ids.*' => 'uuid|exists:companies,id',
            'active' => 'sometimes|boolean',
        ];
    }

    private function ensureCompanyTenantConsistency(array $data): void
    {
        $companyIds = array_values(array_unique($data['company_ids'] ?? []));
        $tenantIds = array_values(array_unique($data['tenant_ids'] ?? []));
        if ($companyIds === []) return;
        $invalid = Company::whereIn('id', $companyIds)->whereNotIn('tenant_id', $tenantIds)->exists();
        if ($invalid) {
            throw ValidationException::withMessages(['company_ids' => 'Toda empresa selecionada deve pertencer a um tenant associado ao usuário.']);
        }
    }

    private function syncAccess(User $user, array $data): void
    {
        if (isset($data['role'])) $user->syncRoles([$data['role']]);
        if (array_key_exists('tenant_ids', $data)) $user->tenants()->sync($data['tenant_ids'] ?? []);
        if (array_key_exists('company_ids', $data)) $user->companies()->sync($data['company_ids'] ?? []);
    }
}
