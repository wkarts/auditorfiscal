<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Services\CnpjLookup;
use App\Services\TenantAccess;
use Illuminate\Http\Request;

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
        $data = $request->validate($this->rules());
        $tenant = Tenant::create($data);
        $tenant->users()->syncWithoutDetaching([$request->user()->id]);
        return response()->json($tenant->loadCount(['companies', 'users']), 201);
    }

    public function update(Request $request, Tenant $tenant)
    {
        TenantAccess::ensure($request->user(), $tenant->id);
        $tenant->update($request->validate($this->rules($tenant->id, true)));
        return $tenant->loadCount(['companies', 'users']);
    }

    public function destroy(Request $request, Tenant $tenant)
    {
        TenantAccess::ensure($request->user(), $tenant->id);
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
            'tax_id' => "$required|digits:14|unique:tenants,tax_id,$tenantId",
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:30',
            'active' => 'sometimes|boolean',
            'settings' => 'sometimes|array',
        ];
    }
}
