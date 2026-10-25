<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class Application extends Model
{
    protected $guarded = [];

    protected $hidden = ['client_secret_hash', 'webhook_secret_encrypted'];

    protected function casts(): array
    {
        return [
            'redirect_uris' => 'array',
            'post_logout_redirect_uris' => 'array',
            'allowed_origins' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function ($m) {
            $m->uuid ??= (string) Str::uuid();
            $m->client_id ??= 'app_'.Str::lower(Str::random(24));
        });
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isPublicClient(): bool
    {
        return empty($this->client_secret_hash);
    }

    public function webhookSecret(): ?string
    {
        if (! $this->webhook_secret_encrypted) {
            return null;
        }
        try {
            return Crypt::decryptString($this->webhook_secret_encrypted);
        } catch (\Throwable) {
            return null;
        }
    }

    public function roles(): HasMany
    {
        return $this->hasMany(Role::class);
    }

    public function permissions(): HasMany
    {
        return $this->hasMany(Permission::class);
    }
}
