<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Activity extends Model
{
    protected $fillable = [
        'code',
        'name',
        'program_code',
        'program_name',
        'pptk_id',
        'pagu_budget',
        'spj_ls_gaji',
        'spj_ls_barjas',
        'spj_up_gu_tu',
        'spj_sipd_total',
        'sisa_pagu_sipd',
        'period_year',
    ];

    protected $casts = [
        'pagu_budget' => 'decimal:2',
        'spj_ls_gaji' => 'decimal:2',
        'spj_ls_barjas' => 'decimal:2',
        'spj_up_gu_tu' => 'decimal:2',
        'spj_sipd_total' => 'decimal:2',
        'sisa_pagu_sipd' => 'decimal:2',
        'period_year' => 'integer',
    ];

    public function pptk(): BelongsTo
    {
        return $this->belongsTo(Pptk::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function realizations(): HasMany
    {
        return $this->hasMany(PaguRealization::class);
    }
}
