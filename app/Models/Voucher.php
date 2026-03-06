<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Voucher extends Model
{
    use HasFactory, SoftDeletes;

    // ── Voucher Type Constants ──
    const TYPE_SALES    = 'sales';
    const TYPE_PURCHASE = 'purchase';
    const TYPE_PAYMENT  = 'payment';   // Outgoing (to supplier)
    const TYPE_RECEIPT  = 'receipt';    // Incoming (from customer)
    const TYPE_JOURNAL  = 'journal';
    const TYPE_EXPENSE  = 'expense';
    const TYPE_CONTRA   = 'contra';    // Cash ↔ Bank

    // ── Voucher Number Prefixes ──
    const PREFIXES = [
        'sales'    => 'SV',
        'purchase' => 'PV',
        'payment'  => 'PMT',
        'receipt'  => 'RCV',
        'journal'  => 'JV',
        'expense'  => 'EXP',
        'contra'   => 'CTR',
    ];

    protected $fillable = [
        'voucher_no',
        'voucher_type',
        'date',
        'narration',
        'reference_type',
        'reference_id',
        'total_amount',
        'branch_id',
        'is_posted',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'date' => 'date',
        'total_amount' => 'decimal:2',
        'is_posted' => 'boolean',
    ];

    // ── Relationships ──

    public function entries(): HasMany
    {
        return $this->hasMany(VoucherEntry::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // ── Scopes ──

    public function scopePosted($query)
    {
        return $query->where('is_posted', true);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('voucher_type', $type);
    }

    public function scopeForDate($query, string $date)
    {
        return $query->whereDate('date', $date);
    }

    public function scopeDateRange($query, string $from, string $to)
    {
        return $query->whereBetween('date', [$from, $to]);
    }

    public function scopeForBranch($query, ?int $branchId)
    {
        if ($branchId) {
            return $query->where('branch_id', $branchId);
        }
        return $query;
    }

    // ── Helpers ──

    /**
     * Generate the next voucher number for a given type.
     * Format: PREFIX-YYYY-NNNNN  (e.g. SV-2026-00001)
     */
    public static function generateVoucherNo(string $type): string
    {
        $prefix = self::PREFIXES[$type] ?? 'GEN';
        $year = now()->format('Y');
        $pattern = $prefix . '-' . $year . '-';

        $last = static::withTrashed()
            ->where('voucher_no', 'like', $pattern . '%')
            ->orderByRaw('CAST(SUBSTRING(voucher_no, ' . (strlen($pattern) + 1) . ') AS UNSIGNED) DESC')
            ->value('voucher_no');

        if ($last) {
            $lastNumber = (int) substr($last, strlen($pattern));
            $next = $lastNumber + 1;
        } else {
            $next = 1;
        }

        return $prefix . '-' . $year . '-' . str_pad($next, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Check if voucher entries are balanced
     */
    public function isBalanced(): bool
    {
        $totals = $this->entries()
            ->selectRaw('SUM(debit) as total_debit, SUM(credit) as total_credit')
            ->first();

        return round((float) $totals->total_debit, 2) === round((float) $totals->total_credit, 2);
    }

    /**
     * Get total debit of all entries
     */
    public function getTotalDebit(): float
    {
        return (float) $this->entries()->sum('debit');
    }

    /**
     * Get total credit of all entries
     */
    public function getTotalCredit(): float
    {
        return (float) $this->entries()->sum('credit');
    }
}
