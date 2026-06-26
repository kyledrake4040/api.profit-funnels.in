<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A client's published micro-site: a simple business website with a lead form
 * that drops new enquiries straight into the account's CRM. The wedge for new
 * businesses that don't have a website yet.
 */
final class Site extends Model
{
    use HasFactory;

    protected $fillable = [
        'account_id',
        'slug',
        'business_name',
        'headline',
        'about',
        'phone',
        'email',
        'city',
        'services',
        'theme_color',
        'published',
    ];

    protected $casts = [
        'services'  => 'array',
        'published' => 'boolean',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
