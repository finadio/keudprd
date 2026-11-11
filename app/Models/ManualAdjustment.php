<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManualAdjustment extends Model
{
    protected $fillable = [
        'pptk_id',
        'adjustment_date',
        'period_month',
        'period_year',
        'adjustment_type',
        'amount',
        'reason',
        'authorized_by',
    ];

    protected $casts = [
        'adjustment_date' => 'date',
        'amount' => 'decimal:2',
        'period_month' => 'integer',
        'period_year' => 'integer',
    ];

    public function pptk(): BelongsTo
    {
        return $this->belongsTo(Pptk::class);
    }

    public function authorizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'authorized_by');
    }
}
