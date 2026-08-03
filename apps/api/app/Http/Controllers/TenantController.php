<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Services\CnpjLookup;
use App\Services\TenantAccess;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TenantController extends Controller
{
    public function index(Request $request)
    {
        return Tenant::withCount(['companies', 'users'])
            ->whereIn('id', TenantAccess::ids($request->user()))
            ->orderBy('legal_name')->paginate(50);
    }

    public function store(Request $request)
    {
        TenantAccess::ensurePlatformAdmin($request->user());
        $this->normalizeTaxId($request);
        $data = $request->validate($this->rules());
        $tenant = Tenant::create($data);
        return response()->json($tenant->loadCount(['companies', 'users']), 201);
    }

    public function update(Request $request, Tenant $tenant)
    {
        TenantAccess::ensure($request->user(), $tenant->id);
        $this->normalizeTaxId($request);
        $tenant->update($request->validate($this->rules($tenant->id, true)));
        return $tenant->loadCount(['companies', 'users']);
    }

    public function destroy(Request $request, Tenant $tenant)
    {
        TenantAccess::ensurePlatformAdmin($request->user());
        $tenant->update(['active' => false]);
        return response()->noContent();
    }

    public function lookup(Request $request, CnpjLookup $lookup)
    {
        $data = $request->validate(['cnpj' => 'required|string|max:18']);
        return $lookup->find($data['cnpj']);
    }

    private function rules(?string $tenantId = null, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';
        return [
            'legal_name' => "$required|string|max:255",
            'trade_name' => 'nullable|string|max:255',
            'tax_id' => [$required, 'digits:14', Rule::unique('tenants', 'tax_id')->ignore($tenantId)],
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:30',
            'active' => 'sometimes|boolean',
            'settings' => 'sometimes|array',
        ];
    }

    private function normalizeTaxId(Request $request): void
    {
        if ($request->exists('tax_id')) {
            $request->merge(['tax_id' => preg_replace('/\D/', '', (string) $request->input('tax_id'))]);
        }
    }
}
