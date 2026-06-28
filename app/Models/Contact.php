<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A CRM contact (lead/customer) belonging to a single account. The core record
 * the rest of the CRM — pipelines, conversations, automations — hangs off.
 */
final class Contact extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'account_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'company',
        'status',
        'source',
        'tags',
        'custom_fields',
        'notes',
    ];

    protected $casts = [
        'tags'          => 'array',
        'custom_fields' => 'array',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Timestamped activity notes — the structured log.
     * Named `contactNotes` to avoid collision with the legacy `notes` text column.
     */
    public function contactNotes(): HasMany
    {
        return $this->hasMany(ContactNote::class);
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name . ' ' . (string) $this->last_name);
    }
}
