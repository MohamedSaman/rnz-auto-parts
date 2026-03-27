<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalesReturn extends Model
{
    use HasFactory;

    protected $fillable = [
        'return_no',
        'return_date',
        'sale_id',
        'customer_id',
        'subtotal',
        'overall_discount',
        'tax_total',
        'grand_total',
        'refund_type',
        'cash_refund_amount',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'return_date' => 'date',
        'subtotal' => 'decimal:2',
        'overall_discount' => 'decimal:2',
        'tax_total' => 'decimal:2',
        'grand_total' => 'decimal:2',
        'cash_refund_amount' => 'decimal:2',
    ];

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function items()
    {
        return $this->hasMany(SalesReturnItem::class);
    }

    public static function generateReturnNo(): string
    {
        $prefix = 'SRN-';
        $date = now()->format('Ymd');

        $last = static::where('return_no', 'like', $prefix . $date . '-%')
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first();

        $next = 1;
        if ($last) {
            $parts = explode('-', (string) $last->return_no);
            $next = ((int) end($parts)) + 1;
        }

        return $prefix . $date . '-' . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
