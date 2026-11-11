<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Transaction extends Model
{
    protected $fillable = [
        'batch_id',
        'transaction_date',
        'document_number',
        'description',
        'transaction_type',
        'payment_method',
        'amount_gross',
        'amount_tax',
        'amount_net',
        'flow_direction',
        'pptk_id',
        'activity_id',
        'vendor_name',
        'period_month',
        'period_year',
        'is_verified',
        'status',
        'notes',
        'meta',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'amount_gross' => 'decimal:2',
        'amount_tax' => 'decimal:2',
        'amount_net' => 'decimal:2',
        'period_month' => 'integer',
        'period_year' => 'integer',
        'is_verified' => 'boolean',
        'meta' => 'array',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class, 'batch_id');
    }

    public function pptk(): BelongsTo
    {
        return $this->belongsTo(Pptk::class);
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(TransactionAllocation::class);
    }

    public function taxDeductions(): HasMany
    {
        return $this->hasMany(TaxDeduction::class);
    }

    public function reconciliations(): HasMany
    {
        return $this->hasMany(Reconciliation::class, 'bank_transaction_id');
    }
}
