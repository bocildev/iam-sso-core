<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiKey extends Model
{
    protected $guarded = [];

    protected $hidden = ['key_hash'];

    public function application(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    protected function casts(): array
    {
        return ['scopes' => 'array', 'expires_at' => 'datetime', 'last_used_at' => 'datetime'];
    }
}

