<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaguRealization extends Model
{
    use HasFactory;

    protected $fillable = [
        'period_year',
        'pptk_id',
        'activity_id',
        'transaction_date',
        'type', // 'pencairan' or 'pengembalian'
        'amount',
        'recipient',
        'description',
        'source_fund',
        'document_number',
        'is_spj_submitted',
        'spj_submission_date',
        'spj_document_number',
        'created_by',
        'notes',
    ];

    protected $casts = [
        'period_year' => 'integer',
        'transaction_date' => 'date:Y-m-d',
        'amount' => 'decimal:2',
        'is_spj_submitted' => 'boolean',
        'spj_submission_date' => 'date:Y-m-d',
    ];

    public function pptk(): BelongsTo
    {
        return $this->belongsTo(Pptk::class);
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
