<?php

namespace App\Services;

use App\Models\Account;
use App\Models\ProductStock;
use App\Models\ProductSupplier;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Models\ReturnSupplier;
use App\Models\Voucher;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PurchaseReturnService
{
    public function createPurchaseReturn(array $payload): PurchaseReturn
    {
        return DB::transaction(function () use ($payload) {
            $purchaseId = (int) Arr::get($payload, 'purchase_id');
            $order = PurchaseOrder::with(['supplier', 'items.product'])
                ->lockForUpdate()
                ->findOrFail($purchaseId);

            $returnType = Arr::get($payload, 'return_type', 'debit_note');
            $this->validateReturnType($returnType);

            $items = collect(Arr::get($payload, 'items', []))
                ->filter(fn($item) => (float) ($item['return_qty'] ?? 0) > 0)
                ->values();

            if ($items->isEmpty()) {
                throw new InvalidArgumentException('At least one return item is required.');
            }

            $return = PurchaseReturn::create([
                'return_no' => Arr::get($payload, 'return_no') ?: PurchaseReturn::generateReturnNo(),
                'return_date' => Arr::get($payload, 'return_date') ?: now()->toDateString(),
                'supplier_id' => Arr::get($payload, 'supplier_id') ?: $order->supplier_id,
                'purchase_id' => $order->id,
                'subtotal' => 0,
                'overall_discount' => (float) Arr::get($payload, 'overall_discount', 0),
                'tax_total' => 0,
                'grand_total' => 0,
                'return_type' => $returnType,
                'notes' => Arr::get($payload, 'notes'),
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);

            $totals = $this->applyItems($order, $return, $items, true);
            $this->applyTotals($return, $totals['subtotal'], $totals['tax_total'], (float) Arr::get($payload, 'overall_discount', 0));

            $voucher = $this->applyFinancialAndLedger($order, $return, true);
            if ($voucher) {
                $return->voucher_id = $voucher->id;
                $return->save();
            }

            return $return->load(['supplier', 'purchaseOrder', 'items.product']);
        });
    }

    public function updatePurchaseReturn(int $purchaseReturnId, array $payload): PurchaseReturn
    {
        return DB::transaction(function () use ($purchaseReturnId, $payload) {
            $return = PurchaseReturn::with(['items', 'purchaseOrder.supplier'])
                ->lockForUpdate()
                ->findOrFail($purchaseReturnId);

            $order = PurchaseOrder::with(['supplier', 'items.product'])
                ->lockForUpdate()
                ->findOrFail($return->purchase_id);

            $this->reverseExistingImpact($return, $order);

            $returnType = Arr::get($payload, 'return_type', $return->return_type);
            $this->validateReturnType($returnType);

            $items = collect(Arr::get($payload, 'items', []))
                ->filter(fn($item) => (float) ($item['return_qty'] ?? 0) > 0)
                ->values();

            if ($items->isEmpty()) {
                throw new InvalidArgumentException('At least one return item is required.');
            }

            $return->update([
                'return_date' => Arr::get($payload, 'return_date', $return->return_date?->format('Y-m-d')),
                'supplier_id' => Arr::get($payload, 'supplier_id', $return->supplier_id),
                'return_type' => $returnType,
                'notes' => Arr::get($payload, 'notes', $return->notes),
                'updated_by' => Auth::id(),
                'voucher_id' => null,
            ]);

            $return->items()->delete();

            $totals = $this->applyItems($order, $return, $items, false);
            $this->applyTotals($return, $totals['subtotal'], $totals['tax_total'], (float) Arr::get($payload, 'overall_discount', $return->overall_discount));

            $voucher = $this->applyFinancialAndLedger($order, $return, false);
            if ($voucher) {
                $return->voucher_id = $voucher->id;
                $return->save();
            }

            return $return->load(['supplier', 'purchaseOrder', 'items.product']);
        });
    }

    public function deletePurchaseReturn(int $purchaseReturnId): void
    {
        DB::transaction(function () use ($purchaseReturnId) {
            $return = PurchaseReturn::with(['items', 'purchaseOrder.supplier', 'voucher.entries'])
                ->lockForUpdate()
                ->findOrFail($purchaseReturnId);

            $order = PurchaseOrder::with('supplier')->lockForUpdate()->findOrFail($return->purchase_id);
            $this->reverseExistingImpact($return, $order);

            $return->items()->delete();
            $return->delete();
        });
    }

    private function applyItems(PurchaseOrder $order, PurchaseReturn $return, $items, bool $syncLegacy): array
    {
        $subtotal = 0.0;
        $taxTotal = 0.0;

        foreach ($items as $itemInput) {
            $orderItemId = (int) ($itemInput['purchase_order_item_id'] ?? 0);
            $orderItem = PurchaseOrderItem::where('order_id', $order->id)
                ->where('id', $orderItemId)
                ->lockForUpdate()
                ->first();

            if (!$orderItem) {
                throw new InvalidArgumentException('Invalid purchase item selected.');
            }

            $legacyReturned = $this->getLegacyReturnedQty($order->id, $orderItem);
            $alreadyReturned = max((float) ($orderItem->returned_qty ?? 0), $legacyReturned);
            $purchasedQty = (float) ($orderItem->received_quantity ?? $orderItem->quantity ?? 0);
            $balanceQty = max(0, $purchasedQty - $alreadyReturned);
            $returnQty = (float) ($itemInput['return_qty'] ?? 0);

            if ($returnQty <= 0) {
                continue;
            }

            if ($returnQty > $balanceQty) {
                throw new InvalidArgumentException('Return quantity exceeds allowed balance for selected item.');
            }

            $rate = (float) ($itemInput['rate'] ?? $orderItem->unit_price ?? 0);
            $discount = (float) ($itemInput['discount'] ?? 0);
            $tax = (float) ($itemInput['tax'] ?? 0);

            $gross = round($returnQty * $rate, 2);
            $lineTotal = round(max(0, $gross - $discount + $tax), 2);

            PurchaseReturnItem::create([
                'purchase_return_id' => $return->id,
                'purchase_order_item_id' => $orderItem->id,
                'purchase_id' => $order->id,
                'product_id' => $orderItem->product_id,
                'variant_id' => $orderItem->variant_id,
                'variant_value' => $orderItem->variant_value,
                'purchased_qty' => $purchasedQty,
                'already_returned_qty' => $alreadyReturned,
                'balance_returnable_qty' => $balanceQty,
                'return_qty' => $returnQty,
                'rate' => $rate,
                'discount_amount' => $discount,
                'tax_amount' => $tax,
                'line_total' => $lineTotal,
            ]);

            if ($syncLegacy) {
                ReturnSupplier::create([
                    'purchase_order_id' => $order->id,
                    'product_id' => $orderItem->product_id,
                    'variant_id' => $orderItem->variant_id,
                    'variant_value' => $orderItem->variant_value,
                    'return_quantity' => $returnQty,
                    'unit_price' => $rate,
                    'total_amount' => $lineTotal,
                    'return_reason' => 'other',
                    'notes' => 'PRN ' . $return->return_no,
                ]);
            }

            $orderItem->returned_qty = round($alreadyReturned + $returnQty, 3);
            $orderItem->save();

            $this->reduceStockForReturn($orderItem, $returnQty);

            $subtotal += max(0, $gross - $discount);
            $taxTotal += $tax;
        }

        return [
            'subtotal' => $subtotal,
            'tax_total' => $taxTotal,
        ];
    }

    private function applyTotals(PurchaseReturn $return, float $subtotal, float $taxTotal, float $overallDiscount): void
    {
        $return->subtotal = round($subtotal, 2);
        $return->tax_total = round($taxTotal, 2);
        $return->overall_discount = round(max(0, $overallDiscount), 2);
        $return->grand_total = round(max(0, $return->subtotal - $return->overall_discount + $return->tax_total), 2);
        $return->save();
    }

    private function applyFinancialAndLedger(PurchaseOrder $order, PurchaseReturn $return, bool $syncLegacy): ?Voucher
    {
        if ($return->return_type !== 'replacement') {
            $reduction = (float) $return->grand_total;
            $order->total_amount = max(0, (float) ($order->total_amount ?? 0) - $reduction);
            $order->due_amount = max(0, (float) ($order->due_amount ?? 0) - $reduction);
            $order->save();

            if ($order->supplier) {
                $supplier = ProductSupplier::find($order->supplier_id);
                if ($supplier && $return->return_type === 'debit_note') {
                    $supplier->overpayment = (float) ($supplier->overpayment ?? 0) + $reduction;
                    $supplier->save();
                }
            }
        }

        $voucher = AccountingService::postPurchaseReturn(
            $order,
            (float) $return->grand_total,
            null,
            $return->return_type
        );

        if ($syncLegacy && $return->return_type !== 'replacement' && $order->supplier_id) {
            $supplier = ProductSupplier::find($order->supplier_id);
            if ($supplier) {
                // Keep old behavior available for reports that use overpayment
                $supplier->addOverpayment((float) $return->grand_total);
            }
        }

        return $voucher;
    }

    private function reverseExistingImpact(PurchaseReturn $return, PurchaseOrder $order): void
    {
        foreach ($return->items as $item) {
            $orderItem = PurchaseOrderItem::where('id', $item->purchase_order_item_id)->lockForUpdate()->first();
            if ($orderItem) {
                $currentReturned = (float) ($orderItem->returned_qty ?? 0);
                $orderItem->returned_qty = max(0, $currentReturned - (float) $item->return_qty);
                $orderItem->save();
            }

            $this->restoreStockOnReverse($item->product_id, $item->variant_id, $item->variant_value, (float) $item->return_qty);
        }

        if ($return->return_type !== 'replacement') {
            $amount = (float) $return->grand_total;
            $order->total_amount = (float) ($order->total_amount ?? 0) + $amount;
            $order->due_amount = (float) ($order->due_amount ?? 0) + $amount;
            $order->save();

            if ($return->return_type === 'debit_note') {
                $supplier = ProductSupplier::find($order->supplier_id);
                if ($supplier) {
                    $supplier->overpayment = max(0, (float) ($supplier->overpayment ?? 0) - $amount);
                    $supplier->save();
                }
            }
        }

        if ($return->voucher_id) {
            $voucher = Voucher::with('entries')->find($return->voucher_id);
            if ($voucher) {
                AccountingService::reverseVoucher($voucher, 'Purchase return update/delete reversal');
            }
        }
    }

    private function reduceStockForReturn(PurchaseOrderItem $orderItem, float $qty): void
    {
        $query = ProductStock::where('product_id', $orderItem->product_id);
        if (!empty($orderItem->variant_id)) {
            $query->where('variant_id', $orderItem->variant_id);
        }
        if (!empty($orderItem->variant_value)) {
            $query->where('variant_value', $orderItem->variant_value);
        }

        $stock = $query->first();
        if (!$stock) {
            $stock = ProductStock::where('product_id', $orderItem->product_id)->first();
        }

        if (!$stock) {
            throw new InvalidArgumentException('Stock record not found for returned purchase item.');
        }

        if ((float) $stock->available_stock < $qty) {
            throw new InvalidArgumentException('Insufficient stock to return this quantity to supplier.');
        }

        $stock->available_stock = max(0, (float) $stock->available_stock - $qty);
        $stock->updateTotals();
    }

    private function restoreStockOnReverse(int $productId, ?int $variantId, ?string $variantValue, float $qty): void
    {
        $query = ProductStock::where('product_id', $productId);
        if (!empty($variantId)) {
            $query->where('variant_id', $variantId);
        }
        if (!empty($variantValue)) {
            $query->where('variant_value', $variantValue);
        }

        $stock = $query->first();
        if (!$stock) {
            $stock = ProductStock::where('product_id', $productId)->first();
        }

        if (!$stock) {
            $stock = ProductStock::create([
                'product_id' => $productId,
                'variant_id' => $variantId,
                'variant_value' => $variantValue,
                'available_stock' => 0,
                'damage_stock' => 0,
                'total_stock' => 0,
                'sold_count' => 0,
                'restocked_quantity' => 0,
            ]);
        }

        $stock->available_stock = (float) $stock->available_stock + $qty;
        $stock->updateTotals();
    }

    private function getLegacyReturnedQty(int $purchaseId, PurchaseOrderItem $item): float
    {
        $query = ReturnSupplier::where('purchase_order_id', $purchaseId)
            ->where('product_id', $item->product_id);

        if ($item->variant_id) {
            $query->where('variant_id', $item->variant_id);
        } else {
            $query->whereNull('variant_id');
        }

        if ($item->variant_value) {
            $query->where('variant_value', $item->variant_value);
        } else {
            $query->where(function ($q) {
                $q->whereNull('variant_value')->orWhere('variant_value', '');
            });
        }

        return (float) $query->sum('return_quantity');
    }

    private function validateReturnType(string $type): void
    {
        if (!in_array($type, ['cash_refund', 'debit_note', 'replacement'], true)) {
            throw new InvalidArgumentException('Invalid return type.');
        }
    }
}
