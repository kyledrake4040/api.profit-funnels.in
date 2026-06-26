<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A field-service work order (the Jobber side of the platform): a scheduled,
 * billable job for a client, optionally tied to a CRM contact.
 *
 * Named ServiceJob / service_jobs to avoid colliding with Laravel's queue jobs.
 */
final class ServiceJob extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'service_jobs';

    protected $fillable = [
        'account_id',
        'contact_id',
        'title',
        'description',
        'status',
        'scheduled_at',
        'completed_at',
        'value',
        'currency',
        'address',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'completed_at' => 'datetime',
        'value'        => 'decimal:2',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function isComplete(): bool
    {
        return $this->status === config('custom.job.status_completed');
    }
}
