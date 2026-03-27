<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\WithDynamicLayout;
use App\Models\ReturnsProduct;
use App\Models\SaleItem;
use App\Models\SalesReturn;
use App\Models\SalesReturnItem;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Sales Return Modify')]
#[Layout('components.layouts.admin')]
class SalesReturnModify extends Component
{
    use WithDynamicLayout, WithPagination;

    public string $returnSearch = '';
    public int $perPage = 10;

    public ?SalesReturn $selectedReturn = null;
    public ?int $currentReturnId = null;

    public ?int $editingReturnId = null;
    public string $editReturnDate = '';
    public string $editRefundType = 'cash';
    public float $editOverallDiscount = 0;
    public ?float $editCashRefundAmount = null;
    public string $editReturnNotes = '';
    public array $editItems = [];

    public float $editSubtotal = 0;
    public float $editTaxTotal = 0;
    public float $editGrandTotal = 0;

    public function updatedReturnSearch(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    public function showReceipt(int $salesReturnId): void
    {
        $this->selectedReturn = SalesReturn::with(['sale.customer', 'items.product'])->find($salesReturnId);
        $this->currentReturnId = $salesReturnId;
        $this->dispatch('showModal', 'receiptModal');
    }

    public function editReturn(int $salesReturnId): void
    {
        $salesReturn = SalesReturn::with(['items.product'])->find($salesReturnId);

        if (!$salesReturn) {
            $this->dispatch('showToast', ['type' => 'error', 'message' => 'Return record not found.']);
            return;
        }

        $this->editingReturnId = $salesReturn->id;
        $this->editReturnDate = optional($salesReturn->return_date)->format('Y-m-d') ?: now()->toDateString();
        $this->editRefundType = (string) $salesReturn->refund_type;
        $this->editOverallDiscount = (float) $salesReturn->overall_discount;
        $this->editCashRefundAmount = $salesReturn->cash_refund_amount !== null ? (float) $salesReturn->cash_refund_amount : null;
        $this->editReturnNotes = (string) ($salesReturn->notes ?? '');

        $this->editItems = [];
        foreach ($salesReturn->items as $item) {
            if (!$item instanceof SalesReturnItem) {
                continue;
            }

            $this->editItems[] = [
                'id' => (int) $item->id,
                'sale_item_id' => (int) ($item->sale_item_id ?? 0),
                'product_name' => ($item->product?->name ?? 'Product') . ($item->variant_value ? ' (' . $item->variant_value . ')' : ''),
                'return_qty' => (float) $item->return_qty,
                'rate' => (float) $item->rate,
                'discount_amount' => (float) $item->discount_amount,
                'tax_amount' => (float) $item->tax_amount,
                'line_total' => (float) $item->line_total,
            ];
        }

        $this->recalculateEditTotals();
        $this->dispatch('showModal', 'editReturnModal');
    }

    public function updatedEditItems(): void
    {
        foreach ($this->editItems as $i => $item) {
            $qty = max(0, (float) ($item['return_qty'] ?? 0));
            $rate = max(0, (float) ($item['rate'] ?? 0));
            $discount = max(0, (float) ($item['discount_amount'] ?? 0));
            $tax = max(0, (float) ($item['tax_amount'] ?? 0));

            $lineTotal = max(0, ($qty * $rate) - $discount + $tax);

            $this->editItems[$i]['return_qty'] = $qty;
            $this->editItems[$i]['rate'] = $rate;
            $this->editItems[$i]['discount_amount'] = $discount;
            $this->editItems[$i]['tax_amount'] = $tax;
            $this->editItems[$i]['line_total'] = round($lineTotal, 2);
        }

        $this->recalculateEditTotals();
    }

    public function updatedEditOverallDiscount(): void
    {
        $this->editOverallDiscount = max(0, (float) $this->editOverallDiscount);
        $this->recalculateEditTotals();
    }

    public function updateReturn(): void
    {
        if (!$this->editingReturnId) {
            $this->dispatch('showToast', ['type' => 'error', 'message' => 'No return selected for update.']);
            return;
        }

        $this->validate([
            'editReturnDate' => 'required|date',
            'editRefundType' => 'required|in:cash,credit_note,replacement',
            'editOverallDiscount' => 'nullable|numeric|min:0',
            'editCashRefundAmount' => 'nullable|numeric|min:0',
            'editReturnNotes' => 'nullable|string|max:1000',
            'editItems' => 'required|array|min:1',
            'editItems.*.return_qty' => 'required|numeric|min:0.001',
            'editItems.*.rate' => 'required|numeric|min:0',
            'editItems.*.discount_amount' => 'nullable|numeric|min:0',
            'editItems.*.tax_amount' => 'nullable|numeric|min:0',
        ]);

        $salesReturn = SalesReturn::with('items')->find($this->editingReturnId);

        if (!$salesReturn) {
            $this->dispatch('showToast', ['type' => 'error', 'message' => 'Return record not found.']);
            return;
        }

        $this->recalculateEditTotals();

        DB::transaction(function () use ($salesReturn) {
            $salesReturn->update([
                'return_date' => $this->editReturnDate,
                'refund_type' => $this->editRefundType,
                'overall_discount' => $this->editOverallDiscount,
                'cash_refund_amount' => $this->editRefundType === 'cash' ? ($this->editCashRefundAmount ?? $this->editGrandTotal) : null,
                'notes' => $this->editReturnNotes ?: null,
                'subtotal' => $this->editSubtotal,
                'tax_total' => $this->editTaxTotal,
                'grand_total' => $this->editGrandTotal,
            ]);

            foreach ($this->editItems as $item) {
                SalesReturnItem::where('id', $item['id'])->update([
                    'return_qty' => (float) $item['return_qty'],
                    'rate' => (float) $item['rate'],
                    'discount_amount' => (float) ($item['discount_amount'] ?? 0),
                    'tax_amount' => (float) ($item['tax_amount'] ?? 0),
                    'line_total' => (float) ($item['line_total'] ?? 0),
                ]);
            }

            // Sync legacy rows by replacing all rows linked through SRN note.
            ReturnsProduct::where('sale_id', $salesReturn->sale_id)
                ->where('notes', 'SRN ' . $salesReturn->return_no)
                ->delete();

            $items = SalesReturnItem::where('sales_return_id', $salesReturn->id)->get();
            foreach ($items as $item) {
                ReturnsProduct::create([
                    'sale_id' => $salesReturn->sale_id,
                    'product_id' => $item->product_id,
                    'variant_id' => $item->variant_id,
                    'variant_value' => $item->variant_value,
                    'return_quantity' => $item->return_qty,
                    'selling_price' => $item->rate,
                    'total_amount' => $item->line_total,
                    'notes' => 'SRN ' . $salesReturn->return_no,
                ]);
            }

            $this->syncSaleItemReturnedQuantities((int) $salesReturn->sale_id);
        });

        $this->dispatch('hideModal', 'editReturnModal');
        $this->dispatch('showToast', ['type' => 'success', 'message' => 'Sales return updated successfully.']);
    }

    public function deleteReturn(int $salesReturnId): void
    {
        $this->selectedReturn = SalesReturn::with(['sale.customer', 'items.product'])->find($salesReturnId);
        $this->currentReturnId = $salesReturnId;
        $this->dispatch('showModal', 'deleteReturnModal');
    }

    public function confirmDeleteReturn(): void
    {
        if (!$this->currentReturnId) {
            return;
        }

        $salesReturn = SalesReturn::find($this->currentReturnId);
        if (!$salesReturn) {
            $this->dispatch('showToast', ['type' => 'error', 'message' => 'Return record not found.']);
            return;
        }

        DB::transaction(function () use ($salesReturn) {
            ReturnsProduct::where('sale_id', $salesReturn->sale_id)
                ->where('notes', 'SRN ' . $salesReturn->return_no)
                ->delete();

            SalesReturnItem::where('sales_return_id', $salesReturn->id)->delete();
            $salesReturn->delete();

            $this->syncSaleItemReturnedQuantities((int) $salesReturn->sale_id);
        });

        $this->closeModal();
        $this->dispatch('showToast', ['type' => 'success', 'message' => 'Sales return deleted successfully.']);
    }

    public function closeModal(): void
    {
        $this->selectedReturn = null;
        $this->currentReturnId = null;
        $this->editingReturnId = null;
        $this->editItems = [];

        $this->dispatch('hideModal', 'receiptModal');
        $this->dispatch('hideModal', 'deleteReturnModal');
        $this->dispatch('hideModal', 'editReturnModal');
    }

    public function printReceipt(): void
    {
        $this->dispatch('printReceipt');
    }

    private function recalculateEditTotals(): void
    {
        $subtotal = 0;
        $taxTotal = 0;

        foreach ($this->editItems as $item) {
            $qty = (float) ($item['return_qty'] ?? 0);
            $rate = (float) ($item['rate'] ?? 0);
            $discount = (float) ($item['discount_amount'] ?? 0);
            $tax = (float) ($item['tax_amount'] ?? 0);

            $subtotal += max(0, ($qty * $rate) - $discount);
            $taxTotal += max(0, $tax);
        }

        $this->editSubtotal = round($subtotal, 2);
        $this->editTaxTotal = round($taxTotal, 2);
        $this->editGrandTotal = round(max(0, $this->editSubtotal - (float) $this->editOverallDiscount + $this->editTaxTotal), 2);

        if ($this->editRefundType === 'cash' && ($this->editCashRefundAmount === null || (float) $this->editCashRefundAmount <= 0)) {
            $this->editCashRefundAmount = $this->editGrandTotal;
        }
    }

    private function syncSaleItemReturnedQuantities(int $saleId): void
    {
        $saleItems = SaleItem::where('sale_id', $saleId)->get();

        foreach ($saleItems as $saleItem) {
            $returnedQty = (float) SalesReturnItem::where('sale_id', $saleId)
                ->where('sale_item_id', $saleItem->id)
                ->sum('return_qty');

            $saleItem->returned_qty = round(max(0, $returnedQty), 3);
            $saleItem->save();
        }
    }

    public function render()
    {
        $query = SalesReturn::with(['sale.customer', 'items'])
            ->orderByDesc('return_date')
            ->orderByDesc('id');

        if (!empty($this->returnSearch)) {
            $search = '%' . trim($this->returnSearch) . '%';

            $query->where(function ($q) use ($search) {
                $q->where('return_no', 'like', $search)
                    ->orWhereHas('sale', function ($sq) use ($search) {
                        $sq->where('invoice_number', 'like', $search);
                    })
                    ->orWhereHas('customer', function ($cq) use ($search) {
                        $cq->where('name', 'like', $search)
                            ->orWhere('phone', 'like', $search);
                    });
            });
        }

        $returns = $query->paginate($this->perPage);

        return view('livewire.admin.sales-return-modify', [
            'returns' => $returns,
            'selectedReturn' => $this->selectedReturn,
            'currentReturnId' => $this->currentReturnId,
        ])->layout($this->layout, ['erpContext' => 'voucher']);
    }
}
