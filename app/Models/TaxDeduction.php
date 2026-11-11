<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaxDeduction extends Model
{
    protected $fillable = [
        'transaction_id',
        'tax_type',
        'tax_rate',
        'taxable_base',
        'tax_amount',
        'ntpn_billing',
        'is_settled',
    ];

    protected $casts = [
        'tax_rate' => 'decimal:4',
        'taxable_base' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'is_settled' => 'boolean',
    ];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
