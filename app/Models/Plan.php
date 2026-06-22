<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

final class Plan extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'price',
        'currency',
        'interval',
        'features',
        'status',
    ];

    protected $casts = [
        'features' => 'array',
        'price'    => 'decimal:2',
    ];

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function isActive(): bool
    {
        return $this->status === config('custom.plan.status_active');
    }

    /**
     * The end of one billing period from the given start, based on the plan's
     * interval (yearly plans add a year; everything else a month).
     */
    public function periodEndFrom(Carbon $start): Carbon
    {
        return $this->interval === config('custom.plan.interval_yearly')
            ? $start->copy()->addYear()
            : $start->copy()->addMonth();
    }
}
