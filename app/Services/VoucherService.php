<?php

namespace App\Services;

use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Customer;
use App\Models\ProductDetail;
use App\Models\ProductStock;
use App\Models\ProductSupplier;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\ProductBatch;
use App\Models\ProductPrice;
use App\Models\Voucher;
use App\Services\AccountingService;
use App\Services\FIFOStockService;
use App\Services\InventoryService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * VoucherService — Orchestrates BUSY-style voucher operations.
 *
 * Handles the complete lifecycle of sales & purchase vouchers:
 *   1. Create / Save voucher (sale/purchase + items + stock + accounting)
 *   2. Modify / Update voucher (reverse old → apply new)
 *   3. Delete / Cancel voucher (reverse all)
 *   4. Voucher numbering
 */
class VoucherService
{
    /**
     * Save a new Sales Voucher.
     *
     * @param  array  $voucherData   [date, customer_id, billing_type, salesman_id, notes]
     * @param  array  $items         [['product_id','variant_id','variant_value','quantity','rate','discount','tax'], ...]
     * @return Sale
     */
    public static function createSalesVoucher(array $voucherData, array $items): Sale
    {
        return DB::transaction(function () use ($voucherData, $items) {
            // 1. Generate voucher/invoice number
            $invoiceNumber = Sale::generateInvoiceNumber();
            $saleId = Sale::generateSaleId();

            // Calculate totals
            $subtotal = 0;
            $totalDiscount = 0;
            $totalTax = 0;

            foreach ($items as &$item) {
                $lineTotal = $item['quantity'] * $item['rate'];
                $lineDiscount = ($item['discount'] ?? 0) * $item['quantity'];
                $lineTax = ($item['tax_amount'] ?? 0);
                $lineNet = $lineTotal - $lineDiscount + $lineTax;

                $item['line_total'] = $lineTotal;
                $item['line_discount'] = $lineDiscount;
                $item['line_tax'] = $lineTax;
                $item['line_net'] = $lineNet;

                $subtotal += $lineTotal;
                $totalDiscount += $lineDiscount;
                $totalTax += $lineTax;
            }
            unset($item);

            $grandTotal = $subtotal - $totalDiscount + $totalTax;

            // Determine payment status based on billing type
            $billingType = $voucherData['billing_type'] ?? 'cash';
            $paymentStatus = $billingType === 'cash' ? 'paid' : 'pending';
            $paymentType = $billingType === 'cash' ? 'full' : 'partial';

            // 2. Create the Sale record
            $sale = Sale::create([
                'sale_id' => $saleId,
                'invoice_number' => $invoiceNumber,
                'sale_type' => 'admin',
                'customer_id' => $voucherData['customer_id'],
                'user_id' => Auth::id(),
                'customer_type' => 'retail',
                'subtotal' => $subtotal,
                'discount_amount' => $totalDiscount,
                'total_amount' => $grandTotal,
                'payment_type' => $paymentType,
                'payment_status' => $paymentStatus,
                'status' => 'confirm',
                'due_amount' => $billingType === 'credit' ? $grandTotal : 0,
                'notes' => $voucherData['notes'] ?? null,
                'tax_amount' => $totalTax,
                'voucher_date' => $voucherData['date'] ?? now()->toDateString(),
                'salesman_id' => $voucherData['salesman_id'] ?? null,
                'billing_type' => $billingType,
            ]);

            // 3. Save items & deduct stock via FIFO
            $totalCOGS = 0;

            foreach ($items as $item) {
                $product = ProductDetail::find($item['product_id']);
                if (!$product) continue;

                // Deduct stock using FIFO
                $costPriceAtSale = 0;
                try {
                    $deduction = FIFOStockService::deductStock(
                        $item['product_id'],
                        $item['quantity'],
                        $item['variant_id'] ?? null,
                        $item['variant_value'] ?? null
                    );
                    $costPriceAtSale = $deduction['total_cost'] ?? 0;
                    $totalCOGS += $costPriceAtSale;
                } catch (\Exception $e) {
                    Log::warning('FIFO deduction failed for product ' . $item['product_id'] . ': ' . $e->getMessage());
                    // Fallback: deduct from ProductStock directly
                    InventoryService::deductStockDirect(
                        $item['product_id'],
                        $item['quantity'],
                        $item['variant_id'] ?? null,
                        $item['variant_value'] ?? null
                    );
                }

                SaleItem::create([
                    'sale_id' => $sale->id,
                    'product_id' => $item['product_id'],
                    'product_code' => $product->code ?? '',
                    'product_name' => $product->name ?? '',
                    'product_model' => $product->model->name ?? '',
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['rate'],
                    'discount_per_unit' => $item['discount'] ?? 0,
                    'total_discount' => ($item['discount'] ?? 0) * $item['quantity'],
                    'total' => $item['line_net'],
                    'variant_id' => $item['variant_id'] ?? null,
                    'variant_value' => $item['variant_value'] ?? null,
                    'cost_price_at_sale' => $costPriceAtSale > 0
                        ? round($costPriceAtSale / $item['quantity'], 2)
                        : 0,
                    'tax_amount' => $item['tax_amount'] ?? 0,
                    'tax_percentage' => $item['tax_percentage'] ?? 0,
                ]);
            }

            // 4. Post accounting entries
            try {
                AccountingService::postSale($sale, $totalCOGS);
            } catch (\Exception $e) {
                Log::warning('Accounting posting failed for sale ' . $sale->id . ': ' . $e->getMessage());
            }

            // 5. Update customer balance for credit sales
            if ($billingType === 'credit' && $sale->customer_id) {
                $customer = Customer::find($sale->customer_id);
                if ($customer) {
                    $customer->increment('due_amount', $grandTotal);
                    if ($customer->total_due !== null) {
                        $customer->increment('total_due', $grandTotal);
                    }
                }
            }

            return $sale->fresh(['items', 'customer']);
        });
    }

    /**
     * Modify an existing Sales Voucher.
     * Reverses old transactions, applies new ones.
     *
     * @param  int    $saleId
     * @param  array  $voucherData
     * @param  array  $items
     * @return Sale
     */
    public static function modifySalesVoucher(int $saleId, array $voucherData, array $items): Sale
    {
        return DB::transaction(function () use ($saleId, $voucherData, $items) {
            $sale = Sale::with(['items', 'customer'])->findOrFail($saleId);

            // 1. Reverse accounting entries
            self::reverseVoucherAccounting($sale);

            // 2. Restore stock for old items
            self::restoreStock($sale);

            // 3. Reverse customer balance adjustment
            if ($sale->billing_type === 'credit' && $sale->customer_id) {
                $oldCustomer = Customer::find($sale->customer_id);
                if ($oldCustomer) {
                    $oldCustomer->decrement('due_amount', min($oldCustomer->due_amount, $sale->total_amount));
                    if ($oldCustomer->total_due !== null) {
                        $oldCustomer->decrement('total_due', min($oldCustomer->total_due, $sale->total_amount));
                    }
                }
            }

            // 4. Delete old sale items
            $sale->items()->delete();

            // 5. Recalculate and save new data
            $subtotal = 0;
            $totalDiscount = 0;
            $totalTax = 0;

            foreach ($items as &$item) {
                $lineTotal = $item['quantity'] * $item['rate'];
                $lineDiscount = ($item['discount'] ?? 0) * $item['quantity'];
                $lineTax = ($item['tax_amount'] ?? 0);
                $lineNet = $lineTotal - $lineDiscount + $lineTax;

                $item['line_total'] = $lineTotal;
                $item['line_discount'] = $lineDiscount;
                $item['line_tax'] = $lineTax;
                $item['line_net'] = $lineNet;

                $subtotal += $lineTotal;
                $totalDiscount += $lineDiscount;
                $totalTax += $lineTax;
            }
            unset($item);

            $grandTotal = $subtotal - $totalDiscount + $totalTax;
            $billingType = $voucherData['billing_type'] ?? 'cash';

            // Update sale record
            $sale->update([
                'customer_id' => $voucherData['customer_id'],
                'subtotal' => $subtotal,
                'discount_amount' => $totalDiscount,
                'total_amount' => $grandTotal,
                'payment_type' => $billingType === 'cash' ? 'full' : 'partial',
                'payment_status' => $billingType === 'cash' ? 'paid' : 'pending',
                'due_amount' => $billingType === 'credit' ? $grandTotal : 0,
                'notes' => $voucherData['notes'] ?? null,
                'tax_amount' => $totalTax,
                'voucher_date' => $voucherData['date'] ?? $sale->voucher_date,
                'salesman_id' => $voucherData['salesman_id'] ?? null,
                'billing_type' => $billingType,
            ]);

            // 6. Create new items & deduct stock
            $totalCOGS = 0;

            foreach ($items as $item) {
                $product = ProductDetail::find($item['product_id']);
                if (!$product) continue;

                $costPriceAtSale = 0;
                try {
                    $deduction = FIFOStockService::deductStock(
                        $item['product_id'],
                        $item['quantity'],
                        $item['variant_id'] ?? null,
                        $item['variant_value'] ?? null
                    );
                    $costPriceAtSale = $deduction['total_cost'] ?? 0;
                    $totalCOGS += $costPriceAtSale;
                } catch (\Exception $e) {
                    Log::warning('FIFO deduction failed: ' . $e->getMessage());
                    InventoryService::deductStockDirect(
                        $item['product_id'],
                        $item['quantity'],
                        $item['variant_id'] ?? null,
                        $item['variant_value'] ?? null
                    );
                }

                SaleItem::create([
                    'sale_id' => $sale->id,
                    'product_id' => $item['product_id'],
                    'product_code' => $product->code ?? '',
                    'product_name' => $product->name ?? '',
                    'product_model' => $product->model->name ?? '',
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['rate'],
                    'discount_per_unit' => $item['discount'] ?? 0,
                    'total_discount' => ($item['discount'] ?? 0) * $item['quantity'],
                    'total' => $item['line_net'],
                    'variant_id' => $item['variant_id'] ?? null,
                    'variant_value' => $item['variant_value'] ?? null,
                    'cost_price_at_sale' => $costPriceAtSale > 0
                        ? round($costPriceAtSale / $item['quantity'], 2)
                        : 0,
                    'tax_amount' => $item['tax_amount'] ?? 0,
                    'tax_percentage' => $item['tax_percentage'] ?? 0,
                ]);
            }

            // 7. Post new accounting entries
            try {
                AccountingService::postSale($sale->fresh(), $totalCOGS);
            } catch (\Exception $e) {
                Log::warning('Accounting posting failed: ' . $e->getMessage());
            }

            // 8. Update customer balance for credit sales
            if ($billingType === 'credit' && $sale->customer_id) {
                $customer = Customer::find($sale->customer_id);
                if ($customer) {
                    $customer->increment('due_amount', $grandTotal);
                    if ($customer->total_due !== null) {
                        $customer->increment('total_due', $grandTotal);
                    }
                }
            }

            return $sale->fresh(['items', 'customer']);
        });
    }

    /**
     * Delete / Cancel a Sales Voucher.
     */
    public static function deleteSalesVoucher(int $saleId): bool
    {
        return DB::transaction(function () use ($saleId) {
            $sale = Sale::with(['items', 'customer'])->findOrFail($saleId);

            // 1. Reverse accounting
            self::reverseVoucherAccounting($sale);

            // 2. Restore stock
            self::restoreStock($sale);

            // 3. Reverse customer balance
            if ($sale->billing_type === 'credit' && $sale->customer_id) {
                $customer = Customer::find($sale->customer_id);
                if ($customer) {
                    $customer->decrement('due_amount', min($customer->due_amount, $sale->total_amount));
                    if ($customer->total_due !== null) {
                        $customer->decrement('total_due', min($customer->total_due, $sale->total_amount));
                    }
                }
            }

            // 4. Soft-delete or mark as cancelled
            $sale->update(['status' => 'cancelled']);
            $sale->items()->delete();

            return true;
        });
    }

    /**
     * Reverse the accounting voucher linked to a sale.
     */
    private static function reverseVoucherAccounting(Sale $sale): void
    {
        // Find the linked accounting voucher
        $voucher = Voucher::where('reference_type', 'sale')
            ->where('reference_id', $sale->id)
            ->where('is_posted', true)
            ->whereNull('deleted_at')
            ->first();

        if ($voucher) {
            try {
                AccountingService::reverseVoucher($voucher, 'Voucher modification: ' . $sale->invoice_number);
            } catch (\Exception $e) {
                Log::warning('Voucher reversal failed: ' . $e->getMessage());
            }
        }
    }

    /**
     * Restore stock for all items in a sale.
     */
    private static function restoreStock(Sale $sale): void
    {
        foreach ($sale->items as $item) {
            try {
                InventoryService::restoreStock(
                    $item->product_id,
                    $item->quantity,
                    $item->variant_id,
                    $item->variant_value
                );
            } catch (\Exception $e) {
                Log::warning('Stock restore failed for product ' . $item->product_id . ': ' . $e->getMessage());
            }
        }
    }

    // ╔══════════════════════════════════════════════════════════════╗
    // ║  PURCHASE VOUCHER OPERATIONS                                ║
    // ╚══════════════════════════════════════════════════════════════╝

    /**
     * Save a new Purchase Voucher.
     *
     * @param  array  $voucherData  [date, supplier_id, billing_type, transport_cost, notes, invoice_number]
     * @param  array  $items        [['product_id','variant_id','variant_value','quantity','free_qty','rate','discount','tax_amount','tax_percentage',
     *                                'wholesale_price','distributor_price','retail_price'], ...]
     * @return PurchaseOrder
     */
    public static function createPurchaseVoucher(array $voucherData, array $items): PurchaseOrder
    {
        return DB::transaction(function () use ($voucherData, $items) {
            $purchaseDate = $voucherData['date'] ?? now()->toDateString();
            $billingType = $voucherData['billing_type'] ?? 'cash';

            // 1. Generate order code & invoice number
            $year = Carbon::parse($purchaseDate)->format('Ymd');
            $lastOrder = PurchaseOrder::where('order_code', 'like', 'ORD-' . $year . '-%')
                ->orderByDesc('order_code')
                ->first();

            if ($lastOrder && preg_match('/ORD-' . $year . '-(\d+)/', $lastOrder->order_code, $matches)) {
                $nextNumber = intval($matches[1]) + 1;
            } else {
                $nextNumber = 1;
            }
            $orderCode = 'ORD-' . $year . '-' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);

            $invoiceNumber = $voucherData['invoice_number'] ?? self::generatePurchaseInvoiceNumber();

            // 2. Calculate totals
            $subtotal = 0;
            $totalTax = 0;
            $totalDiscount = 0;

            foreach ($items as &$item) {
                $lineTotal = $item['quantity'] * $item['rate'];
                $lineDiscount = ($item['discount'] ?? 0) * $item['quantity'];
                $lineTax = ($item['tax_amount'] ?? 0);
                $lineNet = $lineTotal - $lineDiscount + $lineTax;

                $item['line_total'] = $lineTotal;
                $item['line_discount'] = $lineDiscount;
                $item['line_tax'] = $lineTax;
                $item['line_net'] = $lineNet;

                $subtotal += $lineTotal;
                $totalDiscount += $lineDiscount;
                $totalTax += $lineTax;
            }
            unset($item);

            $grandTotal = $subtotal - $totalDiscount + $totalTax;

            // 3. Create PurchaseOrder record
            $po = PurchaseOrder::create([
                'order_code' => $orderCode,
                'invoice_number' => $invoiceNumber,
                'supplier_id' => $voucherData['supplier_id'],
                'order_date' => $purchaseDate,
                'status' => 'complete',
                'total_amount' => $grandTotal,
                'transport_cost' => floatval($voucherData['transport_cost'] ?? 0),
                'payment_type' => $billingType,
                'discount_amount' => $totalDiscount,
            ]);

            // 4. Create line items & increase stock
            $inventoryValue = 0;

            foreach ($items as $item) {
                $product = ProductDetail::find($item['product_id']);
                if (!$product) continue;

                $qty = intval($item['quantity']);
                $freeQty = intval($item['free_qty'] ?? 0);
                $totalQty = $qty + $freeQty;
                $rate = floatval($item['rate']);
                $displayName = $product->name;

                if (!empty($item['variant_value'])) {
                    $displayName .= ' - ' . $item['variant_value'];
                }

                // Create purchase order item
                PurchaseOrderItem::create([
                    'order_id' => $po->id,
                    'product_id' => $item['product_id'],
                    'variant_id' => $item['variant_id'] ?? null,
                    'variant_value' => $item['variant_value'] ?? null,
                    'quantity' => $qty,
                    'free_qty' => $freeQty,
                    'received_quantity' => $totalQty,
                    'unit_price' => $rate,
                    'discount' => $item['discount'] ?? 0,
                    'status' => 'received',
                ]);

                // Increase stock — create batch + increment ProductStock
                InventoryService::increaseStock(
                    $item['product_id'],
                    $totalQty,
                    $rate,
                    $po->id,
                    $item['variant_id'] ?? null,
                    $item['variant_value'] ?? null
                );

                // Update product prices if provided
                self::updateProductPrices($item);

                $inventoryValue += ($qty * $rate);
            }

            // 5. Post accounting entries (Dr Inventory, Cr Supplier)
            try {
                $po->tax_amount = $totalTax; // set transient for postPurchase
                AccountingService::postPurchase($po, $inventoryValue);
            } catch (\Exception $e) {
                Log::warning('Accounting posting failed for purchase ' . $po->order_code . ': ' . $e->getMessage());
            }

            // 6. Update supplier balance for credit purchases
            if ($billingType === 'credit' && $po->supplier_id) {
                $supplier = ProductSupplier::find($po->supplier_id);
                if ($supplier) {
                    $supplier->increment('due_amount', $grandTotal);
                }
            }

            Log::info("Purchase Voucher created: {$po->order_code} / {$invoiceNumber} - Total: {$grandTotal}");

            return $po->fresh(['items', 'supplier']);
        });
    }

    /**
     * Modify an existing Purchase Voucher.
     * Reverses old stock + accounting, then applies new values.
     */
    public static function modifyPurchaseVoucher(int $poId, array $voucherData, array $items): PurchaseOrder
    {
        return DB::transaction(function () use ($poId, $voucherData, $items) {
            $po = PurchaseOrder::with(['items', 'supplier'])->findOrFail($poId);

            // 1. Reverse accounting entries
            self::reversePurchaseVoucherAccounting($po);

            // 2. Reverse stock for old items
            self::reversePurchaseStock($po);

            // 3. Reverse supplier balance
            $oldBillingType = $po->payment_type ?? 'cash';
            if ($oldBillingType === 'credit' && $po->supplier_id) {
                $supplier = ProductSupplier::find($po->supplier_id);
                if ($supplier && $supplier->due_amount > 0) {
                    $supplier->decrement('due_amount', min($supplier->due_amount, floatval($po->total_amount)));
                }
            }

            // 4. Delete old items
            $po->items()->delete();

            // 5. Recalculate totals
            $purchaseDate = $voucherData['date'] ?? $po->order_date;
            $billingType = $voucherData['billing_type'] ?? 'cash';

            $subtotal = 0;
            $totalTax = 0;
            $totalDiscount = 0;

            foreach ($items as &$item) {
                $lineTotal = $item['quantity'] * $item['rate'];
                $lineDiscount = ($item['discount'] ?? 0) * $item['quantity'];
                $lineTax = ($item['tax_amount'] ?? 0);
                $lineNet = $lineTotal - $lineDiscount + $lineTax;

                $item['line_total'] = $lineTotal;
                $item['line_discount'] = $lineDiscount;
                $item['line_tax'] = $lineTax;
                $item['line_net'] = $lineNet;

                $subtotal += $lineTotal;
                $totalDiscount += $lineDiscount;
                $totalTax += $lineTax;
            }
            unset($item);

            $grandTotal = $subtotal - $totalDiscount + $totalTax;

            // 6. Update PO record
            $po->update([
                'supplier_id' => $voucherData['supplier_id'],
                'order_date' => $purchaseDate,
                'total_amount' => $grandTotal,
                'transport_cost' => floatval($voucherData['transport_cost'] ?? 0),
                'payment_type' => $billingType,
                'discount_amount' => $totalDiscount,
                'invoice_number' => $voucherData['invoice_number'] ?? $po->invoice_number,
            ]);

            // 7. Create new items & increase stock
            $inventoryValue = 0;

            foreach ($items as $item) {
                $product = ProductDetail::find($item['product_id']);
                if (!$product) continue;

                $qty = intval($item['quantity']);
                $freeQty = intval($item['free_qty'] ?? 0);
                $totalQty = $qty + $freeQty;
                $rate = floatval($item['rate']);

                PurchaseOrderItem::create([
                    'order_id' => $po->id,
                    'product_id' => $item['product_id'],
                    'variant_id' => $item['variant_id'] ?? null,
                    'variant_value' => $item['variant_value'] ?? null,
                    'quantity' => $qty,
                    'free_qty' => $freeQty,
                    'received_quantity' => $totalQty,
                    'unit_price' => $rate,
                    'discount' => $item['discount'] ?? 0,
                    'status' => 'received',
                ]);

                InventoryService::increaseStock(
                    $item['product_id'],
                    $totalQty,
                    $rate,
                    $po->id,
                    $item['variant_id'] ?? null,
                    $item['variant_value'] ?? null
                );

                self::updateProductPrices($item);

                $inventoryValue += ($qty * $rate);
            }

            // 8. Post new accounting entries
            try {
                $po->tax_amount = $totalTax;
                AccountingService::postPurchase($po->fresh(), $inventoryValue);
            } catch (\Exception $e) {
                Log::warning('Accounting posting failed: ' . $e->getMessage());
            }

            // 9. Update supplier balance for credit
            if ($billingType === 'credit' && $po->supplier_id) {
                $supplier = ProductSupplier::find($po->supplier_id);
                if ($supplier) {
                    $supplier->increment('due_amount', $grandTotal);
                }
            }

            Log::info("Purchase Voucher modified: {$po->order_code}");

            return $po->fresh(['items', 'supplier']);
        });
    }

    /**
     * Delete / Cancel a Purchase Voucher.
     */
    public static function deletePurchaseVoucher(int $poId): bool
    {
        return DB::transaction(function () use ($poId) {
            $po = PurchaseOrder::with(['items', 'supplier'])->findOrFail($poId);

            // 1. Reverse accounting
            self::reversePurchaseVoucherAccounting($po);

            // 2. Reverse stock
            self::reversePurchaseStock($po);

            // 3. Reverse supplier balance
            if (($po->payment_type ?? 'cash') === 'credit' && $po->supplier_id) {
                $supplier = ProductSupplier::find($po->supplier_id);
                if ($supplier && $supplier->due_amount > 0) {
                    $supplier->decrement('due_amount', min($supplier->due_amount, floatval($po->total_amount)));
                }
            }

            // 4. Cancel
            $po->update(['status' => 'cancelled']);
            $po->items()->delete();

            Log::info("Purchase Voucher deleted: {$po->order_code}");

            return true;
        });
    }

    /**
     * Reverse accounting voucher linked to a PurchaseOrder.
     */
    private static function reversePurchaseVoucherAccounting(PurchaseOrder $po): void
    {
        $voucher = Voucher::where('reference_type', 'purchase_order')
            ->where('reference_id', $po->id)
            ->where('is_posted', true)
            ->whereNull('deleted_at')
            ->first();

        if ($voucher) {
            try {
                AccountingService::reverseVoucher($voucher, 'Purchase modification: ' . $po->order_code);
            } catch (\Exception $e) {
                Log::warning('Purchase voucher reversal failed: ' . $e->getMessage());
            }
        }
    }

    /**
     * Reverse stock for all items in a purchase order (decrease stock).
     */
    private static function reversePurchaseStock(PurchaseOrder $po): void
    {
        foreach ($po->items as $item) {
            try {
                $totalQty = intval($item->received_quantity ?: ($item->quantity + ($item->free_qty ?? 0)));
                InventoryService::decreaseStock(
                    $item->product_id,
                    $totalQty,
                    $item->variant_id,
                    $item->variant_value
                );
            } catch (\Exception $e) {
                Log::warning('Stock reversal failed for product ' . $item->product_id . ': ' . $e->getMessage());
            }
        }
    }

    /**
     * Update product prices from purchase item data.
     */
    private static function updateProductPrices(array $item): void
    {
        try {
            $priceQuery = ProductPrice::where('product_id', $item['product_id']);
            if (!empty($item['variant_value'])) {
                $priceQuery->where('variant_value', $item['variant_value']);
            }
            $price = $priceQuery->first();

            if ($price) {
                $updates = ['supplier_price' => floatval($item['rate'])];
                if (!empty($item['wholesale_price'])) $updates['wholesale_price'] = floatval($item['wholesale_price']);
                if (!empty($item['distributor_price'])) $updates['distributor_price'] = floatval($item['distributor_price']);
                if (!empty($item['retail_price'])) $updates['retail_price'] = floatval($item['retail_price']);
                $price->update($updates);
            }
        } catch (\Exception $e) {
            Log::warning('Price update failed for product ' . $item['product_id'] . ': ' . $e->getMessage());
        }
    }

    /**
     * Generate next purchase invoice number.
     */
    public static function generatePurchaseInvoiceNumber(): string
    {
        $last = PurchaseOrder::whereNotNull('invoice_number')
            ->orderByDesc('invoice_number')
            ->value('invoice_number');

        if ($last && preg_match('/INV-(\d+)/', $last, $m)) {
            $next = intval($m[1]) + 1;
        } else {
            $next = 1;
        }

        return 'INV-' . str_pad($next, 4, '0', STR_PAD_LEFT);
    }
}
