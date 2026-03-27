<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\WithDynamicLayout;
use App\Models\Customer;
use App\Models\ReturnsProduct;
use App\Models\Sale;
use App\Models\SalesReturn;
use App\Models\SalesReturnItem;
use App\Services\SalesReturnService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Sales Return Add')]
#[Layout('components.layouts.admin')]
class SalesReturnAdd extends Component
{
    use WithDynamicLayout;

    public string $returnNo = '';
    public string $returnDate = '';

    public string $customerSearch = '';
    public array $customerResults = [];
    public ?int $selectedCustomerId = null;

    public string $invoiceSearch = '';
    public array $invoiceResults = [];
    public ?int $selectedInvoiceId = null;

    public array $saleItems = [];
    public array $invoiceHistory = [];

    public float $subtotal = 0;
    public float $overallDiscount = 0;
    public float $taxTotal = 0;
    public float $grandTotal = 0;

    public string $refundType = 'cash';
    public ?float $cashRefundAmount = null;
    public string $notes = '';

    public function mount(): void
    {
        $this->returnNo = SalesReturn::generateReturnNo();
        $this->returnDate = now()->toDateString();
    }

    public function updatedCustomerSearch(): void
    {
        $term = trim($this->customerSearch);
        if (strlen($term) < 2) {
            $this->customerResults = [];
            return;
        }

        $this->customerResults = Customer::query()
            ->where('name', 'like', '%' . $term . '%')
            ->orWhere('phone', 'like', '%' . $term . '%')
            ->limit(10)
            ->get(['id', 'name', 'phone'])
            ->toArray();
    }

    public function selectCustomer(int $customerId): void
    {
        $this->selectedCustomerId = $customerId;
        $customer = Customer::find($customerId);
        $this->customerSearch = $customer ? ($customer->name . ' (' . ($customer->phone ?? '-') . ')') : '';
        $this->customerResults = [];
        $this->selectedInvoiceId = null;
        $this->saleItems = [];
        $this->invoiceHistory = [];
        $this->recalculateTotals();
    }

    public function updatedInvoiceSearch(): void
    {
        $term = trim($this->invoiceSearch);
        if (strlen($term) < 1) {
            $this->invoiceResults = [];
            return;
        }

        $query = Sale::with('customer')->orderByDesc('id');

        if ($this->selectedCustomerId) {
            $query->where('customer_id', $this->selectedCustomerId);
        }

        $this->invoiceResults = $query
            ->where('invoice_number', 'like', '%' . $term . '%')
            ->limit(15)
            ->get()
            ->map(function (Sale $sale) {
                return [
                    'id' => $sale->id,
                    'invoice_number' => $sale->invoice_number,
                    'customer_name' => $sale->customer?->name ?? 'Walk-in',
                    'date' => optional($sale->created_at)->format('Y-m-d'),
                    'total' => (float) $sale->total_amount,
                ];
            })->toArray();
    }

    public function selectInvoice(int $saleId): void
    {
        $sale = Sale::with(['customer', 'items.product'])->find($saleId);
        if (!$sale) {
            return;
        }

        $this->selectedInvoiceId = $sale->id;
        $this->invoiceSearch = (string) $sale->invoice_number;
        $this->invoiceResults = [];

        if ($sale->customer_id) {
            $this->selectedCustomerId = $sale->customer_id;
            $this->customerSearch = $sale->customer?->name . ' (' . ($sale->customer?->phone ?? '-') . ')';
        }

        $lines = [];
        foreach ($sale->items as $item) {
            $voucherReturned = $this->voucherReturnedQty((int) $item->id);
            $legacyReturned = $this->legacyReturnedQty($sale->id, (int) $item->product_id, $item->variant_id, $item->variant_value);

            // New sales-return vouchers are authoritative for current system flow.
            // Fall back to legacy table only when no voucher rows exist for this item.
            $alreadyReturned = $voucherReturned > 0
                ? $voucherReturned
                : max((float) ($item->returned_qty ?? 0), $legacyReturned);

            $soldQty = (float) ($item->quantity ?? 0);
            $balance = max(0, $soldQty - $alreadyReturned);
            $rate = (float) ($item->unit_price ?? 0);

            $productName = $item->product_name ?: ($item->product->name ?? 'Product');
            if (!empty($item->variant_value)) {
                $productName .= ' (' . $item->variant_value . ')';
            }

            $lines[] = [
                'sale_item_id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $productName,
                'sold_qty' => $soldQty,
                'already_returned_qty' => $alreadyReturned,
                'balance_returnable_qty' => $balance,
                'return_qty' => 0,
                'rate' => $rate,
                'discount' => 0,
                'tax' => 0,
                'line_total' => 0,
            ];
        }

        $this->saleItems = $lines;

        $this->loadInvoiceHistory($sale->id);
        $this->recalculateTotals();
    }

    public function updatedSaleItems(): void
    {
        foreach ($this->saleItems as $index => $item) {
            $qty = (float) ($item['return_qty'] ?? 0);
            $balance = (float) ($item['balance_returnable_qty'] ?? 0);

            if ($qty < 0) {
                $qty = 0;
            }

            if ($qty > $balance) {
                $qty = $balance;
            }

            $rate = max(0, (float) ($item['rate'] ?? 0));
            $discount = max(0, (float) ($item['discount'] ?? 0));
            $tax = max(0, (float) ($item['tax'] ?? 0));
            $gross = $qty * $rate;
            $line = max(0, $gross - $discount + $tax);

            $this->saleItems[$index]['return_qty'] = $qty;
            $this->saleItems[$index]['rate'] = $rate;
            $this->saleItems[$index]['discount'] = $discount;
            $this->saleItems[$index]['tax'] = $tax;
            $this->saleItems[$index]['line_total'] = round($line, 2);
        }

        $this->recalculateTotals();
    }

    public function updatedOverallDiscount(): void
    {
        $this->overallDiscount = max(0, (float) $this->overallDiscount);
        $this->recalculateTotals();
    }

    public function updatedRefundType(): void
    {
        if ($this->refundType !== 'cash') {
            $this->cashRefundAmount = null;
        }
    }

    public function save(): void
    {
        $this->persist(false);
    }

    public function saveAndPrint(): void
    {
        $this->persist(true);
    }

    public function cancel(): void
    {
        $this->resetForm();
        $this->dispatch('showToast', ['type' => 'success', 'message' => 'Sales return entry cancelled.']);
    }

    private function persist(bool $print): void
    {
        $this->validate([
            'returnNo' => 'required|string|max:50',
            'returnDate' => 'required|date',
            'selectedInvoiceId' => 'required|integer|exists:sales,id',
            'selectedCustomerId' => 'nullable|integer|exists:customers,id',
            'refundType' => 'required|in:cash,credit_note,replacement',
            'overallDiscount' => 'nullable|numeric|min:0',
            'cashRefundAmount' => 'nullable|numeric|min:0',
            'saleItems' => 'required|array|min:1',
            'saleItems.*.sale_item_id' => 'required|integer',
            'saleItems.*.return_qty' => 'nullable|numeric|min:0',
            'saleItems.*.rate' => 'nullable|numeric|min:0',
            'saleItems.*.discount' => 'nullable|numeric|min:0',
            'saleItems.*.tax' => 'nullable|numeric|min:0',
        ]);

        $itemsToSubmit = collect($this->saleItems)
            ->filter(fn($item) => (float) ($item['return_qty'] ?? 0) > 0)
            ->values()
            ->toArray();

        if (empty($itemsToSubmit)) {
            $this->addError('saleItems', 'Enter at least one return quantity.');
            return;
        }

        if ($this->refundType === 'cash' && ((float) ($this->cashRefundAmount ?? 0) <= 0)) {
            $this->addError('cashRefundAmount', 'Cash refund amount is required for cash refund.');
            return;
        }

        $service = app(SalesReturnService::class);

        try {
            $salesReturn = $service->createSalesReturn([
                'return_no' => $this->returnNo,
                'return_date' => $this->returnDate,
                'sale_id' => $this->selectedInvoiceId,
                'customer_id' => $this->selectedCustomerId,
                'items' => $itemsToSubmit,
                'overall_discount' => $this->overallDiscount,
                'refund_type' => $this->refundType,
                'cash_refund_amount' => $this->cashRefundAmount,
                'notes' => $this->notes,
            ]);

            if ($print) {
                $this->redirectRoute('admin.sales-return-print', ['salesReturn' => $salesReturn->id]);
                return;
            }

            $this->resetForm();
            $this->dispatch('showToast', ['type' => 'success', 'message' => 'Sales Return saved successfully.']);
        } catch (\Throwable $e) {
            $this->dispatch('showToast', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    private function recalculateTotals(): void
    {
        $sub = 0.0;
        $tax = 0.0;

        foreach ($this->saleItems as $item) {
            $qty = (float) ($item['return_qty'] ?? 0);
            $rate = (float) ($item['rate'] ?? 0);
            $discount = (float) ($item['discount'] ?? 0);
            $itemTax = (float) ($item['tax'] ?? 0);

            if ($qty <= 0) {
                continue;
            }

            $sub += max(0, ($qty * $rate) - $discount);
            $tax += max(0, $itemTax);
        }

        $this->subtotal = round($sub, 2);
        $this->taxTotal = round($tax, 2);
        $this->grandTotal = round(max(0, $this->subtotal - (float) $this->overallDiscount + $this->taxTotal), 2);

        if ($this->refundType === 'cash' && ($this->cashRefundAmount === null || (float) $this->cashRefundAmount <= 0)) {
            $this->cashRefundAmount = $this->grandTotal;
        }
    }

    private function loadInvoiceHistory(int $saleId): void
    {
        $history = SalesReturn::with('items')
            ->where('sale_id', $saleId)
            ->latest('id')
            ->limit(10)
            ->get()
            ->map(function (SalesReturn $return) {
                return [
                    'return_no' => $return->return_no,
                    'date' => optional($return->return_date)->format('Y-m-d'),
                    'refund_type' => $return->refund_type,
                    'total' => (float) $return->grand_total,
                    'items' => $return->items->sum('return_qty'),
                ];
            })->toArray();

        $legacy = ReturnsProduct::where('sale_id', $saleId)
            ->where(function ($q) {
                $q->whereNull('notes')
                    ->orWhere('notes', 'not like', 'SRN %');
            })
            ->selectRaw('DATE(created_at) as date, SUM(total_amount) as total, SUM(return_quantity) as qty')
            ->groupBy('date')
            ->orderByDesc('date')
            ->limit(5)
            ->get()
            ->map(fn($row) => [
                'return_no' => 'LEGACY',
                'date' => $row->date,
                'refund_type' => 'legacy',
                'total' => (float) $row->total,
                'items' => (float) $row->qty,
            ])->toArray();

        $this->invoiceHistory = array_merge($history, $legacy);
    }

    private function legacyReturnedQty(int $saleId, int $productId, ?int $variantId, ?string $variantValue): float
    {
        $query = ReturnsProduct::where('sale_id', $saleId)
            ->where('product_id', $productId)
            ->where(function ($q) {
                $q->whereNull('notes')
                    ->orWhere('notes', 'not like', 'SRN %');
            });

        if (!empty($variantId)) {
            $query->where('variant_id', $variantId);
        } else {
            $query->whereNull('variant_id');
        }

        if (!empty($variantValue)) {
            $query->where('variant_value', $variantValue);
        } else {
            $query->where(function ($q) {
                $q->whereNull('variant_value')->orWhere('variant_value', '');
            });
        }

        return (float) $query->sum('return_quantity');
    }

    private function voucherReturnedQty(int $saleItemId): float
    {
        return (float) SalesReturnItem::where('sale_item_id', $saleItemId)->sum('return_qty');
    }

    private function resetForm(): void
    {
        $this->returnNo = SalesReturn::generateReturnNo();
        $this->returnDate = now()->toDateString();
        $this->customerSearch = '';
        $this->customerResults = [];
        $this->selectedCustomerId = null;
        $this->invoiceSearch = '';
        $this->invoiceResults = [];
        $this->selectedInvoiceId = null;
        $this->saleItems = [];
        $this->invoiceHistory = [];
        $this->subtotal = 0;
        $this->overallDiscount = 0;
        $this->taxTotal = 0;
        $this->grandTotal = 0;
        $this->refundType = 'cash';
        $this->cashRefundAmount = null;
        $this->notes = '';
    }

    public function render()
    {
        return view('livewire.admin.sales-return-add');
    }
}
