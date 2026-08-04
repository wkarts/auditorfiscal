<?php

namespace App\Services;

use App\Models\AnalysisBatch;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class AnalysisAccess
{
    public static function canViewAll(User $user): bool
    {
        return TenantAccess::isPlatformAdmin($user)
            || $user->hasRole('Administrador')
            || $user->analysis_visibility === 'all';
    }

    public static function query(User $user): Builder
    {
        $query = AnalysisBatch::query()->whereIn('company_id', CompanyAccess::ids($user));
        if (! self::canViewAll($user)) {
            $query->where('created_by', $user->id);
        }

        return $query;
    }

    public static function ensure(User $user, AnalysisBatch $batch): AnalysisBatch
    {
        CompanyAccess::ensure($user, $batch->company_id);
        abort_unless(self::canViewAll($user) || (int) $batch->created_by === (int) $user->id, 404);

        return $batch;
    }

    public static function canAssignAll(User $user): bool
    {
        return TenantAccess::isPlatformAdmin($user) || $user->hasRole('Administrador');
    }
}
