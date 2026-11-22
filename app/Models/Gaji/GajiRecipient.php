<?php

namespace App\Models\Gaji;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GajiRecipient extends Model
{
    use HasFactory;

    protected $table = 'gaji_recipients';

    protected $fillable = [
        'name',
        'identifier_number',
        'category',
        'sub_category',
        'position_name',
        'grade',
        'mkg_years',
        'mkg_months',
        'job_class',
        'political_party',
        'marital_status',
        'children_count',
        'akd_role',
        'akd_name',
        'has_official_residence',
        'has_official_car',
        'tpp_base_amount',
        'fixed_salary',
        'custom_allowances',
        'custom_deductions',
        'bank_account_number',
        'bank_name',
        'is_active',
    ];

    protected $casts = [
        'mkg_years' => 'integer',
        'mkg_months' => 'integer',
        'job_class' => 'integer',
        'children_count' => 'integer',
        'has_official_residence' => 'boolean',
        'has_official_car' => 'boolean',
        'tpp_base_amount' => 'float',
        'fixed_salary' => 'float',
        'custom_allowances' => 'array',
        'custom_deductions' => 'array',
        'is_active' => 'boolean',
    ];

    public function payrollItems(): HasMany
    {
        return $this->hasMany(GajiPayrollItem::class, 'recipient_id');
    }

    public function getSubCategoryLabelAttribute(): string
    {
        return match ($this->sub_category) {
            'pns' => 'PNS',
            'pppk_penuh_waktu' => 'PPPK Penuh Waktu',
            'pppk_paruh_waktu' => 'PPPK Paruh Waktu',
            'ketua_dprd' => 'Ketua DPRD',
            'wakil_ketua_dprd' => 'Wakil Ketua DPRD',
            'anggota_dprd' => 'Anggota DPRD',
            default => strtoupper(str_replace('_', ' ', $this->sub_category)),
        };
    }
}
