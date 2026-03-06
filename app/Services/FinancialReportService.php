<?php

namespace App\Services;

use App\Models\Account;
use App\Models\AccountGroup;
use App\Models\Voucher;
use App\Models\VoucherEntry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * FinancialReportService — Trial Balance, P&L, Balance Sheet, Day Book.
 *
 * All reports are computed dynamically from voucher_entries.
 * No stored/cached totals — always produces real-time accurate data.
 *
 * ────────────────────────────────────────────────────────────────
 * CALCULATION LOGIC OVERVIEW
 * ────────────────────────────────────────────────────────────────
 *
 * Trial Balance:
 *   For each account:
 *     total_debit  = opening_debit  + SUM(voucher_entries.debit)  up to date
 *     total_credit = opening_credit + SUM(voucher_entries.credit) up to date
 *     Net = total_debit - total_credit
 *     If Net > 0 → debit balance; If Net < 0 → credit balance
 *     Verify: SUM(all debit balances) = SUM(all credit balances)
 *
 * Profit & Loss (for period fromDate to toDate):
 *   Revenue   = Credits on Income accounts - Debits on Income accounts
 *   COGS      = Debits on COGS account    - Credits on COGS account
 *   Gross Profit = Revenue - COGS
 *   Expenses  = Debits on Expense accounts - Credits on Expense accounts (excl COGS)
 *   Net Profit = Gross Profit - Expenses
 *
 * Balance Sheet (as of date):
 *   Assets      = SUM of debit-nature account balances (net debit)
 *   Liabilities = SUM of credit-nature account balances (net credit) [type=liability]
 *   Equity      = SUM of equity accounts + Retained Earnings
 *   Retained Earnings = cumulative P&L from start up to asOfDate
 *   Verify: Assets = Liabilities + Equity
 *
 * Day Book:
 *   All vouchers for a date with their debit/credit entries
 *
 * Ledger Report:
 *   Delegated to LedgerService::getAccountLedger()
 */
class FinancialReportService
{
    // ────────────────────────────────────────────────────────────
    //  TRIAL BALANCE
    // ────────────────────────────────────────────────────────────

    /**
     * Generate Trial Balance as of a given date.
     *
     * @param  string|null $asOfDate  Defaults to today
     * @param  int|null    $branchId  Filter by branch
     * @return array {
     *   accounts: [ { id, code, name, group, debit_balance, credit_balance } ],
     *   total_debit: float,
     *   total_credit: float,
     *   is_balanced: bool
     * }
     */
    public static function getTrialBalance(?string $asOfDate = null, ?int $branchId = null): array
    {
        $asOfDate = $asOfDate ?? now()->toDateString();

        $accounts = Account::with('group')
            ->where('is_active', true)
            ->forBranch($branchId)
            ->get();

        $trialBalance = [];
        $totalDebit  = 0;
        $totalCredit = 0;

        foreach ($accounts as $account) {
            // Get transaction totals
            $query = VoucherEntry::where('voucher_entries.account_id', $account->id)
                ->join('vouchers', 'vouchers.id', '=', 'voucher_entries.voucher_id')
                ->where('vouchers.is_posted', true)
                ->whereNull('vouchers.deleted_at')
                ->where('vouchers.date', '<=', $asOfDate);

            if ($branchId) {
                $query->where('vouchers.branch_id', $branchId);
            }

            $totals = $query->select(
                DB::raw('COALESCE(SUM(voucher_entries.debit), 0) as sum_debit'),
                DB::raw('COALESCE(SUM(voucher_entries.credit), 0) as sum_credit')
            )->first();

            $debit  = (float) ($totals->sum_debit ?? 0) + (float) $account->opening_debit;
            $credit = (float) ($totals->sum_credit ?? 0) + (float) $account->opening_credit;
            $net    = $debit - $credit;

            // Skip zero-balance accounts
            if (abs($net) < 0.01) continue;

            $debitBalance  = $net > 0 ? round($net, 2) : 0;
            $creditBalance = $net < 0 ? round(abs($net), 2) : 0;

            $trialBalance[] = [
                'id'             => $account->id,
                'code'           => $account->code,
                'name'           => $account->name,
                'group'          => $account->group->name ?? '',
                'group_type'     => $account->group->type ?? '',
                'debit_balance'  => $debitBalance,
                'credit_balance' => $creditBalance,
            ];

            $totalDebit  += $debitBalance;
            $totalCredit += $creditBalance;
        }

        return [
            'as_of_date'   => $asOfDate,
            'accounts'     => $trialBalance,
            'total_debit'  => round($totalDebit, 2),
            'total_credit' => round($totalCredit, 2),
            'is_balanced'  => round($totalDebit, 2) === round($totalCredit, 2),
        ];
    }

    // ────────────────────────────────────────────────────────────
    //  PROFIT & LOSS
    // ────────────────────────────────────────────────────────────

    /**
     * Generate Profit & Loss statement for a period.
     *
     * @return array {
     *   revenue: { items[], total },
     *   cogs: { items[], total },
     *   gross_profit: float,
     *   expenses: { items[], total },
     *   net_profit: float,
     *   period: { from, to }
     * }
     */
    public static function getProfitAndLoss(string $fromDate, string $toDate, ?int $branchId = null): array
    {
        // ── Revenue (Income accounts) ──
        $incomeGroups = AccountGroup::where('type', 'income')->pluck('id')->toArray();
        $incomeGroupIds = self::getAllChildGroupIds($incomeGroups);

        $revenueItems = self::getGroupBalancesForPeriod($incomeGroupIds, $fromDate, $toDate, $branchId);
        $totalRevenue = collect($revenueItems)->sum('amount');

        // ── COGS (specific system account) ──
        $cogsAccount = Account::where('code', 'COGS')->first();
        $cogsAmount = 0;
        $cogsItems = [];

        if ($cogsAccount) {
            $cogsAmount = self::getAccountPeriodBalance($cogsAccount->id, $fromDate, $toDate, $branchId);
            $cogsItems[] = ['name' => 'Cost of Goods Sold', 'amount' => $cogsAmount];
        }

        // Purchase returns reduce COGS
        $purchRetAccount = Account::where('code', 'PURCH-RET')->first();
        if ($purchRetAccount) {
            $purchRetAmount = self::getAccountPeriodBalance($purchRetAccount->id, $fromDate, $toDate, $branchId);
            if ($purchRetAmount != 0) {
                $cogsItems[] = ['name' => 'Less: Purchase Returns', 'amount' => -$purchRetAmount];
                $cogsAmount -= $purchRetAmount;
            }
        }

        $grossProfit = $totalRevenue - $cogsAmount;

        // ── Operating Expenses (Expense accounts, excluding COGS & Purchase Returns) ──
        $expenseGroups = AccountGroup::where('type', 'expense')->pluck('id')->toArray();
        $expenseGroupIds = self::getAllChildGroupIds($expenseGroups);

        $excludeAccountIds = collect([$cogsAccount?->id, $purchRetAccount?->id])->filter()->toArray();
        $expenseItems = self::getGroupBalancesForPeriod($expenseGroupIds, $fromDate, $toDate, $branchId, $excludeAccountIds);
        $totalExpenses = collect($expenseItems)->sum('amount');

        $netProfit = $grossProfit - $totalExpenses;

        return [
            'period' => [
                'from' => $fromDate,
                'to'   => $toDate,
            ],
            'revenue' => [
                'items' => $revenueItems,
                'total' => round($totalRevenue, 2),
            ],
            'cogs' => [
                'items' => $cogsItems,
                'total' => round($cogsAmount, 2),
            ],
            'gross_profit' => round($grossProfit, 2),
            'expenses' => [
                'items' => $expenseItems,
                'total' => round($totalExpenses, 2),
            ],
            'net_profit' => round($netProfit, 2),
        ];
    }

    // ────────────────────────────────────────────────────────────
    //  BALANCE SHEET
    // ────────────────────────────────────────────────────────────

    /**
     * Generate Balance Sheet as of a date.
     *
     * @return array {
     *   assets: { groups[], total },
     *   liabilities: { groups[], total },
     *   equity: { items[], retained_earnings, total },
     *   is_balanced: bool
     * }
     */
    public static function getBalanceSheet(?string $asOfDate = null, ?int $branchId = null): array
    {
        $asOfDate = $asOfDate ?? now()->toDateString();

        // ── Assets ──
        $assetGroups = AccountGroup::where('type', 'asset')->whereNull('parent_id')->with('childrenRecursive')->get();
        $assetsData = self::getGroupedBalances($assetGroups, $asOfDate, $branchId);
        $totalAssets = collect($assetsData)->sum('total');

        // ── Liabilities ──
        $liabilityGroups = AccountGroup::where('type', 'liability')->whereNull('parent_id')->with('childrenRecursive')->get();
        $liabilitiesData = self::getGroupedBalances($liabilityGroups, $asOfDate, $branchId);
        $totalLiabilities = collect($liabilitiesData)->sum('total');

        // ── Equity ──
        $equityGroups = AccountGroup::where('type', 'equity')->whereNull('parent_id')->with('childrenRecursive')->get();
        $equityData = self::getGroupedBalances($equityGroups, $asOfDate, $branchId);
        $equityAccountsTotal = collect($equityData)->sum('total');

        // Retained Earnings = cumulative P&L from the beginning to asOfDate
        // Using a far-back start date to capture all history
        $retainedEarnings = self::getRetainedEarnings($asOfDate, $branchId);

        $totalEquity = $equityAccountsTotal + $retainedEarnings;

        return [
            'as_of_date' => $asOfDate,
            'assets' => [
                'groups' => $assetsData,
                'total'  => round($totalAssets, 2),
            ],
            'liabilities' => [
                'groups' => $liabilitiesData,
                'total'  => round($totalLiabilities, 2),
            ],
            'equity' => [
                'groups'             => $equityData,
                'retained_earnings'  => round($retainedEarnings, 2),
                'total'              => round($totalEquity, 2),
            ],
            'is_balanced' => round($totalAssets, 2) === round($totalLiabilities + $totalEquity, 2),
        ];
    }

    // ────────────────────────────────────────────────────────────
    //  DAY BOOK
    // ────────────────────────────────────────────────────────────

    /**
     * Get all vouchers for a specific date with entries.
     *
     * @return array {
     *   date: string,
     *   vouchers: [ { voucher_no, type, narration, total, entries[] } ],
     *   total_debit: float,
     *   total_credit: float
     * }
     */
    public static function getDayBook(string $date, ?int $branchId = null): array
    {
        $query = Voucher::with(['entries.account', 'creator'])
            ->where('is_posted', true)
            ->whereDate('date', $date);

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        $vouchers = $query->orderBy('id')->get();

        $totalDebit  = 0;
        $totalCredit = 0;

        $voucherData = $vouchers->map(function ($voucher) use (&$totalDebit, &$totalCredit) {
            $entries = $voucher->entries->map(function ($entry) use (&$totalDebit, &$totalCredit) {
                $totalDebit  += (float) $entry->debit;
                $totalCredit += (float) $entry->credit;

                return [
                    'account_code' => $entry->account->code ?? '',
                    'account_name' => $entry->account->name ?? '',
                    'debit'        => (float) $entry->debit,
                    'credit'       => (float) $entry->credit,
                    'narration'    => $entry->narration,
                ];
            });

            return [
                'voucher_no'   => $voucher->voucher_no,
                'voucher_type' => $voucher->voucher_type,
                'narration'    => $voucher->narration,
                'total_amount' => (float) $voucher->total_amount,
                'created_by'   => $voucher->creator->name ?? '',
                'entries'      => $entries->toArray(),
            ];
        });

        return [
            'date'         => $date,
            'vouchers'     => $voucherData->toArray(),
            'total_debit'  => round($totalDebit, 2),
            'total_credit' => round($totalCredit, 2),
            'voucher_count' => $vouchers->count(),
        ];
    }

    // ────────────────────────────────────────────────────────────
    //  HELPER METHODS
    // ────────────────────────────────────────────────────────────

    /**
     * Get retained earnings (cumulative net profit from inception to date).
     */
    private static function getRetainedEarnings(string $asOfDate, ?int $branchId = null): float
    {
        // Revenue
        $incomeGroupIds = self::getAllChildGroupIds(
            AccountGroup::where('type', 'income')->pluck('id')->toArray()
        );
        $revenue = self::getGroupTotalBalance($incomeGroupIds, $asOfDate, $branchId);

        // Expenses (including COGS)
        $expenseGroupIds = self::getAllChildGroupIds(
            AccountGroup::where('type', 'expense')->pluck('id')->toArray()
        );
        $expenses = self::getGroupTotalBalance($expenseGroupIds, $asOfDate, $branchId);

        return $revenue - $expenses;
    }

    /**
     * Get net balance for a period (transactions only within fromDate–toDate).
     * For debit-nature accounts: returns debit - credit (positive = expense/COGS)
     * For credit-nature accounts: returns credit - debit (positive = revenue)
     */
    private static function getAccountPeriodBalance(int $accountId, string $fromDate, string $toDate, ?int $branchId = null): float
    {
        $account = Account::with('group')->find($accountId);
        if (!$account) return 0;

        $query = VoucherEntry::where('voucher_entries.account_id', $accountId)
            ->join('vouchers', 'vouchers.id', '=', 'voucher_entries.voucher_id')
            ->where('vouchers.is_posted', true)
            ->whereNull('vouchers.deleted_at')
            ->whereBetween('vouchers.date', [$fromDate, $toDate]);

        if ($branchId) {
            $query->where('vouchers.branch_id', $branchId);
        }

        $totals = $query->select(
            DB::raw('COALESCE(SUM(voucher_entries.debit), 0) as sum_debit'),
            DB::raw('COALESCE(SUM(voucher_entries.credit), 0) as sum_credit')
        )->first();

        $debit  = (float) ($totals->sum_debit ?? 0);
        $credit = (float) ($totals->sum_credit ?? 0);

        if ($account->group && $account->group->nature === 'debit') {
            return $debit - $credit;
        }

        return $credit - $debit;
    }

    /**
     * Get account balances for all accounts in given group IDs, for a period.
     * Returns array of [name, amount] where amount is in natural direction.
     */
    private static function getGroupBalancesForPeriod(
        array   $groupIds,
        string  $fromDate,
        string  $toDate,
        ?int    $branchId = null,
        array   $excludeAccountIds = []
    ): array {
        $accounts = Account::whereIn('group_id', $groupIds)
            ->where('is_active', true)
            ->when($excludeAccountIds, fn($q) => $q->whereNotIn('id', $excludeAccountIds))
            ->forBranch($branchId)
            ->get();

        $items = [];

        foreach ($accounts as $account) {
            $amount = self::getAccountPeriodBalance($account->id, $fromDate, $toDate, $branchId);

            if (abs($amount) > 0.01) {
                $items[] = [
                    'id'     => $account->id,
                    'code'   => $account->code,
                    'name'   => $account->name,
                    'amount' => round($amount, 2),
                ];
            }
        }

        return $items;
    }

    /**
     * Get grouped balances for Balance Sheet display.
     * Groups accounts under their account_group hierarchy.
     */
    private static function getGroupedBalances(Collection $topGroups, string $asOfDate, ?int $branchId = null): array
    {
        $result = [];

        foreach ($topGroups as $group) {
            $groupData = self::processGroupForBalanceSheet($group, $asOfDate, $branchId);
            if ($groupData['total'] != 0) {
                $result[] = $groupData;
            }
        }

        return $result;
    }

    /**
     * Recursively process an account group for Balance Sheet.
     */
    private static function processGroupForBalanceSheet(AccountGroup $group, string $asOfDate, ?int $branchId = null): array
    {
        $accounts = Account::where('group_id', $group->id)
            ->where('is_active', true)
            ->forBranch($branchId)
            ->get();

        $accountItems = [];
        $groupTotal = 0;

        foreach ($accounts as $account) {
            $balance = LedgerService::getAccountBalance($account->id, $asOfDate, $branchId);

            if (abs($balance) > 0.01) {
                $accountItems[] = [
                    'id'      => $account->id,
                    'code'    => $account->code,
                    'name'    => $account->name,
                    'balance' => round($balance, 2),
                ];
                $groupTotal += $balance;
            }
        }

        // Process child groups
        $childGroups = [];
        foreach ($group->children ?? [] as $child) {
            $childData = self::processGroupForBalanceSheet($child, $asOfDate, $branchId);
            if ($childData['total'] != 0) {
                $childGroups[] = $childData;
                $groupTotal += $childData['total'];
            }
        }

        return [
            'group_name'   => $group->name,
            'group_code'   => $group->code,
            'accounts'     => $accountItems,
            'sub_groups'   => $childGroups,
            'total'        => round($groupTotal, 2),
        ];
    }

    /**
     * Get total balance across all accounts in the given group IDs.
     * Used for retained earnings calculation.
     */
    private static function getGroupTotalBalance(array $groupIds, string $asOfDate, ?int $branchId = null): float
    {
        $accounts = Account::whereIn('group_id', $groupIds)
            ->where('is_active', true)
            ->forBranch($branchId)
            ->get();

        $total = 0;

        foreach ($accounts as $account) {
            $total += LedgerService::getAccountBalance($account->id, $asOfDate, $branchId);
        }

        return $total;
    }

    /**
     * Get all child group IDs recursively for given parent group IDs.
     */
    private static function getAllChildGroupIds(array $parentIds): array
    {
        $allIds = $parentIds;
        $children = AccountGroup::whereIn('parent_id', $parentIds)->pluck('id')->toArray();

        if (!empty($children)) {
            $allIds = array_merge($allIds, self::getAllChildGroupIds($children));
        }

        return array_unique($allIds);
    }
}
