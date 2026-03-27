<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\WithDynamicLayout;
use App\Models\ProductSupplier;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReturn;
use App\Models\ReturnSupplier;
use App\Services\PurchaseReturnService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Purchase Return Add')]
#[Layout('components.layouts.admin')]
class PurchaseReturnAdd extends Component
{
    use WithDynamicLayout;

    public string $returnNo = '';
    public string $returnDate = '';

    public string $supplierSearch = '';
    public array $supplierResults = [];
    public ?int $selectedSupplierId = null;

    public string $invoiceSearch = '';
    public array $invoiceResults = [];
    public ?int $selectedPurchaseId = null;

    public array $purchaseItems = [];
    public array $invoiceHistory = [];

    public float $subtotal = 0;
    public float $overallDiscount = 0;
    public float $taxTotal = 0;
    public float $grandTotal = 0;

    public string $returnType = 'debit_note';
    public string $notes = '';

    public function mount(): void
    {
        $this->returnNo = PurchaseReturn::generateReturnNo();
        $this->returnDate = now()->toDateString();
    }

    public function updatedSupplierSearch(): void
    {
        $term = trim($this->supplierSearch);
        if (strlen($term) < 2) {
            $this->supplierResults = [];
            return;
        }

        $this->supplierResults = ProductSupplier::query()
            ->where('name', 'like', '%' . $term . '%')
            ->orWhere('phone', 'like', '%' . $term . '%')
            ->limit(10)
            ->get(['id', 'name', 'phone'])
            ->toArray();
    }

    public function selectSupplier(int $supplierId): void
    {
        $this->selectedSupplierId = $supplierId;
        $supplier = ProductSupplier::find($supplierId);
        $this->supplierSearch = $supplier ? ($supplier->name . ' (' . ($supplier->phone ?? '-') . ')') : '';
        $this->supplierResults = [];
        $this->selectedPurchaseId = null;
        $this->purchaseItems = [];
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

        $query = PurchaseOrder::with('supplier')->orderByDesc('id');
        if ($this->selectedSupplierId) {
            $query->where('supplier_id', $this->selectedSupplierId);
        }

        $this->invoiceResults = $query
            ->where(function ($q) use ($term) {
                $q->where('order_code', 'like', '%' . $term . '%')
                    ->orWhere('invoice_number', 'like', '%' . $term . '%');
            })
            ->limit(15)
            ->get()
            ->map(function (PurchaseOrder $po) {
                return [
                    'id' => $po->id,
                    'order_code' => $po->order_code,
                    'invoice_number' => $po->invoice_number,
                    'supplier_name' => $po->supplier?->name ?? '-',
                    'date' => optional($po->order_date)->format('Y-m-d') ?: optional($po->created_at)->format('Y-m-d'),
                    'total' => (float) $po->total_amount,
                ];
            })->toArray();
    }

    public function selectPurchaseInvoice(int $purchaseId): void
    {
        $po = PurchaseOrder::with(['supplier', 'items.product'])->find($purchaseId);
        if (!$po) {
            return;
        }

        $this->selectedPurchaseId = $po->id;
        $this->invoiceSearch = (string) ($po->invoice_number ?: $po->order_code);
        $this->invoiceResults = [];

        if ($po->supplier_id) {
            $this->selectedSupplierId = $po->supplier_id;
            $this->supplierSearch = $po->supplier?->name . ' (' . ($po->supplier?->phone ?? '-') . ')';
        }

        $lines = [];
        foreach ($po->items as $item) {
            $legacyReturned = $this->legacyReturnedQty($po->id, (int) $item->product_id, $item->variant_id, $item->variant_value);
            $alreadyReturned = max((float) ($item->returned_qty ?? 0), $legacyReturned);
            $purchasedQty = (float) ($item->received_quantity ?? $item->quantity ?? 0);
            $balance = max(0, $purchasedQty - $alreadyReturned);

            $productName = $item->product?->name ?? 'Product';
            if (!empty($item->variant_value)) {
                $productName .= ' (' . $item->variant_value . ')';
            }

            $lines[] = [
                'purchase_order_item_id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $productName,
                'purchased_qty' => $purchasedQty,
                'already_returned_qty' => $alreadyReturned,
                'balance_returnable_qty' => $balance,
                'return_qty' => 0,
                'rate' => (float) ($item->unit_price ?? 0),
                'discount' => 0,
                'tax' => 0,
                'line_total' => 0,
            ];
        }

        $this->purchaseItems = $lines;
        $this->loadInvoiceHistory($po->id);
        $this->recalculateTotals();
    }

    public function updatedPurchaseItems(): void
    {
        foreach ($this->purchaseItems as $index => $item) {
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

            $this->purchaseItems[$index]['return_qty'] = $qty;
            $this->purchaseItems[$index]['rate'] = $rate;
            $this->purchaseItems[$index]['discount'] = $discount;
            $this->purchaseItems[$index]['tax'] = $tax;
            $this->purchaseItems[$index]['line_total'] = round($line, 2);
        }

        $this->recalculateTotals();
    }

    public function updatedOverallDiscount(): void
    {
        $this->overallDiscount = max(0, (float) $this->overallDiscount);
        $this->recalculateTotals();
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
        $this->dispatch('showToast', ['type' => 'success', 'message' => 'Purchase return entry cancelled.']);
    }

    private function persist(bool $print): void
    {
        $this->validate([
            'returnNo' => 'required|string|max:50',
            'returnDate' => 'required|date',
            'selectedPurchaseId' => 'required|integer|exists:purchase_orders,id',
            'selectedSupplierId' => 'required|integer|exists:product_suppliers,id',
            'returnType' => 'required|in:cash_refund,debit_note,replacement',
            'overallDiscount' => 'nullable|numeric|min:0',
            'purchaseItems' => 'required|array|min:1',
            'purchaseItems.*.purchase_order_item_id' => 'required|integer',
            'purchaseItems.*.return_qty' => 'nullable|numeric|min:0',
            'purchaseItems.*.rate' => 'nullable|numeric|min:0',
            'purchaseItems.*.discount' => 'nullable|numeric|min:0',
            'purchaseItems.*.tax' => 'nullable|numeric|min:0',
        ]);

        $itemsToSubmit = collect($this->purchaseItems)
            ->filter(fn ($item) => (float) ($item['return_qty'] ?? 0) > 0)
            ->values()
            ->toArray();

        if (empty($itemsToSubmit)) {
            $this->addError('purchaseItems', 'Enter at least one return quantity.');
            return;
        }

        $service = app(PurchaseReturnService::class);

        try {
            $return = $service->createPurchaseReturn([
                'return_no' => $this->returnNo,
                'return_date' => $this->returnDate,
                'purchase_id' => $this->selectedPurchaseId,
                'supplier_id' => $this->selectedSupplierId,
                'return_type' => $this->returnType,
                'overall_discount' => $this->overallDiscount,
                'items' => $itemsToSubmit,
                'notes' => $this->notes,
            ]);

            if ($print) {
                $this->redirectRoute('admin.purchase-return-print', ['purchaseReturn' => $return->id]);
                return;
            }

            $this->resetForm();
            $this->dispatch('showToast', ['type' => 'success', 'message' => 'Purchase return saved successfully.']);
        } catch (\Throwable $e) {
            $this->dispatch('showToast', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    private function recalculateTotals(): void
    {
        $sub = 0.0;
        $tax = 0.0;

        foreach ($this->purchaseItems as $item) {
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
        $this->grandTotal = round(max(0, $this->subtotal - $this->overallDiscount + $this->taxTotal), 2);
    }

    private function loadInvoiceHistory(int $purchaseId): void
    {
        $history = PurchaseReturn::with('items')
            ->where('purchase_id', $purchaseId)
            ->latest('id')
            ->limit(10)
            ->get()
            ->map(function (PurchaseReturn $return) {
                return [
                    'return_no' => $return->return_no,
                    'date' => optional($return->return_date)->format('Y-m-d'),
                    'return_type' => $return->return_type,
                    'total' => (float) $return->grand_total,
                    'items' => $return->items->sum('return_qty'),
                ];
            })->toArray();

        $legacy = ReturnSupplier::where('purchase_order_id', $purchaseId)
            ->selectRaw('DATE(created_at) as date, SUM(total_amount) as total, SUM(return_quantity) as qty')
            ->groupBy('date')
            ->orderByDesc('date')
            ->limit(5)
            ->get()
            ->map(fn ($row) => [
                'return_no' => 'LEGACY',
                'date' => $row->date,
                'return_type' => 'legacy',
                'total' => (float) $row->total,
                'items' => (float) $row->qty,
            ])->toArray();

        $this->invoiceHistory = array_merge($history, $legacy);
    }

    private function legacyReturnedQty(int $purchaseId, int $productId, ?int $variantId, ?string $variantValue): float
    {
        $query = ReturnSupplier::where('purchase_order_id', $purchaseId)
            ->where('product_id', $productId);

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

    private function resetForm(): void
    {
        $this->returnNo = PurchaseReturn::generateReturnNo();
        $this->returnDate = now()->toDateString();
        $this->supplierSearch = '';
        $this->supplierResults = [];
        $this->selectedSupplierId = null;
        $this->invoiceSearch = '';
        $this->invoiceResults = [];
        $this->selectedPurchaseId = null;
        $this->purchaseItems = [];
        $this->invoiceHistory = [];
        $this->subtotal = 0;
        $this->overallDiscount = 0;
        $this->taxTotal = 0;
        $this->grandTotal = 0;
        $this->returnType = 'debit_note';
        $this->notes = '';
    }

    public function render()
    {
        return view('livewire.admin.purchase-return-add');
    }
}
