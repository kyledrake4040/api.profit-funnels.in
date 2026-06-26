<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A GHL-style automation: when its trigger event fires within the account, the
 * engine runs the automation's ordered actions.
 */
final class Automation extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'account_id',
        'name',
        'trigger_event',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function actions(): HasMany
    {
        return $this->hasMany(AutomationAction::class)->orderBy('sort_order');
    }
}
