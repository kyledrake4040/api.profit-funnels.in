<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'stripe_event_id',
        'type',
        'checkout_session_id',
        'payment_intent_id',
        'customer_email',
        'customer_name',
        'description',
        'amount',
        'currency',
        'status',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
        'amount' => 'integer',
    ];

    /**
     * Human readable amount, e.g. "$2,500.00 CAD".
     *
     * @return string
     */
    public function getFormattedAmountAttribute()
    {
        if ($this->amount === null) {
            return '';
        }

        return number_format($this->amount / 100, 2).' '.strtoupper((string) $this->currency);
    }
}
