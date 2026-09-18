<?php

namespace App\Models;

use App\Services\EmailCodeService;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'phone', 'password', 'password_recovery_phrase', 'email_verified_at', 'role', 'permissions', 'active'])]
#[Hidden(['password', 'password_recovery_phrase', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
class User extends Authenticatable implements MustVerifyEmail
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
            'permissions' => 'array',
            'active' => 'boolean',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
            'two_factor_last_used_step' => 'integer',
        ];
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    public function hasTwoFactorAuthentication(): bool
    {
        return filled($this->two_factor_secret) && $this->two_factor_confirmed_at !== null;
    }

    public function canAccessModule(string $module): bool
    {
        if ($this->role === 'customer') {
            return false;
        }

        return $this->isSuperAdmin() || in_array($module, $this->permissions ?? [], true);
    }

    public function canManageManualPayments(): bool
    {
        return $this->active && ($this->isSuperAdmin() || ($this->role === 'admin' && $this->canAccessModule('paiements')));
    }

    public function sendEmailVerificationNotification(): void
    {
        app(EmailCodeService::class)->send($this);
    }
}
