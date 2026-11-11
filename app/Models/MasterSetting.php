<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MasterSetting extends Model
{
    protected $fillable = [
        'fiscal_year',
        'skpd_name',
        'pa_kpa_name',
        'pa_kpa_nip',
        'treasurer_name',
        'treasurer_nip',
        'effective_start_date',
        'effective_end_date',
        'is_active',
    ];

    protected $casts = [
        'fiscal_year' => 'integer',
        'effective_start_date' => 'date',
        'effective_end_date' => 'date',
        'is_active' => 'boolean',
    ];

    public static function current(): ?self
    {
        return static::where('is_active', true)->latest()->first()
            ?? static::first();
    }
}
