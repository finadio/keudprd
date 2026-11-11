<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reconciliation extends Model
{
    protected $fillable = [
        'bank_transaction_id',
        'bku_transaction_id',
        'reconciliation_status',
        'discrepancy_amount',
        'checked_by',
        'checked_at',
        'resolution_notes',
    ];

    protected $casts = [
        'discrepancy_amount' => 'decimal:2',
        'checked_at' => 'datetime',
    ];

    public function bankTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'bank_transaction_id');
    }

    public function bkuTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'bku_transaction_id');
    }

    public function checker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_by');
    }
}
