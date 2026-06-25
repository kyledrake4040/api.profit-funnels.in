<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A client workspace (sub-account) under an Agency. Every piece of product data
 * — funnels, contacts, campaigns, automations — is scoped to an Account.
 */
final class Account extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'agency_id',
        'name',
        'slug',
        'status',
    ];

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    /**
     * Users with access to this account, each carrying a role
     * (Owner / Admin / User) on the membership pivot.
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'account_user')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function isActive(): bool
    {
        return $this->status === config('custom.account.status_active');
    }
}
