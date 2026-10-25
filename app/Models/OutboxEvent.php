<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class OutboxEvent extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'delivered_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (OutboxEvent $e) {
            $e->uuid ??= (string) Str::uuid();
        });
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }
}
