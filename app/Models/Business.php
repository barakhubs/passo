<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Business extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'user_id',
        'category',
        'phone',
        'country',
        'address',
        'logo',
        'description',
        'email',
        'website',
        'tagline'
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function categories()
    {
        return $this->hasMany(related: Category::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getLogoUrlAttribute()
    {
        if ($this->logo) {
            return Storage::url('businesses/' . $this->logo);
        }

        return null;
    }
}
