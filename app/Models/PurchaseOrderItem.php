<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'product_id',
        'variant_id',
        'variant_value',
        'quantity',
        'received_quantity',
        'unit_price',
        'discount',
        'discount_type',
        'status',
    ];

    public function order()
    {
        return $this->belongsTo(PurchaseOrder::class, 'order_id');
    }

    public function product()
    {
        return $this->belongsTo(ProductDetail::class, 'product_id');
    }

    public function variant()
    {
        return $this->belongsTo(\App\Models\ProductVariant::class, 'variant_id');
    }
    public function detail()
    {
        return $this->hasOne(ProductDetail::class, 'code');
    }
}
