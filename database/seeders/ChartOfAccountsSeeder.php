<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\AccountGroup;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\ProductSupplier;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * ChartOfAccountsSeeder — Seeds the standard chart of accounts.
 *
 * Run via: php artisan db:seed --class=ChartOfAccountsSeeder
 *
 * This seeder is idempotent — it uses firstOrCreate, so it can be run
 * multiple times without duplicating records. It also migrates existing
 * customers and suppliers into ledger accounts.
 *
 * ────────────────────────────────────────────────────
 * CHART OF ACCOUNTS STRUCTURE (BUSY/Tally style)
 * ────────────────────────────────────────────────────
 *
 * ASSETS (Debit)
 * ├── Current Assets
 * │   ├── Cash in Hand     [CASH]    (system)
 * │   ├── Bank Account     [BANK]    (system)
 * │   ├── Accounts Receivable [AR]   (system — parent for customer ledgers)
 * │   ├── Inventory        [INVENTORY] (system)
 * │   └── Input Tax        [INPUT-TAX](system)
 * └── Fixed Assets
 *     └── Furniture & Equipment [FA-EQUIP]
 *
 * LIABILITIES (Credit)
 * └── Current Liabilities
 *     ├── Accounts Payable  [AP]       (system — parent for supplier ledgers)
 *     ├── Output Tax        [OUTPUT-TAX](system)
 *     └── Staff Loans       [L-STAFF-LOAN]
 *
 * INCOME (Credit)
 * ├── Sales Revenue     [SALES]     (system)
 * ├── Sales Returns     [SALES-RET] (system — contra)
 * ├── Sales Discount    [SALES-DISC](system)
 * └── Other Income      [I-OTHER]
 *
 * EXPENSES (Debit)
 * ├── Cost of Goods Sold [COGS]      (system)
 * ├── Purchase Returns   [PURCH-RET] (system — contra)
 * ├── Purchase Discount  [PURCH-DISC](system)
 * ├── Operating Expenses
 * │   ├── Salary Expense   [E-SALARY]
 * │   ├── Rent Expense     [E-RENT]
 * │   ├── Utilities        [E-UTILITY]
 * │   ├── Transport        [E-TRANSPORT]
 * │   └── Daily Expenses   [E-DAILY]
 * └── Administrative Expenses [E-ADMIN]
 *
 * EQUITY (Credit)
 * ├── Owner's Capital       [EQ-CAPITAL]
 * ├── Retained Earnings     [EQ-RETAINED] (system)
 * └── Opening Balance Equity[EQ-OPENING]  (system)
 */
class ChartOfAccountsSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            // ── Create default branch if none exists ──
            $this->createDefaultBranch();

            // ── Account Groups ──
            $this->seedAccountGroups();

            // ── System Accounts ──
            $this->seedSystemAccounts();

            // ── Expense Sub-Accounts ──
            $this->seedExpenseAccounts();

            // ── Migrate existing customers to ledger accounts ──
            $this->migrateCustomerAccounts();

            // ── Migrate existing suppliers to ledger accounts ──
            $this->migrateSupplierAccounts();
        });

        $this->command->info('Chart of Accounts seeded successfully.');
        $this->command->info('Customer accounts created: ' . Account::where('reference_type', 'customer')->count());
        $this->command->info('Supplier accounts created: ' . Account::where('reference_type', 'supplier')->count());
    }

    private function createDefaultBranch(): void
    {
        Branch::firstOrCreate(
            ['code' => 'MAIN'],
            [
                'name'    => 'Main Branch',
                'is_main' => true,
                'is_active' => true,
            ]
        );
    }

    private function seedAccountGroups(): void
    {
        // ── Top-Level Groups (the 5 accounting types) ──
        $assets = $this->createGroup('Assets', 'A', 'asset', 'debit', null, true, 1);
        $liabilities = $this->createGroup('Liabilities', 'L', 'liability', 'credit', null, true, 2);
        $income = $this->createGroup('Income', 'I', 'income', 'credit', null, true, 3);
        $expenses = $this->createGroup('Expenses', 'E', 'expense', 'debit', null, true, 4);
        $equity = $this->createGroup('Equity', 'EQ', 'equity', 'credit', null, true, 5);

        // ── Asset Sub-Groups ──
        $this->createGroup('Current Assets', 'A-CA', 'asset', 'debit', $assets->id, true, 1);
        $this->createGroup('Fixed Assets', 'A-FA', 'asset', 'debit', $assets->id, true, 2);

        // ── Liability Sub-Groups ──
        $this->createGroup('Current Liabilities', 'L-CL', 'liability', 'credit', $liabilities->id, true, 1);

        // ── Expense Sub-Groups ──
        $this->createGroup('Operating Expenses', 'E-OP', 'expense', 'debit', $expenses->id, true, 1);
        $this->createGroup('Administrative Expenses', 'E-ADMIN-GRP', 'expense', 'debit', $expenses->id, true, 2);
    }

    private function seedSystemAccounts(): void
    {
        $ca = AccountGroup::where('code', 'A-CA')->first();
        $fa = AccountGroup::where('code', 'A-FA')->first();
        $cl = AccountGroup::where('code', 'L-CL')->first();
        $income = AccountGroup::where('code', 'I')->first();
        $expense = AccountGroup::where('code', 'E')->first();
        $equity = AccountGroup::where('code', 'EQ')->first();

        // ── Asset Accounts ──
        $this->createAccount('CASH', 'Cash in Hand', $ca->id, true);
        $this->createAccount('BANK', 'Bank Account', $ca->id, true);
        $this->createAccount('AR', 'Accounts Receivable', $ca->id, true);
        $this->createAccount('INVENTORY', 'Inventory', $ca->id, true);
        $this->createAccount('INPUT-TAX', 'Input Tax (VAT/GST)', $ca->id, true);
        $this->createAccount('FA-EQUIP', 'Furniture & Equipment', $fa->id, false);

        // ── Liability Accounts ──
        $this->createAccount('AP', 'Accounts Payable', $cl->id, true);
        $this->createAccount('OUTPUT-TAX', 'Output Tax (VAT/GST)', $cl->id, true);
        $this->createAccount('L-STAFF-LOAN', 'Staff Loans Payable', $cl->id, false);

        // ── Income Accounts ──
        $this->createAccount('SALES', 'Sales Revenue', $income->id, true);
        $this->createAccount('SALES-RET', 'Sales Returns', $income->id, true);
        $this->createAccount('SALES-DISC', 'Sales Discount', $income->id, true);
        $this->createAccount('I-OTHER', 'Other Income', $income->id, false);

        // ── Expense Accounts (top level) ──
        $this->createAccount('COGS', 'Cost of Goods Sold', $expense->id, true);
        $this->createAccount('PURCH-RET', 'Purchase Returns', $expense->id, true);
        $this->createAccount('PURCH-DISC', 'Purchase Discount', $expense->id, true);

        // ── Equity Accounts ──
        $this->createAccount('EQ-CAPITAL', "Owner's Capital", $equity->id, false);
        $this->createAccount('EQ-RETAINED', 'Retained Earnings', $equity->id, true);
        $this->createAccount('EQ-OPENING', 'Opening Balance Equity', $equity->id, true);
    }

    private function seedExpenseAccounts(): void
    {
        $opExp = AccountGroup::where('code', 'E-OP')->first();
        $adminExp = AccountGroup::where('code', 'E-ADMIN-GRP')->first();

        // ── Operating Expense Accounts ──
        $this->createAccount('E-SALARY', 'Salary Expense', $opExp->id, false);
        $this->createAccount('E-RENT', 'Rent Expense', $opExp->id, false);
        $this->createAccount('E-UTILITY', 'Utilities Expense', $opExp->id, false);
        $this->createAccount('E-TRANSPORT', 'Transport Expense', $opExp->id, false);
        $this->createAccount('E-DAILY', 'Daily Expenses', $opExp->id, false);
        $this->createAccount('E-MAINTENANCE', 'Maintenance Expense', $opExp->id, false);
        $this->createAccount('E-TEL', 'Telephone & Internet', $opExp->id, false);

        // ── Administrative Expense Accounts ──
        $this->createAccount('E-ADMIN', 'Administrative Expenses', $adminExp->id, false);
        $this->createAccount('E-OFFICE', 'Office Supplies', $adminExp->id, false);
        $this->createAccount('E-PRINTING', 'Printing & Stationery', $adminExp->id, false);
    }

    /**
     * Migrate existing customers into ledger accounts.
     * Maps opening_balance to opening_debit (customers owe us = debit).
     */
    private function migrateCustomerAccounts(): void
    {
        $arAccount = Account::where('code', 'AR')->first();
        if (!$arAccount) return;

        Customer::whereNull('account_id')->chunk(100, function ($customers) use ($arAccount) {
            foreach ($customers as $customer) {
                $existingAccount = Account::where('reference_type', 'customer')
                    ->where('reference_id', $customer->id)
                    ->first();

                if ($existingAccount) {
                    $customer->update(['account_id' => $existingAccount->id]);
                    continue;
                }

                $openingBalance = (float) ($customer->opening_balance ?? 0)
                    + (float) ($customer->due_amount ?? 0);

                $account = Account::create([
                    'code'              => 'CUST-' . str_pad($customer->id, 5, '0', STR_PAD_LEFT),
                    'name'              => $customer->business_name ?: $customer->name,
                    'group_id'          => $arAccount->group_id,
                    'parent_account_id' => $arAccount->id,
                    'is_system'         => false,
                    'reference_type'    => 'customer',
                    'reference_id'      => $customer->id,
                    'opening_debit'     => max($openingBalance, 0),
                    'opening_credit'    => max(-$openingBalance, 0), // overpaid
                    'is_active'         => true,
                ]);

                $customer->update(['account_id' => $account->id]);
            }
        });
    }

    /**
     * Migrate existing suppliers into ledger accounts.
     * Maps outstanding PO amounts to opening_credit (we owe them = credit).
     */
    private function migrateSupplierAccounts(): void
    {
        $apAccount = Account::where('code', 'AP')->first();
        if (!$apAccount) return;

        ProductSupplier::whereNull('account_id')->chunk(100, function ($suppliers) use ($apAccount) {
            foreach ($suppliers as $supplier) {
                $existingAccount = Account::where('reference_type', 'supplier')
                    ->where('reference_id', $supplier->id)
                    ->first();

                if ($existingAccount) {
                    $supplier->update(['account_id' => $existingAccount->id]);
                    continue;
                }

                // Sum outstanding purchase orders
                $outstanding = $supplier->purchaseOrders()
                    ->where('due_amount', '>', 0)
                    ->sum('due_amount');

                $overpayment = (float) ($supplier->overpayment ?? 0);

                $account = Account::create([
                    'code'              => 'SUPP-' . str_pad($supplier->id, 5, '0', STR_PAD_LEFT),
                    'name'              => $supplier->businessname ?: $supplier->name,
                    'group_id'          => $apAccount->group_id,
                    'parent_account_id' => $apAccount->id,
                    'is_system'         => false,
                    'reference_type'    => 'supplier',
                    'reference_id'      => $supplier->id,
                    'opening_debit'     => $overpayment, // they owe us (overpaid)
                    'opening_credit'    => (float) $outstanding, // we owe them
                    'is_active'         => true,
                ]);

                $supplier->update(['account_id' => $account->id]);
            }
        });
    }

    // ── Helper Methods ──

    private function createGroup(
        string $name,
        string $code,
        string $type,
        string $nature,
        ?int   $parentId,
        bool   $isSystem,
        int    $sortOrder
    ): AccountGroup {
        return AccountGroup::firstOrCreate(
            ['code' => $code],
            [
                'name'      => $name,
                'type'      => $type,
                'nature'    => $nature,
                'parent_id' => $parentId,
                'is_system' => $isSystem,
                'is_active' => true,
                'sort_order' => $sortOrder,
            ]
        );
    }

    private function createAccount(
        string $code,
        string $name,
        int    $groupId,
        bool   $isSystem
    ): Account {
        return Account::firstOrCreate(
            ['code' => $code],
            [
                'name'      => $name,
                'group_id'  => $groupId,
                'is_system' => $isSystem,
                'is_active' => true,
                'opening_debit'  => 0,
                'opening_credit' => 0,
            ]
        );
    }
}
