<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    protected $fillable = ['tenant_id', 'name', 'email', 'password', 'active', 'all_clients', 'analysis_visibility'];
    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'active' => 'boolean',
            'all_clients' => 'boolean',
        ];
    }

    public function companies()
    {
        return $this->belongsToMany(Company::class)->withPivot('is_default')->withTimestamps();
    }

    public function clients()
    {
        return $this->belongsToMany(Company::class, 'company_user', 'user_id', 'company_id')
            ->withPivot('is_default')->withTimestamps();
    }

    /** @deprecated A conta do usuário agora é singular; use tenant() ou account(). */
    public function tenants()
    {
        return $this->belongsToMany(Tenant::class)->withTimestamps();
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function account()
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }
}
