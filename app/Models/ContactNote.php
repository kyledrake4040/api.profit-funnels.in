<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A timestamped note on a contact — the simplest form of an activity log.
 * Notes are append-only from the UI (delete allowed but no edit, to keep the
 * log honest).
 */
final class ContactNote extends Model
{
    protected $fillable = [
        'contact_id',
        'user_id',
        'body',
    ];

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
