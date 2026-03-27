<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseReturn extends Model
{
    use HasFactory;

    protected $fillable = [
        'return_no',
        'return_date',
        'supplier_id',
        'purchase_id',
        'subtotal',
        'overall_discount',
        'tax_total',
        'grand_total',
        'return_type',
        'voucher_id',
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
    ];

    public function supplier()
    {
        return $this->belongsTo(ProductSupplier::class, 'supplier_id');
    }

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_id');
    }

    public function items()
    {
        return $this->hasMany(PurchaseReturnItem::class);
    }

    public function voucher()
    {
        return $this->belongsTo(Voucher::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public static function generateReturnNo(): string
    {
        $prefix = 'PRN-';
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
