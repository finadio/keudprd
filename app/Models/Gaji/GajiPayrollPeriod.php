<?php

namespace App\Models\Gaji;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GajiPayrollPeriod extends Model
{
    use HasFactory;

    protected $table = 'gaji_payroll_periods';

    protected $fillable = [
        'period_year',
        'period_month',
        'period_title',
        'status',
        'total_recipients',
        'total_asn_gross',
        'total_dprd_gross',
        'total_gross',
        'total_deductions',
        'total_netto',
        'available_budget',
        'budget_difference',
        'is_budget_sufficient',
        'budget_diagnostic',
        'simulated_at',
        'paid_at',
        'created_by',
    ];

    protected $casts = [
        'period_year' => 'integer',
        'period_month' => 'integer',
        'total_recipients' => 'integer',
        'total_asn_gross' => 'float',
        'total_dprd_gross' => 'float',
        'total_gross' => 'float',
        'total_deductions' => 'float',
        'total_netto' => 'float',
        'available_budget' => 'float',
        'budget_difference' => 'float',
        'is_budget_sufficient' => 'boolean',
        'budget_diagnostic' => 'array',
        'simulated_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(GajiPayrollItem::class, 'period_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
