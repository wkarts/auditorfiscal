<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\User;

class TenantAccess
{
    public static function ensure(User $user, string $tenantId): Tenant
    {
        $query = Tenant::query()->whereKey($tenantId);
        if (! $user->hasRole('Administrador')) {
            $query->whereHas('users', fn ($relation) => $relation->where('users.id', $user->id));
        }

        return $query->firstOrFail();
    }

    public static function ids(User $user)
    {
        return $user->hasRole('Administrador')
            ? Tenant::query()->pluck('id')
            : $user->tenants()->pluck('tenants.id');
    }
}
