<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Activity extends Model
{
    protected $fillable = ['code', 'name', 'pptk_id', 'pagu_budget'];

    protected $casts = [
        'pagu_budget' => 'decimal:2',
    ];

    public function pptk(): BelongsTo
    {
        return $this->belongsTo(Pptk::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
