<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaleItem extends Model
{
    protected $fillable = [
        'sale_id',
        'product_id',
        'quantity',
        'unit_price',
        'total',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }
}
