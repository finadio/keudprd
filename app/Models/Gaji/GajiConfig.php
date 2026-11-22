<?php

namespace App\Models\Gaji;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GajiConfig extends Model
{
    use HasFactory;

    protected $table = 'gaji_configs';

    protected $fillable = [
        'config_key',
        'category',
        'name',
        'description',
        'config_value',
        'legal_reference',
        'is_active',
    ];

    protected $casts = [
        'config_value' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Helper to get configuration value by key.
     */
    public static function getValue(string $key, mixed $default = null): mixed
    {
        $config = static::where('config_key', $key)->where('is_active', true)->first();

        return $config ? $config->config_value : $default;
    }
}
