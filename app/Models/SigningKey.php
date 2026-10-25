<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SigningKey extends Model
{
    protected $guarded = [];

    protected $hidden = ['private_pem_encrypted'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
