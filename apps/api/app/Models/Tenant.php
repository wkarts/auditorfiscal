<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Tenant extends Model
{
    use HasUuids;

    protected $fillable = ['legal_name', 'trade_name', 'tax_id', 'email', 'phone', 'active', 'settings'];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'settings' => 'array'];
    }

    public function companies() { return $this->hasMany(Company::class); }
    public function users() { return $this->hasMany(User::class); }

    /** @deprecated Relação anterior à vinculação singular de usuários por conta. */
    public function legacyUsers() { return $this->belongsToMany(User::class, 'tenant_user')->withTimestamps(); }
}
