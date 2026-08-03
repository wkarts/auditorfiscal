<?php

namespace App\Services;

use App\Models\Company;
use App\Models\User;

class CompanyAccess
{
    public static function ensure(User $user, string $companyId): Company
    {
        $query = Company::query()->whereKey($companyId);
        $query->whereIn('tenant_id', TenantAccess::ids($user));
        if (! TenantAccess::hasAllClients($user)) {
            $query->whereHas('users', fn ($relation) => $relation->where('users.id', $user->id));
        }

        return $query->firstOrFail();
    }

    public static function ids(User $user)
    {
        $query = Company::query();
        $query->whereIn('tenant_id', TenantAccess::ids($user));
        if (! TenantAccess::hasAllClients($user)) {
            $query->whereHas('users', fn ($relation) => $relation->where('users.id', $user->id));
        }

        return $query->pluck('id');
    }
}
