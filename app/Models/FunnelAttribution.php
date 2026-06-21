<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Row in the funnel_attribution table. The table tracks created_at only (no
 * updated_at), so automatic timestamping is disabled.
 *
 * @property int         $id
 * @property string      $post_id
 * @property string|null $platform
 * @property string      $utm_source
 * @property string|null $utm_medium
 * @property string|null $utm_campaign
 * @property string|null $lead_id
 * @property int|null    $revenue_cents
 */
class FunnelAttribution extends Model
{
    protected $table = 'funnel_attribution';

    public $timestamps = false;

    protected $fillable = [
        'post_id',
        'platform',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'lead_id',
        'revenue_cents',
        'created_at',
    ];

    protected $casts = [
        'revenue_cents' => 'integer',
        'created_at' => 'datetime',
    ];
}
