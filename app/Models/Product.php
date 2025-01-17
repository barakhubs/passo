<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'category_id',
        'image',
        'description',
        'buying_price',
        'selling_price',
        'stock_quantity',
        'published'
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    // public function category()
    // {
    //     return $this->hasMany(Product::class);
    // }

    // public function children()
    // {
    //     return $this->hasMany(Category::class, 'parent_id');
    // }

    public function getImageUrlAttribute()
    {
        if ($this->image) {
            return Storage::url('products/' . $this->image);
        }

        return null;
    }
}
