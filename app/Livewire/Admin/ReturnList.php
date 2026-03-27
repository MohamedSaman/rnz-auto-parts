<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use App\Models\ReturnsProduct;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Livewire\Concerns\WithDynamicLayout;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

#[Title("Product Return")]
class ReturnList extends Component
{
    use WithDynamicLayout, WithPagination;


    // Do not store the full collection/paginator in a public property
    public $returnsCount = 0;
    public $returnSearch = '';
    public $selectedReturn = null;
    public $showReceiptModal = false;
    public $currentReturnId = null;
    public $perPage = 10;

    // Edit return state
    public $editingReturnId = null;
    public $editReturnQuantity = 0;
    public $editReturnPrice = 0;
    public $editReturnNotes = '';

    public function mount()
    {
        // Load lightweight count; actual paginated data is returned from render()
        $this->loadReturns();
    }

    protected function loadReturns()
    {
        $query = ReturnsProduct::with(['sale', 'product']);

        // Filter by user for staff - only show returns for their own sales
        if ($this->isStaff()) {
            $query->whereHas('sale', function ($q) {
                $q->where('user_id', Auth::id());
            });
        }

        if (!empty($this->returnSearch)) {
            $search = '%' . $this->returnSearch . '%';
            $query->where(function ($q) use ($search) {
                $q->whereHas('sale', function ($sq) use ($search) {
                    $sq->where('invoice_number', 'like', $search);
                })->orWhereHas('product', function ($pq) use ($search) {
                    $pq->where('name', 'like', $search)
                        ->orWhere('code', 'like', $search);
                });
            });
        }
        $this->returnsCount = $query->count();
    }

    public function updatedReturnSearch()
    {
        $this->resetPage();
        $this->loadReturns();
    }

    public function showReturnDetails($id)
    {
        $this->selectedReturn = ReturnsProduct::with(['sale', 'product'])->find($id);
        $this->dispatch('showModal', 'returnDetailsModal');
    }

    public function showReceipt($returnId)
    {
        $this->selectedReturn = ReturnsProduct::with(['sale.customer', 'product'])->find($returnId);
        $this->currentReturnId = $returnId;
        $this->showReceiptModal = true;
        $this->dispatch('showModal', 'receiptModal');
    }

    public function downloadReturn($returnId)
    {
        $return = ReturnsProduct::with(['sale.customer', 'product'])->find($returnId);

        if (!$return) {
            $this->dispatch('showToast', ['type' => 'error', 'message' => 'Return record not found.']);
            return;
        }

        try {
            $pdf = PDF::loadView('admin.returns.return-receipt', compact('return'));

            $pdf->setPaper('a4', 'portrait');
            $pdf->setOption('dpi', 150);
            $pdf->setOption('defaultFont', 'sans-serif');

            return response()->streamDownload(
                function () use ($pdf) {
                    echo $pdf->output();
                },
                'return-receipt-' . $return->id . '-' . now()->format('Y-m-d') . '.pdf'
            );
        } catch (\Exception $e) {
            $this->dispatch('showToast', ['type' => 'error', 'message' => 'Failed to generate PDF: ' . $e->getMessage()]);
        }
    }

    public function printReceipt()
    {
        $this->dispatch('printReceipt');
    }

    public function deleteReturn($returnId)
    {
        $this->selectedReturn = ReturnsProduct::find($returnId);
        $this->currentReturnId = $returnId;
        $this->dispatch('showModal', 'deleteReturnModal');
    }

    public function editReturn($returnId)
    {
        $return = ReturnsProduct::with(['sale.customer', 'product'])->find($returnId);

        if (!$return) {
            $this->dispatch('showToast', ['type' => 'error', 'message' => 'Return record not found.']);
            return;
        }

        $this->editingReturnId = $return->id;
        $this->selectedReturn = $return;
        $this->editReturnQuantity = (float) ($return->return_quantity ?? 0);
        $this->editReturnPrice = (float) ($return->selling_price ?? 0);
        $this->editReturnNotes = (string) ($return->notes ?? '');

        $this->dispatch('showModal', 'editReturnModal');
    }

    public function updateReturn()
    {
        if (!$this->editingReturnId) {
            $this->dispatch('showToast', ['type' => 'error', 'message' => 'No return selected for update.']);
            return;
        }

        $this->validate([
            'editReturnQuantity' => 'required|numeric|min:0.01',
            'editReturnPrice' => 'required|numeric|min:0',
            'editReturnNotes' => 'nullable|string|max:1000',
        ]);

        try {
            DB::transaction(function () {
                $return = ReturnsProduct::with(['sale.customer'])->lockForUpdate()->findOrFail($this->editingReturnId);

                $oldQty = (float) ($return->return_quantity ?? 0);
                $oldPrice = (float) ($return->selling_price ?? 0);
                $oldTotal = (float) ($return->total_amount ?? ($oldQty * $oldPrice));

                $newQty = (float) $this->editReturnQuantity;
                $newPrice = (float) $this->editReturnPrice;
                $newTotal = round($newQty * $newPrice, 2);

                $qtyDelta = $newQty - $oldQty;

                // When reducing return qty, stock must have enough available quantity to reverse the return.
                if ($qtyDelta < 0) {
                    $required = abs($qtyDelta);
                    $stockCheck = \App\Models\ProductStock::where('product_id', $return->product_id)->first();
                    if ($stockCheck && (float) $stockCheck->available_stock < $required) {
                        throw new \RuntimeException('Not enough available stock to reduce this return quantity.');
                    }
                }

                $return->update([
                    'return_quantity' => $newQty,
                    'selling_price' => $newPrice,
                    'total_amount' => $newTotal,
                    'notes' => $this->editReturnNotes ?: null,
                ]);

                $this->applyReturnStockAdjustment($return, $qtyDelta);
                $this->applySaleAndCustomerAdjustment($return, $oldTotal, $newTotal);
            });

            $this->selectedReturn = ReturnsProduct::with(['sale.customer', 'product'])->find($this->editingReturnId);
            $this->loadReturns();
            $this->dispatch('hideModal', 'editReturnModal');
            $this->dispatch('showToast', ['type' => 'success', 'message' => 'Return updated successfully.']);
        } catch (\Throwable $e) {
            $this->dispatch('showToast', ['type' => 'error', 'message' => 'Failed to update return: ' . $e->getMessage()]);
        }
    }

    private function applyReturnStockAdjustment(ReturnsProduct $return, float $qtyDelta): void
    {
        if (abs($qtyDelta) < 0.0001) {
            return;
        }

        $stockQuery = \App\Models\ProductStock::where('product_id', $return->product_id);
        if (!empty($return->variant_id)) {
            $stockQuery->where('variant_id', $return->variant_id);
        }
        if (!empty($return->variant_value)) {
            $stockQuery->where('variant_value', $return->variant_value);
        }

        $stock = $stockQuery->first();
        if (!$stock) {
            $stock = \App\Models\ProductStock::where('product_id', $return->product_id)->first();
        }

        if (!$stock) {
            $stock = \App\Models\ProductStock::create([
                'product_id' => $return->product_id,
                'variant_id' => $return->variant_id,
                'variant_value' => $return->variant_value,
                'available_stock' => 0,
                'damage_stock' => 0,
                'total_stock' => 0,
                'sold_count' => 0,
                'restocked_quantity' => 0,
            ]);
        }

        $stock->available_stock = (float) $stock->available_stock + $qtyDelta;
        if ($qtyDelta > 0) {
            $stock->sold_count = max(0, (float) $stock->sold_count - $qtyDelta);
        } else {
            $stock->sold_count = (float) $stock->sold_count + abs($qtyDelta);
        }

        $stock->updateTotals();
    }

    private function applySaleAndCustomerAdjustment(ReturnsProduct $return, float $oldTotal, float $newTotal): void
    {
        $sale = $return->sale;
        if (!$sale) {
            return;
        }

        // Positive value means sale should increase, negative means sale should decrease.
        $saleAdjustment = $oldTotal - $newTotal;

        $sale->total_amount = max(0, (float) $sale->total_amount + $saleAdjustment);
        $sale->subtotal = max(0, (float) ($sale->subtotal ?? 0) + $saleAdjustment);
        $sale->due_amount = max(0, (float) ($sale->due_amount ?? 0) + $saleAdjustment);
        $sale->save();

        if ($sale->customer) {
            $customer = $sale->customer;
            $customer->due_amount = max(0, (float) ($customer->due_amount ?? 0) + $saleAdjustment);
            $customer->total_due = (float) ($customer->opening_balance ?? 0) + (float) $customer->due_amount;
            $customer->save();
        }
    }

    public function confirmDeleteReturn()
    {
        try {
            if ($this->selectedReturn) {
                // Restore the stock before deleting the return record
                $this->restoreStock($this->selectedReturn);

                $this->selectedReturn->delete();
                // Refresh lightweight data and reset pagination if needed
                $this->loadReturns();
                $this->resetPage();

                $this->dispatch('hideModal', 'deleteReturnModal');
                $this->dispatch('showToast', ['type' => 'success', 'message' => 'Return record deleted successfully!']);
            }
        } catch (\Exception $e) {
            $this->dispatch('showToast', ['type' => 'error', 'message' => 'Error deleting return: ' . $e->getMessage()]);
        }
    }

    private function restoreStock($return)
    {
        // Decrease the available stock since we're deleting a return
        $productStock = \App\Models\ProductStock::where('product_id', $return->product_id)->first();

        if ($productStock) {
            $productStock->available_stock -= $return->return_quantity;
            if ($productStock->sold_count >= $return->return_quantity) {
                $productStock->sold_count += $return->return_quantity;
            }
            $productStock->save();
        }
    }

    public function closeModal()
    {
        $this->selectedReturn = null;
        $this->currentReturnId = null;
        $this->showReceiptModal = false;
        $this->editingReturnId = null;
        $this->editReturnQuantity = 0;
        $this->editReturnPrice = 0;
        $this->editReturnNotes = '';
        $this->dispatch('hideModal', 'returnDetailsModal');
        $this->dispatch('hideModal', 'deleteReturnModal');
        $this->dispatch('hideModal', 'receiptModal');
        $this->dispatch('hideModal', 'editReturnModal');
    }

    public function render()
    {
        $query = ReturnsProduct::with(['sale', 'product'])->orderByDesc('created_at');

        // Filter by user for staff
        if ($this->isStaff()) {
            $query->whereHas('sale', function ($q) {
                $q->where('user_id', Auth::id());
            });
        }

        if (!empty($this->returnSearch)) {
            $search = '%' . $this->returnSearch . '%';
            $query->where(function ($q) use ($search) {
                $q->whereHas('sale', function ($sq) use ($search) {
                    $sq->where('invoice_number', 'like', $search);
                })->orWhereHas('product', function ($pq) use ($search) {
                    $pq->where('name', 'like', $search)
                        ->orWhere('code', 'like', $search);
                });
            });
        }
        $returns = $query->paginate($this->perPage);

        return view('livewire.admin.return-list', [
            'returns' => $returns,
            'selectedReturn' => $this->selectedReturn,
            'currentReturnId' => $this->currentReturnId,
        ])->layout($this->layout);
    }

    public function updatedPerPage()
    {
        $this->resetPage();
    }
}
