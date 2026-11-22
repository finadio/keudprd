<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Pptk extends Model
{
    protected $fillable = [
        'code',
        'name',
        'display_name',
        'nip',
        'subunit_id',
        'sheet_name',
        'color_tag',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }

    public function subunit(): BelongsTo
    {
        return $this->belongsTo(Subunit::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function manualAdjustments(): HasMany
    {
        return $this->hasMany(ManualAdjustment::class);
    }

    public function paguRealizations(): HasMany
    {
        return $this->hasMany(PaguRealization::class);
    }
}
