<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\User;

class TenantAccess
{
    public static function isPlatformAdmin(User $user): bool
    {
        return $user->tenant_id === null && $user->hasRole('Administrador');
    }

    public static function hasAllClients(User $user): bool
    {
        return self::isPlatformAdmin($user)
            || (bool) $user->all_clients
            || $user->hasRole('Administrador');
    }

    public static function ensurePlatformAdmin(User $user): void
    {
        abort_unless(self::isPlatformAdmin($user), 403, 'Apenas administradores da plataforma podem cadastrar contas assinantes.');
    }

    public static function ensure(User $user, string $tenantId): Tenant
    {
        $query = Tenant::query()->whereKey($tenantId);
        if (! self::isPlatformAdmin($user)) {
            $query->whereKey($user->tenant_id ?? '__sem-conta__');
        }

        return $query->firstOrFail();
    }

    public static function ids(User $user)
    {
        return self::isPlatformAdmin($user)
            ? Tenant::query()->pluck('id')
            : collect(array_filter([$user->tenant_id]));
    }

    public static function resolveTarget(User $actor, ?string $requestedTenantId): string
    {
        if (self::isPlatformAdmin($actor)) {
            abort_unless($requestedTenantId, 422, 'Selecione a conta assinante deste cadastro.');

            return (string) Tenant::query()->findOrFail($requestedTenantId)->id;
        }

        abort_unless($actor->tenant_id !== null, 403, 'O usuário não está vinculado a uma conta assinante.');
        abort_if($requestedTenantId && $requestedTenantId !== (string) $actor->tenant_id, 403, 'Você não pode cadastrar dados em outra conta assinante.');

        return (string) $actor->tenant_id;
    }
}
