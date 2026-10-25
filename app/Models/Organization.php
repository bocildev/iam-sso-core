<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Organization extends Model
{
    protected $guarded = [];

    protected static function booted(): void
    {
        static::creating(fn ($m) => $m->uuid ??= (string) Str::uuid());
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }
}
