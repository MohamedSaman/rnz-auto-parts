<?php

/**
 * ══════════════════════════════════════════════════════════════════
 *  DOUBLE-ENTRY ACCOUNTING — INTEGRATION REFERENCE
 * ══════════════════════════════════════════════════════════════════
 *
 *  This file shows exactly how to integrate AccountingService
 *  into existing Livewire components. NO UI changes required.
 *
 *  Add these calls INSIDE the existing DB::transaction blocks,
 *  after the current business logic (sale creation, stock deduction, etc).
 * ══════════════════════════════════════════════════════════════════
 */

use App\Services\AccountingService;
use App\Services\LedgerService;
use App\Services\FinancialReportService;

// ─────────────────────────────────────────────────────────────────
// 1. SALE CREATION (StoreBilling.php / SalesSystem.php)
// ─────────────────────────────────────────────────────────────────
//
// LOCATION: Inside createSale() after Sale::create() and FIFOStockService::deductStock()
//
// WHAT TO ADD:
//
//   // After creating sale items and deducting stock...
//   // Store FIFO cost on each sale item:
//   foreach ($deductionResults as $cartKey => $result) {
//       $saleItem = SaleItem::find($saleItemIds[$cartKey]);
//       $saleItem->update([
//           'cost_price_at_sale' => $result['average_cost'] ?? 0,
//       ]);
//   }
//
//   // Calculate total COGS from stored costs:
//   $totalCOGS = $sale->items()->selectRaw('SUM(cost_price_at_sale * quantity) as total')->value('total') ?? 0;
//
//   // Post accounting entries:
//   AccountingService::postSale($sale, $totalCOGS);
//
// ─────────────────────────────────────────────────────────────────


// ─────────────────────────────────────────────────────────────────
// 2. GRN / PURCHASE RECEIVING (PurchaseOrderList.php)
// ─────────────────────────────────────────────────────────────────
//
// LOCATION: Inside convertToGRN() / reProcessGRN() after ProductBatch creation
//
// WHAT TO ADD:
//
//   // After batches are created and stock updated...
//   $inventoryValue = $purchaseOrder->items()
//       ->selectRaw('SUM(received_quantity * unit_price) as total')
//       ->value('total') ?? 0;
//
//   AccountingService::postPurchase($purchaseOrder, $inventoryValue);
//
// ─────────────────────────────────────────────────────────────────


// ─────────────────────────────────────────────────────────────────
// 3. CUSTOMER PAYMENT (AddCustomerReceipt.php)
// ─────────────────────────────────────────────────────────────────
//
// LOCATION: Inside processPayment() after Payment::create()
//
// WHAT TO ADD:
//
//   AccountingService::postCustomerReceipt($payment);
//
// ─────────────────────────────────────────────────────────────────


// ─────────────────────────────────────────────────────────────────
// 4. SUPPLIER PAYMENT (AddSupplierReceipt.php)
// ─────────────────────────────────────────────────────────────────
//
// LOCATION: Inside processPayment() after PurchasePayment::create()
//
// WHAT TO ADD:
//
//   AccountingService::postSupplierPayment($purchasePayment);
//
// ─────────────────────────────────────────────────────────────────


// ─────────────────────────────────────────────────────────────────
// 5. EXPENSE RECORDING (Expenses.php)
// ─────────────────────────────────────────────────────────────────
//
// LOCATION: Inside saveExpense() after Expense::create()
//
// WHAT TO ADD:
//
//   // Map expense category to ledger account:
//   $expenseAccountMap = [
//       'salary'    => 'E-SALARY',
//       'rent'      => 'E-RENT',
//       'utilities' => 'E-UTILITY',
//       'transport' => 'E-TRANSPORT',
//       'daily'     => 'E-DAILY',
//       // Add more mappings as needed...
//   ];
//
//   $accountCode = $expenseAccountMap[$expense->category] ?? 'E-DAILY';
//   $account = \App\Models\Account::where('code', $accountCode)->first();
//
//   AccountingService::postExpense($expense, $account->id, 'cash');
//
// ─────────────────────────────────────────────────────────────────


// ─────────────────────────────────────────────────────────────────
// 6. SALES RETURN (ReturnProduct.php)
// ─────────────────────────────────────────────────────────────────
//
// LOCATION: After ReturnsProduct::create() and stock restoration
//
// WHAT TO ADD:
//
//   $returnCOGS = /* selling_price from original sale_item's cost_price_at_sale × qty */;
//   AccountingService::postSalesReturn($originalSale, $returnAmount, $returnCOGS);
//
// ─────────────────────────────────────────────────────────────────


// ─────────────────────────────────────────────────────────────────
// 7. BANK DEPOSIT (Income.php / DaySummary.php)
// ─────────────────────────────────────────────────────────────────
//
// LOCATION: Inside bank deposit method after Deposit::create()
//
// WHAT TO ADD:
//
//   AccountingService::postContra($amount, 'deposit', 'Cash deposited to bank');
//
// ─────────────────────────────────────────────────────────────────


// ─────────────────────────────────────────────────────────────────
// 8. SALE EDIT / DELETE (StoreBilling.php)
// ─────────────────────────────────────────────────────────────────
//
// LOCATION: Before deleting old sale items/payments when editing
//
// WHAT TO ADD:
//
//   // Reverse the original voucher before re-posting:
//   if ($sale->voucher_id) {
//       $oldVoucher = \App\Models\Voucher::find($sale->voucher_id);
//       if ($oldVoucher) {
//           AccountingService::reverseVoucher($oldVoucher, 'Sale edited: ' . $sale->invoice_number);
//       }
//   }
//
//   // ... then after new items/payments are created:
//   AccountingService::postSale($sale, $newTotalCOGS);
//
// ─────────────────────────────────────────────────────────────────


// ═════════════════════════════════════════════════════════════════
// FINANCIAL REPORTS — REPLACEMENT QUERIES
// ═════════════════════════════════════════════════════════════════


// ─────────────────────────────────────────────────────────────────
// TRIAL BALANCE (replace or add to Reports.php)
// ─────────────────────────────────────────────────────────────────
//
//   $trialBalance = FinancialReportService::getTrialBalance('2026-03-03');
//   // Returns: accounts[], total_debit, total_credit, is_balanced
//


// ─────────────────────────────────────────────────────────────────
// PROFIT & LOSS (replace ProfitLoss.php logic)
// ─────────────────────────────────────────────────────────────────
//
//   $pnl = FinancialReportService::getProfitAndLoss('2026-01-01', '2026-03-31');
//   // Returns: revenue.items/total, cogs.items/total, gross_profit,
//   //          expenses.items/total, net_profit
//
//   // This replaces the old COGS calculation that used current prices.
//   // Now uses actual cost_price_at_sale × quantity from voucher entries.
//


// ─────────────────────────────────────────────────────────────────
// BALANCE SHEET
// ─────────────────────────────────────────────────────────────────
//
//   $bs = FinancialReportService::getBalanceSheet('2026-03-03');
//   // Returns: assets.groups/total, liabilities.groups/total,
//   //          equity.groups/retained_earnings/total, is_balanced
//


// ─────────────────────────────────────────────────────────────────
// LEDGER REPORT (for any account — customer, supplier, cash, etc.)
// ─────────────────────────────────────────────────────────────────
//
//   $ledger = LedgerService::getAccountLedger($accountId, '2026-01-01', '2026-03-31');
//   // Returns: opening_balance, entries[] with running balance, totals
//


// ─────────────────────────────────────────────────────────────────
// DAY BOOK
// ─────────────────────────────────────────────────────────────────
//
//   $dayBook = FinancialReportService::getDayBook('2026-03-03');
//   // Returns: all vouchers with debit/credit entries for the day
//


// ─────────────────────────────────────────────────────────────────
// REPLACE CASH-IN-HAND QUERIES
// ─────────────────────────────────────────────────────────────────
//
//   // OLD: CashInHand::where('key', 'cash_amount')->value('value');
//   // NEW:
//   $cashBalance = LedgerService::getCashBalance();
//   $bankBalance = LedgerService::getBankBalance();
//


// ─────────────────────────────────────────────────────────────────
// REPLACE CUSTOMER BALANCE QUERIES
// ─────────────────────────────────────────────────────────────────
//
//   // OLD: $customer->due_amount
//   // NEW:
//   $balance = LedgerService::getCustomerBalance($customerId);
//   // Positive = customer owes us. Negative = overpaid.
//


// ─────────────────────────────────────────────────────────────────
// REPLACE SUPPLIER BALANCE QUERIES
// ─────────────────────────────────────────────────────────────────
//
//   // OLD: sum of purchase_orders.due_amount
//   // NEW:
//   $balance = LedgerService::getSupplierBalance($supplierId);
//   // Positive = we owe them. Negative = overpaid.
//


// ═════════════════════════════════════════════════════════════════
// ADD AUDITABLE TRAIT TO EXISTING MODELS
// ═════════════════════════════════════════════════════════════════
//
// In each model that needs audit logging, add:
//
//   use App\Traits\Auditable;
//
//   class Sale extends Model
//   {
//       use HasFactory, SoftDeletes, Auditable;
//       ...
//   }
//
// Recommended models to add Auditable trait:
//   - Sale, Payment, PurchaseOrder, PurchasePayment
//   - Expense, Customer, ProductSupplier
//   - Voucher (already logged by AccountingService)
//
