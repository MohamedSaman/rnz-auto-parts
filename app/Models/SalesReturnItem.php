<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalesReturnItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'sales_return_id',
        'sale_item_id',
        'sale_id',
        'product_id',
        'variant_id',
        'variant_value',
        'sold_qty',
        'already_returned_qty',
        'balance_returnable_qty',
        'return_qty',
        'rate',
        'discount_amount',
        'tax_amount',
        'line_total',
    ];

    protected $casts = [
        'sold_qty' => 'decimal:3',
        'already_returned_qty' => 'decimal:3',
        'balance_returnable_qty' => 'decimal:3',
        'return_qty' => 'decimal:3',
        'rate' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'line_total' => 'decimal:2',
    ];

    public function salesReturn()
    {
        return $this->belongsTo(SalesReturn::class);
    }

    public function saleItem()
    {
        return $this->belongsTo(SaleItem::class);
    }

    public function product()
    {
        return $this->belongsTo(ProductDetail::class);
    }
}
