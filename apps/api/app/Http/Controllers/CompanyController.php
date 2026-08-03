<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Services\CompanyAccess;
use App\Services\TenantAccess;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CompanyController extends Controller
{
    public function index(Request $request)
    {
        $query = Company::with('tenant:id,legal_name,trade_name,tax_id,active')
            ->whereIn('id', CompanyAccess::ids($request->user()));
        if (! TenantAccess::isPlatformAdmin($request->user())) {
            $query->where('active', true)->whereHas('tenant', fn ($tenant) => $tenant->where('active', true));
        }
        return $query->orderBy('legal_name')->paginate(50);
    }

    public function store(Request $request)
    {
        $this->normalizeInput($request);
        $data = $request->validate([
            'tenant_id' => 'sometimes|uuid|exists:tenants,id',
            'account_id' => 'sometimes|uuid|exists:tenants,id',
            'legal_name' => 'required|string|max:255',
            'trade_name' => 'nullable|string|max:255',
            'tax_id' => ['required', 'digits:14', Rule::unique('companies', 'tax_id')],
            'state_registration' => 'nullable|string|max:30',
            'settings' => 'sometimes|array',
        ]);
        $data['tenant_id'] = TenantAccess::resolveTarget(
            $request->user(),
            $data['tenant_id'] ?? $data['account_id'] ?? null,
        );
        unset($data['account_id']);
        $company = Company::create($data);
        if (! TenantAccess::hasAllClients($request->user())) {
            $company->users()->syncWithoutDetaching([$request->user()->id => ['is_default' => false]]);
        }
        return response()->json($company->load('tenant'), 201);
    }

    public function update(Request $request, Company $company)
    {
        CompanyAccess::ensure($request->user(), $company->id);
        $this->normalizeInput($request);
        $data = $request->validate([
            'tenant_id' => 'sometimes|uuid|exists:tenants,id',
            'account_id' => 'sometimes|uuid|exists:tenants,id',
            'legal_name' => 'sometimes|string|max:255',
            'trade_name' => 'nullable|string|max:255',
            'tax_id' => ['sometimes', 'digits:14', Rule::unique('companies', 'tax_id')->ignore($company->id)],
            'state_registration' => 'nullable|string|max:30',
            'active' => 'sometimes|boolean',
            'settings' => 'sometimes|array',
        ]);
        if (isset($data['account_id']) && ! isset($data['tenant_id'])) {
            $data['tenant_id'] = $data['account_id'];
        }
        unset($data['account_id']);
        if (isset($data['tenant_id'])) {
            TenantAccess::ensure($request->user(), $data['tenant_id']);
        }
        $company->update($data);
        if (isset($data['tenant_id'])) {
            $allowedUsers = $company->tenant->users()->pluck('users.id');
            $disallowedUsers = $company->users()->pluck('users.id')->diff($allowedUsers);
            if ($disallowedUsers->isNotEmpty()) {
                $company->users()->detach($disallowedUsers);
            }
        }
        return $company->load('tenant');
    }

    public function destroy(Request $request, Company $company)
    {
        CompanyAccess::ensure($request->user(), $company->id);
        $company->update(['active' => false]);
        return response()->noContent();
    }

    private function normalizeInput(Request $request): void
    {
        if ($request->exists('tax_id')) {
            $request->merge(['tax_id' => preg_replace('/\D/', '', (string) $request->input('tax_id'))]);
        }
    }
}
