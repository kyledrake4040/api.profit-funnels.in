<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable, HasApiTokens, SoftDeletes, HasRoles;

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function funnels(): HasMany
    {
        return $this->hasMany(Funnel::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Agencies this user owns (i.e. is the reseller of).
     */
    public function ownedAgencies(): HasMany
    {
        return $this->hasMany(Agency::class, 'owner_id');
    }

    /**
     * Sub-accounts this user is a member of, with their role on each.
     */
    public function accounts(): BelongsToMany
    {
        return $this->belongsToMany(Account::class, 'account_user')
            ->withPivot('role')
            ->withTimestamps();
    }
}
