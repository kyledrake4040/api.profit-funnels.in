<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * A price quote for a client, made of line items. Once accepted it can be
 * converted into an invoice.
 */
final class Quote extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'account_id',
        'contact_id',
        'job_id',
        'number',
        'accept_token',
        'status',
        'currency',
        'total',
        'notes',
    ];

    protected $casts = [
        'total' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        // Every quote gets an unguessable public token so the business can share
        // a client-facing accept link without exposing the internal quote ID.
        static::creating(function (Quote $quote) {
            if (empty($quote->accept_token)) {
                $quote->accept_token = Str::random(48);
            }
        });
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuoteItem::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function isAccepted(): bool
    {
        return $this->status === config('custom.quote.status_accepted');
    }

    public function publicUrl(): string
    {
        return route('quote.show', $this->accept_token);
    }

    /**
     * Recalculate and persist the total from the current line items.
     */
    public function recalculateTotal(): void
    {
        $this->total = $this->items()->get()->sum(fn (QuoteItem $i) => (float) $i->quantity * (float) $i->unit_price);
        $this->save();
    }
}
