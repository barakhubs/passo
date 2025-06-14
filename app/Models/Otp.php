<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Otp extends Model
{
    protected $fillable = [
        'phone',
        'email',
        'expires_at',
        'code',
        'is_expired',
    ];
}
