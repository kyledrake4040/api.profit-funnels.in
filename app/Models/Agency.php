<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A reseller. Owns its white-label branding and the client sub-accounts
 * (Accounts) it provisions. The top of the tenancy tree:
 * Agency → Account → Users.
 */
final class Agency extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'owner_id',
        'name',
        'slug',
        'brand_name',
        'custom_domain',
        'primary_color',
        'logo_url',
        'status',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }

    public function isActive(): bool
    {
        return $this->status === config('custom.agency.status_active');
    }
}
