<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A deal moving through a pipeline. Scoped to an account, optionally tied to a
 * contact, always sitting in exactly one stage.
 */
final class Opportunity extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'opportunities';

    protected $fillable = [
        'account_id',
        'pipeline_id',
        'stage_id',
        'contact_id',
        'name',
        'value',
        'currency',
        'status',
        'expected_close_at',
    ];

    protected $casts = [
        'value'             => 'decimal:2',
        'expected_close_at' => 'date',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(Pipeline::class);
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(PipelineStage::class, 'stage_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
