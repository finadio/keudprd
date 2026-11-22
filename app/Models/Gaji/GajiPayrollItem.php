<?php

namespace App\Models\Gaji;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GajiPayrollItem extends Model
{
    use HasFactory;

    protected $table = 'gaji_payroll_items';

    protected $fillable = [
        'period_id',
        'recipient_id',
        'category',
        'sub_category',
        'basic_salary_or_rep',
        'package_allowance',
        'position_allowance',
        'family_allowance',
        'rice_allowance',
        'akd_allowance',
        'tki_allowance',
        'housing_allowance',
        'transport_allowance',
        'reses_allowance',
        'tpp_amount',
        'productivity_pct',
        'discipline_pct',
        'other_allowance',
        'gross_amount',
        'deduction_iwp',
        'deduction_bpjs',
        'deduction_pph21',
        'deduction_other',
        'total_deductions',
        'net_amount',
        'calculation_meta',
    ];

    protected $casts = [
        'basic_salary_or_rep' => 'float',
        'package_allowance' => 'float',
        'position_allowance' => 'float',
        'family_allowance' => 'float',
        'rice_allowance' => 'float',
        'akd_allowance' => 'float',
        'tki_allowance' => 'float',
        'housing_allowance' => 'float',
        'transport_allowance' => 'float',
        'reses_allowance' => 'float',
        'tpp_amount' => 'float',
        'productivity_pct' => 'float',
        'discipline_pct' => 'float',
        'other_allowance' => 'float',
        'gross_amount' => 'float',
        'deduction_iwp' => 'float',
        'deduction_bpjs' => 'float',
        'deduction_pph21' => 'float',
        'deduction_other' => 'float',
        'total_deductions' => 'float',
        'net_amount' => 'float',
        'calculation_meta' => 'array',
    ];

    public function period(): BelongsTo
    {
        return $this->belongsTo(GajiPayrollPeriod::class, 'period_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(GajiRecipient::class, 'recipient_id');
    }
}
