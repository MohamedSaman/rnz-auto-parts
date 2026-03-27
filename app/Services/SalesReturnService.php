<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\ProductStock;
use App\Models\ReturnsProduct;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalesReturn;
use App\Models\SalesReturnItem;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SalesReturnService
{
    public function createSalesReturn(array $payload): SalesReturn
    {
        return DB::transaction(function () use ($payload) {
            $saleId = (int) Arr::get($payload, 'sale_id');
            $sale = Sale::with(['customer', 'items.product'])
                ->lockForUpdate()
                ->findOrFail($saleId);

            $refundType = Arr::get($payload, 'refund_type', 'cash');
            if (!in_array($refundType, ['cash', 'credit_note', 'replacement'], true)) {
                throw new InvalidArgumentException('Invalid refund type.');
            }

            $rawItems = collect(Arr::get($payload, 'items', []))
                ->filter(fn($item) => (float) ($item['return_qty'] ?? 0) > 0)
                ->values();

            if ($rawItems->isEmpty()) {
                throw new InvalidArgumentException('At least one return item is required.');
            }

            $returnNo = Arr::get($payload, 'return_no') ?: SalesReturn::generateReturnNo();
            $returnDate = Arr::get($payload, 'return_date') ?: now()->toDateString();

            $salesReturn = SalesReturn::create([
                'return_no' => $returnNo,
                'return_date' => $returnDate,
                'sale_id' => $sale->id,
                'customer_id' => Arr::get($payload, 'customer_id') ?: $sale->customer_id,
                'subtotal' => 0,
                'overall_discount' => (float) Arr::get($payload, 'overall_discount', 0),
                'tax_total' => 0,
                'grand_total' => 0,
                'refund_type' => $refundType,
                'cash_refund_amount' => Arr::get($payload, 'cash_refund_amount') !== null
                    ? (float) Arr::get($payload, 'cash_refund_amount')
                    : null,
                'notes' => Arr::get($payload, 'notes'),
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);

            $subtotal = 0.0;
            $taxTotal = 0.0;

            foreach ($rawItems as $itemInput) {
                $saleItemId = (int) ($itemInput['sale_item_id'] ?? 0);
                $saleItem = SaleItem::where('sale_id', $sale->id)
                    ->where('id', $saleItemId)
                    ->lockForUpdate()
                    ->first();

                if (!$saleItem) {
                    throw new InvalidArgumentException('Invalid sale item selected.');
                }

                $legacyReturned = $this->getLegacyReturnedQty($sale->id, $saleItem);
                $alreadyReturned = max((float) ($saleItem->returned_qty ?? 0), $legacyReturned);
                $soldQty = (float) ($saleItem->quantity ?? 0);
                $balanceQty = max(0, $soldQty - $alreadyReturned);
                $returnQty = (float) ($itemInput['return_qty'] ?? 0);

                if ($returnQty <= 0) {
                    continue;
                }

                if ($returnQty > $balanceQty) {
                    throw new InvalidArgumentException('Return quantity exceeds allowed balance for ' . ($saleItem->product_name ?? 'item') . '.');
                }

                $rate = (float) ($itemInput['rate'] ?? $saleItem->unit_price ?? 0);
                $discount = (float) ($itemInput['discount'] ?? 0);
                $tax = (float) ($itemInput['tax'] ?? 0);

                $gross = round($returnQty * $rate, 2);
                $lineTotal = round(max(0, $gross - $discount + $tax), 2);

                SalesReturnItem::create([
                    'sales_return_id' => $salesReturn->id,
                    'sale_item_id' => $saleItem->id,
                    'sale_id' => $sale->id,
                    'product_id' => $saleItem->product_id,
                    'variant_id' => $saleItem->variant_id,
                    'variant_value' => $saleItem->variant_value,
                    'sold_qty' => $soldQty,
                    'already_returned_qty' => $alreadyReturned,
                    'balance_returnable_qty' => $balanceQty,
                    'return_qty' => $returnQty,
                    'rate' => $rate,
                    'discount_amount' => $discount,
                    'tax_amount' => $tax,
                    'line_total' => $lineTotal,
                ]);

                // Keep old table in sync for backward compatibility with existing list/modify pages
                ReturnsProduct::create([
                    'sale_id' => $sale->id,
                    'product_id' => $saleItem->product_id,
                    'variant_id' => $saleItem->variant_id,
                    'variant_value' => $saleItem->variant_value,
                    'return_quantity' => $returnQty,
                    'selling_price' => $rate,
                    'total_amount' => $lineTotal,
                    'notes' => 'SRN ' . $returnNo,
                ]);

                $saleItem->returned_qty = round($alreadyReturned + $returnQty, 3);
                $saleItem->save();

                $this->restoreStock($saleItem, $returnQty);

                $subtotal += max(0, $gross - $discount);
                $taxTotal += $tax;
            }

            $overallDiscount = (float) Arr::get($payload, 'overall_discount', 0);
            $grandTotal = round(max(0, $subtotal - $overallDiscount + $taxTotal), 2);

            if ($refundType === 'cash') {
                $cashAmount = (float) Arr::get($payload, 'cash_refund_amount', $grandTotal);
                if ($cashAmount <= 0) {
                    throw new InvalidArgumentException('Cash refund amount is required for cash refund.');
                }
                $salesReturn->cash_refund_amount = $cashAmount;
            }

            $salesReturn->subtotal = round($subtotal, 2);
            $salesReturn->tax_total = round($taxTotal, 2);
            $salesReturn->overall_discount = round($overallDiscount, 2);
            $salesReturn->grand_total = $grandTotal;
            $salesReturn->save();

            $this->applyFinancialAdjustments($sale, $salesReturn);

            return $salesReturn->load(['items', 'sale.customer']);
        });
    }

    private function getLegacyReturnedQty(int $saleId, SaleItem $saleItem): float
    {
        $query = ReturnsProduct::where('sale_id', $saleId)
            ->where('product_id', $saleItem->product_id);

        if ($saleItem->variant_id) {
            $query->where('variant_id', $saleItem->variant_id);
        } else {
            $query->whereNull('variant_id');
        }

        if ($saleItem->variant_value) {
            $query->where('variant_value', $saleItem->variant_value);
        } else {
            $query->where(function ($q) {
                $q->whereNull('variant_value')->orWhere('variant_value', '');
            });
        }

        return (float) $query->sum('return_quantity');
    }

    private function restoreStock(SaleItem $saleItem, float $qty): void
    {
        $stockQuery = ProductStock::where('product_id', $saleItem->product_id);

        if (!empty($saleItem->variant_id)) {
            $stockQuery->where('variant_id', $saleItem->variant_id);
        }

        if (!empty($saleItem->variant_value)) {
            $stockQuery->where('variant_value', $saleItem->variant_value);
        }

        $stock = $stockQuery->first();
        if (!$stock) {
            $stock = ProductStock::where('product_id', $saleItem->product_id)->first();
        }

        if (!$stock) {
            $stock = ProductStock::create([
                'product_id' => $saleItem->product_id,
                'variant_id' => $saleItem->variant_id,
                'variant_value' => $saleItem->variant_value,
                'available_stock' => 0,
                'damage_stock' => 0,
                'total_stock' => 0,
                'sold_count' => 0,
                'restocked_quantity' => 0,
            ]);
        }

        $stock->available_stock = (float) $stock->available_stock + $qty;
        $stock->sold_count = max(0, (float) $stock->sold_count - $qty);
        $stock->updateTotals();
    }

    private function applyFinancialAdjustments(Sale $sale, SalesReturn $salesReturn): void
    {
        if ($salesReturn->refund_type === 'replacement') {
            return;
        }

        $amount = (float) $salesReturn->grand_total;
        $sale->subtotal = max(0, (float) ($sale->subtotal ?? 0) - $amount);
        $sale->total_amount = max(0, (float) ($sale->total_amount ?? 0) - $amount);
        $sale->due_amount = max(0, (float) ($sale->due_amount ?? 0) - $amount);
        $sale->save();

        if ($sale->customer_id) {
            $customer = Customer::find($sale->customer_id);
            if ($customer) {
                $customer->due_amount = max(0, (float) ($customer->due_amount ?? 0) - $amount);
                $customer->total_due = (float) ($customer->opening_balance ?? 0) + (float) ($customer->due_amount ?? 0);
                $customer->save();
            }
        }

        AccountingService::postSalesReturn(
            $sale,
            $amount,
            0,
            $sale->branch_id,
            $salesReturn->refund_type
        );
    }
}
