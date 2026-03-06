<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class Account extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'group_id',
        'parent_account_id',
        'is_system',
        'reference_type',    // 'customer' or 'supplier'
        'reference_id',
        'opening_debit',
        'opening_credit',
        'branch_id',
        'is_active',
    ];

    protected $casts = [
        'is_system' => 'boolean',
        'is_active' => 'boolean',
        'opening_debit' => 'decimal:2',
        'opening_credit' => 'decimal:2',
    ];

    // ── Relationships ──

    public function group(): BelongsTo
    {
        return $this->belongsTo(AccountGroup::class, 'group_id');
    }

    public function parentAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'parent_account_id');
    }

    public function childAccounts(): HasMany
    {
        return $this->hasMany(Account::class, 'parent_account_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function voucherEntries(): HasMany
    {
        return $this->hasMany(VoucherEntry::class);
    }

    /**
     * Get the referenced customer/supplier model
     */
    public function reference()
    {
        if ($this->reference_type === 'customer') {
            return $this->belongsTo(Customer::class, 'reference_id');
        }
        if ($this->reference_type === 'supplier') {
            return $this->belongsTo(ProductSupplier::class, 'reference_id');
        }
        return null;
    }

    // ── Scopes ──

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeSystem($query)
    {
        return $query->where('is_system', true);
    }

    public function scopeByCode($query, string $code)
    {
        return $query->where('code', $code);
    }

    public function scopeCustomerAccounts($query)
    {
        return $query->where('reference_type', 'customer');
    }

    public function scopeSupplierAccounts($query)
    {
        return $query->where('reference_type', 'supplier');
    }

    public function scopeForBranch($query, ?int $branchId)
    {
        if ($branchId) {
            return $query->where(function ($q) use ($branchId) {
                $q->where('branch_id', $branchId)->orWhereNull('branch_id');
            });
        }
        return $query;
    }

    // ── Balance Calculations ──

    /**
     * Get current balance (opening + all transactions).
     * For debit-nature accounts: positive = debit balance
     * For credit-nature accounts: positive = credit balance
     */
    public function getCurrentBalance(?string $asOfDate = null): float
    {
        $query = VoucherEntry::where('account_id', $this->id)
            ->join('vouchers', 'vouchers.id', '=', 'voucher_entries.voucher_id')
            ->where('vouchers.is_posted', true)
            ->whereNull('vouchers.deleted_at');

        if ($asOfDate) {
            $query->where('vouchers.date', '<=', $asOfDate);
        }

        $totals = $query->select(
            DB::raw('COALESCE(SUM(voucher_entries.debit), 0) as total_debit'),
            DB::raw('COALESCE(SUM(voucher_entries.credit), 0) as total_credit')
        )->first();

        $totalDebit = ($totals->total_debit ?? 0) + $this->opening_debit;
        $totalCredit = ($totals->total_credit ?? 0) + $this->opening_credit;

        // Return in the natural direction of the account
        if ($this->group && $this->group->nature === 'debit') {
            return $totalDebit - $totalCredit; // Positive = normal for assets/expenses
        }

        return $totalCredit - $totalDebit; // Positive = normal for liabilities/income/equity
    }

    /**
     * Get debit total for period
     */
    public function getDebitTotal(?string $fromDate = null, ?string $toDate = null): float
    {
        $query = VoucherEntry::where('account_id', $this->id)
            ->join('vouchers', 'vouchers.id', '=', 'voucher_entries.voucher_id')
            ->where('vouchers.is_posted', true)
            ->whereNull('vouchers.deleted_at');

        if ($fromDate) $query->where('vouchers.date', '>=', $fromDate);
        if ($toDate)   $query->where('vouchers.date', '<=', $toDate);

        return (float) $query->sum('voucher_entries.debit');
    }

    /**
     * Get credit total for period
     */
    public function getCreditTotal(?string $fromDate = null, ?string $toDate = null): float
    {
        $query = VoucherEntry::where('account_id', $this->id)
            ->join('vouchers', 'vouchers.id', '=', 'voucher_entries.voucher_id')
            ->where('vouchers.is_posted', true)
            ->whereNull('vouchers.deleted_at');

        if ($fromDate) $query->where('vouchers.date', '>=', $fromDate);
        if ($toDate)   $query->where('vouchers.date', '<=', $toDate);

        return (float) $query->sum('voucher_entries.credit');
    }

    // ── Static Helpers ──

    /**
     * Find system account by code
     */
    public static function findByCode(string $code): ?self
    {
        return static::where('code', $code)->first();
    }

    /**
     * Get or create a customer's ledger account
     */
    public static function getOrCreateForCustomer(Customer $customer): self
    {
        $existing = static::where('reference_type', 'customer')
            ->where('reference_id', $customer->id)
            ->first();

        if ($existing) return $existing;

        // Find the Accounts Receivable parent account
        $arAccount = static::findByCode('AR');

        return static::create([
            'code' => 'CUST-' . str_pad($customer->id, 5, '0', STR_PAD_LEFT),
            'name' => $customer->business_name ?: $customer->name,
            'group_id' => $arAccount->group_id,
            'parent_account_id' => $arAccount->id,
            'is_system' => false,
            'reference_type' => 'customer',
            'reference_id' => $customer->id,
            'opening_debit' => $customer->opening_balance ?? 0,
            'opening_credit' => 0,
            'is_active' => true,
        ]);
    }

    /**
     * Get or create a supplier's ledger account
     */
    public static function getOrCreateForSupplier(ProductSupplier $supplier): self
    {
        $existing = static::where('reference_type', 'supplier')
            ->where('reference_id', $supplier->id)
            ->first();

        if ($existing) return $existing;

        // Find the Accounts Payable parent account
        $apAccount = static::findByCode('AP');

        return static::create([
            'code' => 'SUPP-' . str_pad($supplier->id, 5, '0', STR_PAD_LEFT),
            'name' => $supplier->businessname ?: $supplier->name,
            'group_id' => $apAccount->group_id,
            'parent_account_id' => $apAccount->id,
            'is_system' => false,
            'reference_type' => 'supplier',
            'reference_id' => $supplier->id,
            'opening_debit' => 0,
            'opening_credit' => $supplier->overpayment ?? 0,
            'is_active' => true,
        ]);
    }
}
