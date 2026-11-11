<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subunit extends Model
{
    protected $fillable = ['code', 'name', 'description'];

    public function pptks(): HasMany
    {
        return $this->hasMany(Pptk::class);
    }
}
