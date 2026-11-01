<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

#[Fillable(['name', 'email', 'password', 'status', 'is_super_admin', 'google_id', 'avatar_url', 'totp_secret_encrypted', 'totp_confirmed_at', 'recovery_code_hashes'])]
#[Hidden(['password', 'remember_token', 'totp_secret_encrypted', 'recovery_code_hashes'])]
class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $attributes = ['status' => 'active', 'is_super_admin' => false];

    protected static function booted(): void
    {
        static::creating(function (User $user) {
            $user->sub ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'totp_confirmed_at' => 'datetime',
            'password' => 'hashed',
            'is_super_admin' => 'boolean',
            'recovery_code_hashes' => 'array',
        ];
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function hasMfaEnabled(): bool
    {
        return ! empty($this->totp_secret_encrypted) && $this->totp_confirmed_at !== null;
    }

    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class);
    }

    public function applications(): BelongsToMany
    {
        return $this->belongsToMany(Application::class, 'user_applications');
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles')->withPivot('organization_id');
    }
}
