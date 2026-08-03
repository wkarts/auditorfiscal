<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Services\CompanyAccess;
use App\Services\TenantAccess;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    public function index(Request $request)
    {
        $query = Company::with('tenant:id,legal_name,trade_name,tax_id,active')
            ->whereIn('id', CompanyAccess::ids($request->user()));
        if (! $request->user()->hasRole('Administrador')) {
            $query->where('active', true)->whereHas('tenant', fn ($tenant) => $tenant->where('active', true));
        }
        return $query->orderBy('legal_name')->paginate(50);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'tenant_id' => 'required|uuid|exists:tenants,id',
            'legal_name' => 'required|string|max:255',
            'trade_name' => 'nullable|string|max:255',
            'tax_id' => 'required|digits:14|unique:companies,tax_id',
            'state_registration' => 'nullable|string|max:30',
            'settings' => 'sometimes|array',
        ]);
        $tenant = TenantAccess::ensure($request->user(), $data['tenant_id']);
        $company = Company::create($data);
        $company->users()->attach($request->user()->id, ['is_default' => false]);
        $tenant->users()->syncWithoutDetaching([$request->user()->id]);
        return response()->json($company->load('tenant'), 201);
    }

    public function update(Request $request, Company $company)
    {
        CompanyAccess::ensure($request->user(), $company->id);
        $data = $request->validate([
            'tenant_id' => 'sometimes|uuid|exists:tenants,id',
            'legal_name' => 'sometimes|string|max:255',
            'trade_name' => 'nullable|string|max:255',
            'state_registration' => 'nullable|string|max:30',
            'active' => 'sometimes|boolean',
            'settings' => 'sometimes|array',
        ]);
        if (isset($data['tenant_id'])) TenantAccess::ensure($request->user(), $data['tenant_id']);
        $company->update($data);
        if (isset($data['tenant_id'])) {
            $allowedUsers = $company->tenant->users()->pluck('users.id');
            $disallowedUsers = $company->users()->pluck('users.id')->diff($allowedUsers);
            if ($disallowedUsers->isNotEmpty()) $company->users()->detach($disallowedUsers);
        }
        return $company->load('tenant');
    }

    public function destroy(Request $request, Company $company)
    {
        CompanyAccess::ensure($request->user(), $company->id);
        $company->update(['active' => false]);
        return response()->noContent();
    }
}
