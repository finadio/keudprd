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

#[Fillable(['name', 'email', 'password', 'role', 'pptk_id'])]
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

    public function isPptk(): bool
    {
        return strtolower($this->role ?? '') === 'pptk';
    }

    public function isSuperAdmin(): bool
    {
        return in_array(strtolower($this->role ?? ''), ['superadmin', 'super admin', 'admin']);
    }

    public function isBendahara(): bool
    {
        return strtolower($this->role ?? '') === 'bendahara';
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
            return 'superadmin';
        }

        if (str_contains($email, 'bendahara') || str_contains($name, 'indah')) {
            return 'bendahara';
        }

        return 'operator';
    }

    public function getRoleLabelAttribute(): string
    {
        $r = strtolower($this->role ?? 'operator');

        return match ($r) {
            'superadmin', 'super admin', 'admin' => 'Super Admin',
            'bendahara' => 'Bendahara Pengeluaran',
            'pptk' => 'PPTK',
            'kasubag' => 'Kasubag',
            default => 'Operator Pembukuan',
        };
    }
}
