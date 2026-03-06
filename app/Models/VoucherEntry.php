<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VoucherEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'voucher_id',
        'account_id',
        'debit',
        'credit',
        'narration',
    ];

    protected $casts = [
        'debit' => 'decimal:2',
        'credit' => 'decimal:2',
    ];

    // ── Relationships ──

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    // ── Scopes ──

    public function scopeDebitEntries($query)
    {
        return $query->where('debit', '>', 0);
    }

    public function scopeCreditEntries($query)
    {
        return $query->where('credit', '>', 0);
    }

    public function scopeForAccount($query, int $accountId)
    {
        return $query->where('account_id', $accountId);
    }

    /**
     * Only entries from posted, non-deleted vouchers
     */
    public function scopePosted($query)
    {
        return $query->whereHas('voucher', function ($q) {
            $q->where('is_posted', true)->whereNull('deleted_at');
        });
    }
}
