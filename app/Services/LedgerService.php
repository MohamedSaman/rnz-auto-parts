<?php

namespace App\Services;

use App\Models\Account;
use App\Models\VoucherEntry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * LedgerService — Account balance calculations and ledger queries.
 *
 * All balances are derived dynamically from voucher_entries + opening balances.
 * No stored balance columns — single source of truth is the ledger.
 */
class LedgerService
{
    /**
     * Get the current balance for a single account.
     *
     * Returns balance in the account's natural direction:
     *   - Debit-nature (Asset/Expense): positive = debit balance
     *   - Credit-nature (Liability/Income/Equity): positive = credit balance
     *
     * @param  int         $accountId
     * @param  string|null $asOfDate   Calculate balance up to this date
     * @param  int|null    $branchId   Filter by branch
     * @return float
     */
    public static function getAccountBalance(int $accountId, ?string $asOfDate = null, ?int $branchId = null): float
    {
        $account = Account::with('group')->findOrFail($accountId);

        $query = VoucherEntry::where('voucher_entries.account_id', $accountId)
            ->join('vouchers', 'vouchers.id', '=', 'voucher_entries.voucher_id')
            ->where('vouchers.is_posted', true)
            ->whereNull('vouchers.deleted_at');

        if ($asOfDate) {
            $query->where('vouchers.date', '<=', $asOfDate);
        }

        if ($branchId) {
            $query->where('vouchers.branch_id', $branchId);
        }

        $totals = $query->select(
            DB::raw('COALESCE(SUM(voucher_entries.debit), 0) as total_debit'),
            DB::raw('COALESCE(SUM(voucher_entries.credit), 0) as total_credit')
        )->first();

        $totalDebit = (float) ($totals->total_debit ?? 0) + (float) $account->opening_debit;
        $totalCredit = (float) ($totals->total_credit ?? 0) + (float) $account->opening_credit;

        // Return in natural direction
        if ($account->group && $account->group->nature === 'debit') {
            return $totalDebit - $totalCredit;
        }

        return $totalCredit - $totalDebit;
    }

    /**
     * Get customer balance (what the customer owes us).
     * Positive = customer owes us, Negative = we owe customer (overpaid).
     */
    public static function getCustomerBalance(int $customerId, ?string $asOfDate = null): float
    {
        $account = Account::where('reference_type', 'customer')
            ->where('reference_id', $customerId)
            ->first();

        if (!$account) return 0;

        return self::getAccountBalance($account->id, $asOfDate);
    }

    /**
     * Get supplier balance (what we owe the supplier).
     * Positive = we owe supplier, Negative = supplier owes us (overpaid).
     */
    public static function getSupplierBalance(int $supplierId, ?string $asOfDate = null): float
    {
        $account = Account::where('reference_type', 'supplier')
            ->where('reference_id', $supplierId)
            ->first();

        if (!$account) return 0;

        return self::getAccountBalance($account->id, $asOfDate);
    }

    /**
     * Get detailed ledger entries for an account with running balance.
     *
     * @return Collection of [date, voucher_no, voucher_type, narration, debit, credit, balance]
     */
    public static function getAccountLedger(
        int     $accountId,
        string  $fromDate,
        string  $toDate,
        ?int    $branchId = null
    ): array {
        $account = Account::with('group')->findOrFail($accountId);
        $isDebitNature = $account->group && $account->group->nature === 'debit';

        // ── Opening balance (everything before fromDate) ──
        $openingQuery = VoucherEntry::where('voucher_entries.account_id', $accountId)
            ->join('vouchers', 'vouchers.id', '=', 'voucher_entries.voucher_id')
            ->where('vouchers.is_posted', true)
            ->whereNull('vouchers.deleted_at')
            ->where('vouchers.date', '<', $fromDate);

        if ($branchId) {
            $openingQuery->where('vouchers.branch_id', $branchId);
        }

        $openingTotals = $openingQuery->select(
            DB::raw('COALESCE(SUM(voucher_entries.debit), 0) as total_debit'),
            DB::raw('COALESCE(SUM(voucher_entries.credit), 0) as total_credit')
        )->first();

        $openingDebit  = (float) ($openingTotals->total_debit ?? 0) + (float) $account->opening_debit;
        $openingCredit = (float) ($openingTotals->total_credit ?? 0) + (float) $account->opening_credit;

        $runningBalance = $isDebitNature
            ? ($openingDebit - $openingCredit)
            : ($openingCredit - $openingDebit);

        // ── Period entries ──
        $entriesQuery = VoucherEntry::where('voucher_entries.account_id', $accountId)
            ->join('vouchers', 'vouchers.id', '=', 'voucher_entries.voucher_id')
            ->where('vouchers.is_posted', true)
            ->whereNull('vouchers.deleted_at')
            ->whereBetween('vouchers.date', [$fromDate, $toDate]);

        if ($branchId) {
            $entriesQuery->where('vouchers.branch_id', $branchId);
        }

        $entries = $entriesQuery
            ->select(
                'vouchers.date',
                'vouchers.voucher_no',
                'vouchers.voucher_type',
                'vouchers.narration as voucher_narration',
                'voucher_entries.debit',
                'voucher_entries.credit',
                'voucher_entries.narration as entry_narration'
            )
            ->orderBy('vouchers.date')
            ->orderBy('vouchers.id')
            ->get();

        $ledger = [];

        // Opening balance row
        $ledger[] = [
            'date'         => $fromDate,
            'voucher_no'   => '',
            'voucher_type' => '',
            'narration'    => 'Opening Balance',
            'debit'        => $isDebitNature && $runningBalance > 0 ? $runningBalance : 0,
            'credit'       => !$isDebitNature && $runningBalance > 0 ? $runningBalance : 0,
            'balance'      => $runningBalance,
            'balance_type' => $runningBalance >= 0 ? ($isDebitNature ? 'Dr' : 'Cr') : ($isDebitNature ? 'Cr' : 'Dr'),
        ];

        foreach ($entries as $entry) {
            $debit  = (float) $entry->debit;
            $credit = (float) $entry->credit;

            if ($isDebitNature) {
                $runningBalance += ($debit - $credit);
            } else {
                $runningBalance += ($credit - $debit);
            }

            $ledger[] = [
                'date'         => $entry->date,
                'voucher_no'   => $entry->voucher_no,
                'voucher_type' => $entry->voucher_type,
                'narration'    => $entry->entry_narration ?? $entry->voucher_narration,
                'debit'        => $debit,
                'credit'       => $credit,
                'balance'      => abs($runningBalance),
                'balance_type' => $runningBalance >= 0 ? ($isDebitNature ? 'Dr' : 'Cr') : ($isDebitNature ? 'Cr' : 'Dr'),
            ];
        }

        return [
            'account'         => $account,
            'from_date'       => $fromDate,
            'to_date'         => $toDate,
            'opening_balance' => abs($openingDebit - $openingCredit),
            'opening_type'    => ($openingDebit >= $openingCredit) ? 'Dr' : 'Cr',
            'entries'         => $ledger,
            'closing_balance' => abs($runningBalance),
            'closing_type'    => $runningBalance >= 0 ? ($isDebitNature ? 'Dr' : 'Cr') : ($isDebitNature ? 'Cr' : 'Dr'),
            'total_debit'     => $entries->sum('debit'),
            'total_credit'    => $entries->sum('credit'),
        ];
    }

    /**
     * Get all outstanding customer balances.
     *
     * @return Collection of [customer_id, customer_name, balance]
     */
    public static function getOutstandingCustomerBalances(?string $asOfDate = null): Collection
    {
        $accounts = Account::with('group')
            ->where('reference_type', 'customer')
            ->where('is_active', true)
            ->get();

        return $accounts->map(function ($account) use ($asOfDate) {
            $balance = self::getAccountBalance($account->id, $asOfDate);
            return [
                'account_id'  => $account->id,
                'customer_id' => $account->reference_id,
                'name'        => $account->name,
                'balance'     => $balance,
                'type'        => $balance >= 0 ? 'Dr' : 'Cr',
            ];
        })->filter(fn($item) => abs($item['balance']) > 0.01)
            ->values();
    }

    /**
     * Get all outstanding supplier balances.
     */
    public static function getOutstandingSupplierBalances(?string $asOfDate = null): Collection
    {
        $accounts = Account::with('group')
            ->where('reference_type', 'supplier')
            ->where('is_active', true)
            ->get();

        return $accounts->map(function ($account) use ($asOfDate) {
            $balance = self::getAccountBalance($account->id, $asOfDate);
            return [
                'account_id'  => $account->id,
                'supplier_id' => $account->reference_id,
                'name'        => $account->name,
                'balance'     => $balance,
                'type'        => $balance >= 0 ? 'Cr' : 'Dr',
            ];
        })->filter(fn($item) => abs($item['balance']) > 0.01)
            ->values();
    }

    /**
     * Get the cash-in-hand balance from the ledger (replaces cash_in_hands table).
     */
    public static function getCashBalance(?string $asOfDate = null, ?int $branchId = null): float
    {
        $cashAccount = Account::where('code', 'CASH')->where('is_system', true)->first();
        if (!$cashAccount) return 0;

        return self::getAccountBalance($cashAccount->id, $asOfDate, $branchId);
    }

    /**
     * Get the bank balance from the ledger.
     */
    public static function getBankBalance(?string $asOfDate = null, ?int $branchId = null): float
    {
        $bankAccount = Account::where('code', 'BANK')->where('is_system', true)->first();
        if (!$bankAccount) return 0;

        return self::getAccountBalance($bankAccount->id, $asOfDate, $branchId);
    }

    /**
     * Get the inventory value from the ledger.
     */
    public static function getInventoryValue(?string $asOfDate = null, ?int $branchId = null): float
    {
        $invAccount = Account::where('code', 'INVENTORY')->where('is_system', true)->first();
        if (!$invAccount) return 0;

        return self::getAccountBalance($invAccount->id, $asOfDate, $branchId);
    }
}
