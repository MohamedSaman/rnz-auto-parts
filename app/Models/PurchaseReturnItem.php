<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseReturnItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_return_id',
        'purchase_order_item_id',
        'purchase_id',
        'product_id',
        'variant_id',
        'variant_value',
        'purchased_qty',
        'already_returned_qty',
        'balance_returnable_qty',
        'return_qty',
        'rate',
        'discount_amount',
        'tax_amount',
        'line_total',
    ];

    protected $casts = [
        'purchased_qty' => 'decimal:3',
        'already_returned_qty' => 'decimal:3',
        'balance_returnable_qty' => 'decimal:3',
        'return_qty' => 'decimal:3',
        'rate' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'line_total' => 'decimal:2',
    ];

    public function purchaseReturn()
    {
        return $this->belongsTo(PurchaseReturn::class);
    }

    public function purchaseOrderItem()
    {
        return $this->belongsTo(PurchaseOrderItem::class, 'purchase_order_item_id');
    }

    public function product()
    {
        return $this->belongsTo(ProductDetail::class);
    }
}
