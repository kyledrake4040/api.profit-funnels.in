<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A client invoice, made of line items. Closes the lead → job → get-paid loop.
 */
final class Invoice extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'account_id',
        'contact_id',
        'quote_id',
        'number',
        'status',
        'currency',
        'total',
        'due_at',
        'paid_at',
        'notes',
    ];

    protected $casts = [
        'total'   => 'decimal:2',
        'due_at'  => 'date',
        'paid_at' => 'datetime',
    ];

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
        return $this->hasMany(InvoiceItem::class);
    }

    public function recalculateTotal(): void
    {
        $this->total = $this->items()->get()->sum(fn (InvoiceItem $i) => (float) $i->quantity * (float) $i->unit_price);
        $this->save();
    }

    public function isPaid(): bool
    {
        return $this->status === config('custom.invoice.status_paid');
    }
}
