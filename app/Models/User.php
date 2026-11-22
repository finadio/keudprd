<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'username', 'email', 'password', 'role', 'pptk_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'pptk_id' => 'integer',
        ];
    }

    public function pptk(): BelongsTo
    {
        return $this->belongsTo(Pptk::class);
    }

    /**
     * Get user role attribute dynamically.
     */
    public function getRoleAttribute(): string
    {
        if (! empty($this->attributes['role'])) {
            return $this->attributes['role'];
        }

        $email = strtolower($this->email ?? '');
        $name = strtolower($this->name ?? '');

        if (str_contains($email, 'admin') || str_contains($name, 'admin') || str_contains($name, 'ning')) {
            return 'Super Admin';
        }

        if (str_contains($email, 'bendahara') || str_contains($name, 'indah')) {
            return 'Bendahara';
        }

        return 'Operator';
    }
}
