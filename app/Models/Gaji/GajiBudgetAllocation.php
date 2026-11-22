<?php

namespace App\Models\Gaji;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GajiBudgetAllocation extends Model
{
    use HasFactory;

    protected $table = 'gaji_budget_allocations';

    protected $fillable = [
        'fiscal_year',
        'category',
        'budget_name',
        'account_code',
        'annual_pagu',
        'monthly_allocation',
        'realized_amount',
        'notes',
    ];

    protected $casts = [
        'annual_pagu' => 'float',
        'monthly_allocation' => 'float',
        'realized_amount' => 'float',
    ];

    public function getRemainingAnnualAttribute(): float
    {
        return $this->annual_pagu - $this->realized_amount;
    }
}
