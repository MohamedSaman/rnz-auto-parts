<?php

namespace App\Services;

use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\ProductSupplier;
use App\Models\PurchaseOrder;
use App\Models\PurchasePayment;
use App\Models\Sale;
use App\Models\Voucher;
use App\Models\VoucherEntry;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * AccountingService — Core double-entry voucher posting engine.
 *
 * Every financial transaction flows through this service to ensure
 * balanced debit/credit entries are created inside a DB transaction.
 *
 * System Account Codes (seeded by ChartOfAccountsSeeder):
 *   CASH        - Cash in Hand
 *   BANK        - Bank Account
 *   AR          - Accounts Receivable (parent for customer sub-accounts)
 *   AP          - Accounts Payable   (parent for supplier sub-accounts)
 *   INVENTORY   - Inventory / Stock
 *   SALES       - Sales Revenue
 *   SALES-RET   - Sales Returns
 *   SALES-DISC  - Sales Discount
 *   COGS        - Cost of Goods Sold
 *   PURCH-RET   - Purchase Returns
 *   PURCH-DISC  - Purchase Discount
 *   INPUT-TAX   - Input Tax (VAT/GST receivable)
 *   OUTPUT-TAX  - Output Tax (VAT/GST payable)
 *   EQ-OPENING  - Opening Balance Equity
 */
class AccountingService
{
    // ────────────────────────────────────────────────────────────
    //  GENERIC VOUCHER CREATION
    // ────────────────────────────────────────────────────────────

    /**
     * Create a voucher with balanced entries.
     *
     * @param  string  $type        Voucher::TYPE_* constant
     * @param  Carbon  $date        Transaction date
     * @param  array   $entries     [ ['account_id'=>int, 'debit'=>float, 'credit'=>float, 'narration'=>?string], ... ]
     * @param  string|null  $narration   Voucher narration
     * @param  string|null  $refType     Reference type  (e.g. 'sale', 'purchase_order')
     * @param  int|null     $refId       Reference ID
     * @param  int|null     $branchId    Branch ID
     * @return Voucher
     *
     * @throws InvalidArgumentException if entries don't balance
     */
    public static function createVoucher(
        string  $type,
        Carbon  $date,
        array   $entries,
        ?string $narration = null,
        ?string $refType = null,
        ?int    $refId = null,
        ?int    $branchId = null
    ): Voucher {
        self::validateBalance($entries);

        return DB::transaction(function () use ($type, $date, $entries, $narration, $refType, $refId, $branchId) {
            $totalAmount = collect($entries)->sum('debit');

            $voucher = Voucher::create([
                'voucher_no'     => Voucher::generateVoucherNo($type),
                'voucher_type'   => $type,
                'date'           => $date,
                'narration'      => $narration,
                'reference_type' => $refType,
                'reference_id'   => $refId,
                'total_amount'   => $totalAmount,
                'branch_id'      => $branchId,
                'is_posted'      => true,
                'created_by'     => Auth::id(),
            ]);

            foreach ($entries as $entry) {
                VoucherEntry::create([
                    'voucher_id' => $voucher->id,
                    'account_id' => $entry['account_id'],
                    'debit'      => round($entry['debit'] ?? 0, 2),
                    'credit'     => round($entry['credit'] ?? 0, 2),
                    'narration'  => $entry['narration'] ?? null,
                ]);
            }

            AuditLog::record('created', $voucher, null, $voucher->toArray());

            return $voucher;
        });
    }

    // ────────────────────────────────────────────────────────────
    //  SALE POSTING
    // ────────────────────────────────────────────────────────────

    /**
     * Post accounting entries for a sale.
     *
     * @param  Sale   $sale       The sale record (already saved)
     * @param  float  $totalCOGS  Total FIFO cost of goods sold (from FIFOStockService)
     * @param  int|null $branchId
     * @return Voucher
     *
     * Entries for credit sale:
     *   Dr  Customer A/c (or Cash)      total_amount
     *   Cr  Sales Revenue               total_amount - tax
     *   Cr  Output Tax                  tax_amount (if > 0)
     *
     *   Dr  COGS                        totalCOGS
     *   Cr  Inventory                   totalCOGS
     *
     * If discount:
     *   Dr  Sales Discount              discount_amount
     *   Cr  Customer A/c (or Cash)      discount_amount
     *   (Discount is already netted in total_amount, so this is informational journal)
     */
    public static function postSale(Sale $sale, float $totalCOGS, ?int $branchId = null): Voucher
    {
        $entries = [];
        $saleTotal = (float) $sale->total_amount;
        $taxAmount = (float) ($sale->tax_amount ?? 0);
        $revenueAmount = $saleTotal - $taxAmount;

        // ── Determine debit account (Customer or Cash) ──
        if ($sale->payment_type === 'full' && $sale->payment_status === 'paid') {
            // Cash/immediate sale → debit Cash
            $debitAccountId = self::getSystemAccountId('CASH');
        } else {
            // Credit sale → debit Customer ledger
            $customer = Customer::find($sale->customer_id);
            $customerAccount = $customer
                ? Account::getOrCreateForCustomer($customer)
                : self::getSystemAccount('AR');
            $debitAccountId = $customerAccount->id;
        }

        // Dr Customer / Cash
        $entries[] = [
            'account_id' => $debitAccountId,
            'debit'      => $saleTotal,
            'credit'     => 0,
            'narration'  => 'Sale ' . $sale->invoice_number,
        ];

        // Cr Sales Revenue
        $entries[] = [
            'account_id' => self::getSystemAccountId('SALES'),
            'debit'      => 0,
            'credit'     => $revenueAmount,
            'narration'  => 'Revenue for ' . $sale->invoice_number,
        ];

        // Cr Output Tax (if applicable)
        if ($taxAmount > 0) {
            $entries[] = [
                'account_id' => self::getSystemAccountId('OUTPUT-TAX'),
                'debit'      => 0,
                'credit'     => $taxAmount,
                'narration'  => 'Tax on ' . $sale->invoice_number,
            ];
        }

        // ── COGS & Inventory entries (only if totalCOGS > 0) ──
        if ($totalCOGS > 0) {
            // Dr COGS
            $entries[] = [
                'account_id' => self::getSystemAccountId('COGS'),
                'debit'      => $totalCOGS,
                'credit'     => 0,
                'narration'  => 'Cost of goods: ' . $sale->invoice_number,
            ];

            // Cr Inventory
            $entries[] = [
                'account_id' => self::getSystemAccountId('INVENTORY'),
                'debit'      => 0,
                'credit'     => $totalCOGS,
                'narration'  => 'Stock reduction: ' . $sale->invoice_number,
            ];
        }

        $voucher = self::createVoucher(
            Voucher::TYPE_SALES,
            Carbon::parse($sale->created_at),
            $entries,
            'Sales Voucher: ' . $sale->invoice_number,
            'sale',
            $sale->id,
            $branchId ?? $sale->branch_id
        );

        // Link voucher back to sale
        $sale->update(['voucher_id' => $voucher->id]);

        return $voucher;
    }

    // ────────────────────────────────────────────────────────────
    //  PURCHASE / GRN POSTING
    // ────────────────────────────────────────────────────────────

    /**
     * Post accounting entries for a purchase (GRN received).
     *
     * @param  PurchaseOrder  $po
     * @param  float          $inventoryValue  Total value at supplier price
     * @param  int|null       $branchId
     * @return Voucher
     *
     * Entries:
     *   Dr  Inventory                   inventoryValue
     *   Dr  Input Tax                   tax (if > 0)
     *   Cr  Supplier A/c               inventoryValue + tax
     */
    public static function postPurchase(PurchaseOrder $po, float $inventoryValue, ?int $branchId = null): Voucher
    {
        $entries = [];
        $taxAmount = (float) ($po->tax_amount ?? 0);
        $totalPayable = $inventoryValue + $taxAmount;

        // Get supplier account
        $supplier = ProductSupplier::find($po->supplier_id);
        $supplierAccount = $supplier
            ? Account::getOrCreateForSupplier($supplier)
            : self::getSystemAccount('AP');

        // Dr Inventory
        $entries[] = [
            'account_id' => self::getSystemAccountId('INVENTORY'),
            'debit'      => $inventoryValue,
            'credit'     => 0,
            'narration'  => 'Purchase: ' . $po->order_code,
        ];

        // Dr Input Tax (if applicable)
        if ($taxAmount > 0) {
            $entries[] = [
                'account_id' => self::getSystemAccountId('INPUT-TAX'),
                'debit'      => $taxAmount,
                'credit'     => 0,
                'narration'  => 'Tax on purchase: ' . $po->order_code,
            ];
        }

        // Cr Supplier
        $entries[] = [
            'account_id' => $supplierAccount->id,
            'debit'      => 0,
            'credit'     => $totalPayable,
            'narration'  => 'Payable: ' . $po->order_code,
        ];

        $voucher = self::createVoucher(
            Voucher::TYPE_PURCHASE,
            Carbon::parse($po->order_date ?? now()),
            $entries,
            'Purchase Voucher: ' . $po->order_code,
            'purchase_order',
            $po->id,
            $branchId ?? $po->branch_id
        );

        $po->update(['voucher_id' => $voucher->id]);

        return $voucher;
    }

    // ────────────────────────────────────────────────────────────
    //  CUSTOMER PAYMENT RECEIPT
    // ────────────────────────────────────────────────────────────

    /**
     * Post accounting entries for receiving payment from a customer.
     *
     * @param  Payment  $payment
     * @param  int|null $branchId
     * @return Voucher
     *
     * Entries:
     *   Dr  Cash / Bank / Cheque        amount
     *   Cr  Customer A/c               amount
     */
    public static function postCustomerReceipt(Payment $payment, ?int $branchId = null): Voucher
    {
        $entries = [];
        $amount = (float) $payment->amount;

        // Determine receipt method account
        $debitAccountId = self::resolvePaymentMethodAccount($payment->payment_method);

        // Dr Cash / Bank
        $entries[] = [
            'account_id' => $debitAccountId,
            'debit'      => $amount,
            'credit'     => 0,
            'narration'  => 'Received from customer',
        ];

        // Cr Customer A/c
        $customer = Customer::find($payment->customer_id);
        $customerAccount = $customer
            ? Account::getOrCreateForCustomer($customer)
            : self::getSystemAccount('AR');

        $entries[] = [
            'account_id' => $customerAccount->id,
            'debit'      => 0,
            'credit'     => $amount,
            'narration'  => 'Payment received',
        ];

        $voucher = self::createVoucher(
            Voucher::TYPE_RECEIPT,
            Carbon::parse($payment->payment_date ?? now()),
            $entries,
            'Receipt from: ' . ($customer->name ?? 'Customer'),
            'payment',
            $payment->id,
            $branchId ?? $payment->branch_id
        );

        $payment->update(['voucher_id' => $voucher->id]);

        return $voucher;
    }

    // ────────────────────────────────────────────────────────────
    //  SUPPLIER PAYMENT
    // ────────────────────────────────────────────────────────────

    /**
     * Post accounting entries for paying a supplier.
     *
     * @param  PurchasePayment  $payment
     * @param  int|null         $branchId
     * @return Voucher
     *
     * Entries:
     *   Dr  Supplier A/c               amount
     *   Cr  Cash / Bank                amount
     */
    public static function postSupplierPayment(PurchasePayment $payment, ?int $branchId = null): Voucher
    {
        $entries = [];
        $amount = (float) $payment->amount + (float) ($payment->overpayment_used ?? 0);

        // Dr Supplier
        $supplier = ProductSupplier::find($payment->supplier_id);
        $supplierAccount = $supplier
            ? Account::getOrCreateForSupplier($supplier)
            : self::getSystemAccount('AP');

        $entries[] = [
            'account_id' => $supplierAccount->id,
            'debit'      => $amount,
            'credit'     => 0,
            'narration'  => 'Payment to supplier',
        ];

        // Cr Cash / Bank (only the actual cash portion)
        $cashAmount = (float) $payment->amount;
        if ($cashAmount > 0) {
            $creditAccountId = self::resolvePaymentMethodAccount($payment->payment_method);
            $entries[] = [
                'account_id' => $creditAccountId,
                'debit'      => 0,
                'credit'     => $cashAmount,
                'narration'  => 'Paid to supplier',
            ];
        }

        // If overpayment credit was used, no additional entry needed
        // (overpayment was already in the supplier ledger as a debit balance)
        // But if overpayment_used > 0 and actual cash < total, we need to balance
        $overpaymentUsed = (float) ($payment->overpayment_used ?? 0);
        if ($overpaymentUsed > 0 && $cashAmount < $amount) {
            // The overpayment credit already sits as excess debit on supplier account
            // We only need to credit cash for actual payment, debit supplier for full amount
            // Adjust: only debit supplier for cash portion, overpayment is already netted
            // Re-calculate entries
            $entries = [];

            if ($cashAmount > 0) {
                // Dr Supplier (for cash paid)
                $entries[] = [
                    'account_id' => $supplierAccount->id,
                    'debit'      => $cashAmount,
                    'credit'     => 0,
                    'narration'  => 'Cash payment to supplier',
                ];

                // Cr Cash / Bank
                $entries[] = [
                    'account_id' => self::resolvePaymentMethodAccount($payment->payment_method),
                    'debit'      => 0,
                    'credit'     => $cashAmount,
                    'narration'  => 'Paid to supplier',
                ];
            }

            // No entry for overpayment adjustment — it's already reflected
            // in the supplier ledger from the original overpayment receipt
        }

        // Don't create empty vouchers
        if (empty($entries)) {
            // Overpayment-only payment: journal entry to reclassify
            $entries[] = [
                'account_id' => $supplierAccount->id,
                'debit'      => $overpaymentUsed,
                'credit'     => 0,
                'narration'  => 'Overpayment credit applied',
            ];
            $entries[] = [
                'account_id' => $supplierAccount->id,
                'debit'      => 0,
                'credit'     => $overpaymentUsed,
                'narration'  => 'Overpayment credit used',
            ];
        }

        $voucher = self::createVoucher(
            Voucher::TYPE_PAYMENT,
            Carbon::parse($payment->payment_date ?? now()),
            $entries,
            'Payment to: ' . ($supplier->name ?? 'Supplier'),
            'purchase_payment',
            $payment->id,
            $branchId ?? $payment->branch_id
        );

        $payment->update(['voucher_id' => $voucher->id]);

        return $voucher;
    }

    // ────────────────────────────────────────────────────────────
    //  EXPENSE POSTING
    // ────────────────────────────────────────────────────────────

    /**
     * Post accounting entries for an expense.
     *
     * @param  Expense    $expense
     * @param  int        $expenseAccountId  The specific expense ledger account
     * @param  string     $paidFrom          'cash' or 'bank'
     * @param  int|null   $branchId
     * @return Voucher
     *
     * Entries:
     *   Dr  Expense A/c                amount
     *   Cr  Cash / Bank                amount
     */
    public static function postExpense(
        Expense $expense,
        int     $expenseAccountId,
        string  $paidFrom = 'cash',
        ?int    $branchId = null
    ): Voucher {
        $amount = (float) $expense->amount;

        $creditAccountId = $paidFrom === 'bank'
            ? self::getSystemAccountId('BANK')
            : self::getSystemAccountId('CASH');

        $entries = [
            [
                'account_id' => $expenseAccountId,
                'debit'      => $amount,
                'credit'     => 0,
                'narration'  => $expense->description ?? $expense->category,
            ],
            [
                'account_id' => $creditAccountId,
                'debit'      => 0,
                'credit'     => $amount,
                'narration'  => 'Expense payment',
            ],
        ];

        $voucher = self::createVoucher(
            Voucher::TYPE_EXPENSE,
            Carbon::parse($expense->date ?? now()),
            $entries,
            'Expense: ' . ($expense->description ?? $expense->category),
            'expense',
            $expense->id,
            $branchId ?? $expense->branch_id
        );

        $expense->update(['voucher_id' => $voucher->id]);

        return $voucher;
    }

    // ────────────────────────────────────────────────────────────
    //  SALES RETURN
    // ────────────────────────────────────────────────────────────

    /**
     * Post accounting entries for a sales return.
     *
     * @param  Sale    $originalSale
     * @param  float   $returnAmount    Total return value (selling price × qty)
     * @param  float   $returnCOGS      Cost of returned goods
     * @param  int|null $branchId
     * @return Voucher
     *
     * Entries:
     *   Dr  Sales Returns               returnAmount
     *   Cr  Customer A/c (or Cash)      returnAmount
     *   Dr  Inventory                   returnCOGS
     *   Cr  COGS                        returnCOGS
     */
    public static function postSalesReturn(
        Sale   $originalSale,
        float  $returnAmount,
        float  $returnCOGS,
        ?int   $branchId = null,
        string $refundType = 'credit_note'
    ): Voucher {
        $entries = [];

        // Dr Sales Returns
        $entries[] = [
            'account_id' => self::getSystemAccountId('SALES-RET'),
            'debit'      => $returnAmount,
            'credit'     => 0,
            'narration'  => 'Return against ' . $originalSale->invoice_number,
        ];

        // Cr Customer / Cash based on refund mode
        $customer = Customer::find($originalSale->customer_id);
        $creditAccountId = $refundType === 'cash'
            ? self::getSystemAccountId('CASH')
            : ($customer ? Account::getOrCreateForCustomer($customer)->id : self::getSystemAccountId('CASH'));

        $entries[] = [
            'account_id' => $creditAccountId,
            'debit'      => 0,
            'credit'     => $returnAmount,
            'narration'  => 'Refund for return',
        ];

        // Dr Inventory (stock returned)
        if ($returnCOGS > 0) {
            $entries[] = [
                'account_id' => self::getSystemAccountId('INVENTORY'),
                'debit'      => $returnCOGS,
                'credit'     => 0,
                'narration'  => 'Stock restored from return',
            ];

            // Cr COGS
            $entries[] = [
                'account_id' => self::getSystemAccountId('COGS'),
                'debit'      => 0,
                'credit'     => $returnCOGS,
                'narration'  => 'COGS reversal for return',
            ];
        }

        return self::createVoucher(
            Voucher::TYPE_JOURNAL,
            Carbon::now(),
            $entries,
            'Sales Return: ' . $originalSale->invoice_number,
            'sale',
            $originalSale->id,
            $branchId ?? $originalSale->branch_id
        );
    }

    // ────────────────────────────────────────────────────────────
    //  PURCHASE RETURN
    // ────────────────────────────────────────────────────────────

    /**
     * Post accounting entries for a purchase return.
     *
     * @param  PurchaseOrder $po
     * @param  float         $returnValue   Cost of items returned
     * @param  int|null      $branchId
     * @return Voucher
     *
     * Entries:
     *   Dr  Supplier A/c               returnValue
     *   Cr  Inventory                   returnValue
     */
    public static function postPurchaseReturn(
        PurchaseOrder $po,
        float         $returnValue,
        ?int          $branchId = null,
        string        $settlementType = 'debit_note'
    ): ?Voucher {
        if ($settlementType === 'replacement' || $returnValue <= 0) {
            return null;
        }

        $supplier = ProductSupplier::find($po->supplier_id);
        $supplierAccount = $supplier
            ? Account::getOrCreateForSupplier($supplier)
            : self::getSystemAccount('AP');

        $debitAccountId = $settlementType === 'cash_refund'
            ? self::getSystemAccountId('CASH')
            : $supplierAccount->id;

        $entries = [
            [
                'account_id' => $debitAccountId,
                'debit'      => $returnValue,
                'credit'     => 0,
                'narration'  => 'Purchase return: ' . $po->order_code,
            ],
            [
                'account_id' => self::getSystemAccountId('PURCH-RET'),
                'debit'      => 0,
                'credit'     => $returnValue,
                'narration'  => 'Purchase return account: ' . $po->order_code,
            ],
        ];

        return self::createVoucher(
            Voucher::TYPE_JOURNAL,
            Carbon::now(),
            $entries,
            'Purchase Return: ' . $po->order_code,
            'purchase_order',
            $po->id,
            $branchId ?? $po->branch_id
        );
    }

    // ────────────────────────────────────────────────────────────
    //  CONTRA ENTRY (Cash ↔ Bank)
    // ────────────────────────────────────────────────────────────

    /**
     * Post a contra entry (bank deposit or withdrawal).
     *
     * @param  float   $amount
     * @param  string  $direction  'deposit' (Cash→Bank) or 'withdrawal' (Bank→Cash)
     * @param  string  $narration
     * @param  int|null $branchId
     * @return Voucher
     */
    public static function postContra(
        float   $amount,
        string  $direction = 'deposit',
        string  $narration = '',
        ?int    $branchId = null
    ): Voucher {
        if ($direction === 'deposit') {
            $entries = [
                ['account_id' => self::getSystemAccountId('BANK'), 'debit' => $amount, 'credit' => 0, 'narration' => 'Cash deposited to bank'],
                ['account_id' => self::getSystemAccountId('CASH'), 'debit' => 0, 'credit' => $amount, 'narration' => 'Cash deposited to bank'],
            ];
        } else {
            $entries = [
                ['account_id' => self::getSystemAccountId('CASH'), 'debit' => $amount, 'credit' => 0, 'narration' => 'Cash withdrawn from bank'],
                ['account_id' => self::getSystemAccountId('BANK'), 'debit' => 0, 'credit' => $amount, 'narration' => 'Cash withdrawn from bank'],
            ];
        }

        return self::createVoucher(
            Voucher::TYPE_CONTRA,
            Carbon::now(),
            $entries,
            $narration ?: 'Contra: ' . ucfirst($direction),
            null,
            null,
            $branchId
        );
    }

    // ────────────────────────────────────────────────────────────
    //  VOUCHER REVERSAL (for edits / deletes)
    // ────────────────────────────────────────────────────────────

    /**
     * Reverse a previously posted voucher by creating an opposite entry.
     * The original voucher is soft-deleted.
     *
     * @param  Voucher $voucher
     * @param  string  $reason
     * @return Voucher  The reversal voucher
     */
    public static function reverseVoucher(Voucher $voucher, string $reason = 'Reversal'): Voucher
    {
        $reversalEntries = [];

        foreach ($voucher->entries as $entry) {
            $reversalEntries[] = [
                'account_id' => $entry->account_id,
                'debit'      => (float) $entry->credit,  // Swap debit/credit
                'credit'     => (float) $entry->debit,
                'narration'  => 'Reversal: ' . ($entry->narration ?? ''),
            ];
        }

        $reversalVoucher = self::createVoucher(
            Voucher::TYPE_JOURNAL,
            Carbon::now(),
            $reversalEntries,
            $reason . ' (Reversing ' . $voucher->voucher_no . ')',
            $voucher->reference_type,
            $voucher->reference_id,
            $voucher->branch_id
        );

        // Soft-delete the original
        $voucher->update(['is_posted' => false]);
        $voucher->delete();

        AuditLog::record('deleted', $voucher, $voucher->toArray(), [
            'reason' => $reason,
            'reversal_voucher_id' => $reversalVoucher->id,
        ]);

        return $reversalVoucher;
    }

    // ────────────────────────────────────────────────────────────
    //  HELPER METHODS
    // ────────────────────────────────────────────────────────────

    /**
     * Validate that total debits equal total credits.
     *
     * @throws InvalidArgumentException
     */
    private static function validateBalance(array $entries): void
    {
        $totalDebit  = 0;
        $totalCredit = 0;

        foreach ($entries as $entry) {
            $totalDebit  += round($entry['debit'] ?? 0, 2);
            $totalCredit += round($entry['credit'] ?? 0, 2);
        }

        if (round($totalDebit, 2) !== round($totalCredit, 2)) {
            throw new InvalidArgumentException(
                "Voucher entries are not balanced. Debit: {$totalDebit}, Credit: {$totalCredit}"
            );
        }

        if ($totalDebit == 0) {
            throw new InvalidArgumentException("Voucher entries cannot have zero total.");
        }
    }

    /**
     * Resolve the ledger account for a payment method.
     */
    private static function resolvePaymentMethodAccount(string $method): int
    {
        return match (strtolower($method)) {
            'bank_transfer', 'bank', 'card', 'credit_card' => self::getSystemAccountId('BANK'),
            'cheque' => self::getSystemAccountId('BANK'), // Cheques go to bank
            default  => self::getSystemAccountId('CASH'),  // cash, default
        };
    }

    /**
     * Get a system account model by its code.
     *
     * @throws \RuntimeException if not found
     */
    public static function getSystemAccount(string $code): Account
    {
        $account = Account::where('code', $code)->where('is_system', true)->first();

        if (!$account) {
            throw new \RuntimeException(
                "System account [{$code}] not found. Run: php artisan db:seed --class=ChartOfAccountsSeeder"
            );
        }

        return $account;
    }

    /**
     * Get a system account ID by its code.
     */
    public static function getSystemAccountId(string $code): int
    {
        return self::getSystemAccount($code)->id;
    }

    /**
     * Calculate total COGS for a sale from its items' cost_price_at_sale.
     * Call this AFTER FIFOStockService has deducted stock and costs are stored.
     */
    public static function calculateSaleCOGS(Sale $sale): float
    {
        return $sale->items()
            ->selectRaw('SUM(cost_price_at_sale * quantity) as total_cogs')
            ->value('total_cogs') ?? 0;
    }
}
