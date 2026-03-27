<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\WithDynamicLayout;
use App\Models\ProductSupplier;
use App\Models\PurchaseReturn;
use App\Services\PurchaseReturnService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Purchase Return List')]
#[Layout('components.layouts.admin')]
class PurchaseReturnList extends Component
{
    use WithDynamicLayout;
    use WithPagination;

    public string $pageMode = 'list';

    public string $search = '';
    public string $dateFrom = '';
    public string $dateTo = '';
    public string $supplierFilter = '';
    public int $perPage = 10;

    public ?int $selectedReturnId = null;
    public ?PurchaseReturn $selectedReturn = null;

    public ?int $editReturnId = null;
    public string $editReturnDate = '';
    public string $editReturnType = 'debit_note';
    public string $editNotes = '';
    public array $editItems = [];

    protected $queryString = [
        'search' => ['except' => ''],
        'dateFrom' => ['except' => ''],
        'dateTo' => ['except' => ''],
        'supplierFilter' => ['except' => ''],
    ];

    public function updatingSearch(): void { $this->resetPage(); }
    public function updatingDateFrom(): void { $this->resetPage(); }
    public function updatingDateTo(): void { $this->resetPage(); }
    public function updatingSupplierFilter(): void { $this->resetPage(); }

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->dateFrom = '';
        $this->dateTo = '';
        $this->supplierFilter = '';
        $this->resetPage();
    }

    public function viewReturn(int $id): void
    {
        $this->selectedReturnId = $id;
        $this->selectedReturn = PurchaseReturn::with(['supplier', 'purchaseOrder', 'items.product', 'creator'])->find($id);
        $this->dispatch('showModal', 'viewPurchaseReturnModal');
    }

    public function openEdit(int $id): void
    {
        $return = PurchaseReturn::with('items.product')->find($id);
        if (!$return) {
            return;
        }

        $this->editReturnId = $return->id;
        $this->editReturnDate = optional($return->return_date)->format('Y-m-d') ?: now()->toDateString();
        $this->editReturnType = $return->return_type;
        $this->editNotes = (string) ($return->notes ?? '');
        $editItems = [];
        foreach ($return->items as $item) {
            $editItems[] = [
                'purchase_order_item_id' => $item->purchase_order_item_id,
                'product_name' => $item->product?->name . ($item->variant_value ? ' (' . $item->variant_value . ')' : ''),
                'balance_returnable_qty' => (float) $item->balance_returnable_qty + (float) $item->return_qty,
                'return_qty' => (float) $item->return_qty,
                'rate' => (float) $item->rate,
                'discount' => (float) $item->discount_amount,
                'tax' => (float) $item->tax_amount,
                'line_total' => (float) $item->line_total,
            ];
        }
        $this->editItems = $editItems;

        $this->dispatch('showModal', 'editPurchaseReturnModal');
    }

    public function updatedEditItems(): void
    {
        foreach ($this->editItems as $index => $item) {
            $qty = max(0, (float) ($item['return_qty'] ?? 0));
            $max = max(0, (float) ($item['balance_returnable_qty'] ?? 0));
            if ($qty > $max) {
                $qty = $max;
            }
            $rate = max(0, (float) ($item['rate'] ?? 0));
            $discount = max(0, (float) ($item['discount'] ?? 0));
            $tax = max(0, (float) ($item['tax'] ?? 0));
            $line = max(0, ($qty * $rate) - $discount + $tax);

            $this->editItems[$index]['return_qty'] = $qty;
            $this->editItems[$index]['rate'] = $rate;
            $this->editItems[$index]['discount'] = $discount;
            $this->editItems[$index]['tax'] = $tax;
            $this->editItems[$index]['line_total'] = round($line, 2);
        }
    }

    public function updateReturn(): void
    {
        if (!$this->editReturnId) {
            return;
        }

        $this->validate([
            'editReturnDate' => 'required|date',
            'editReturnType' => 'required|in:cash_refund,debit_note,replacement',
            'editItems' => 'required|array|min:1',
            'editItems.*.purchase_order_item_id' => 'required|integer',
            'editItems.*.return_qty' => 'nullable|numeric|min:0',
            'editItems.*.rate' => 'nullable|numeric|min:0',
            'editItems.*.discount' => 'nullable|numeric|min:0',
            'editItems.*.tax' => 'nullable|numeric|min:0',
        ]);

        $items = collect($this->editItems)
            ->filter(fn($row) => (float) ($row['return_qty'] ?? 0) > 0)
            ->values()
            ->toArray();

        if (empty($items)) {
            $this->dispatch('showToast', ['type' => 'error', 'message' => 'At least one return qty is required.']);
            return;
        }

        $service = app(PurchaseReturnService::class);

        try {
            $service->updatePurchaseReturn($this->editReturnId, [
                'return_date' => $this->editReturnDate,
                'return_type' => $this->editReturnType,
                'notes' => $this->editNotes,
                'items' => $items,
            ]);

            $this->dispatch('hideModal', 'editPurchaseReturnModal');
            $this->dispatch('showToast', ['type' => 'success', 'message' => 'Purchase return updated successfully.']);
            $this->editReturnId = null;
        } catch (\Throwable $e) {
            $this->dispatch('showToast', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function deleteReturn(int $id): void
    {
        $service = app(PurchaseReturnService::class);

        try {
            $service->deletePurchaseReturn($id);
            $this->dispatch('showToast', ['type' => 'success', 'message' => 'Purchase return deleted and reversed successfully.']);
        } catch (\Throwable $e) {
            $this->dispatch('showToast', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function getGrandTotalEditProperty(): float
    {
        return (float) collect($this->editItems)->sum(function ($item) {
            return (float) ($item['line_total'] ?? 0);
        });
    }

    public function render()
    {
        $data = $this->getListData();

        return view('livewire.admin.purchase-return-list', $data);
    }

    protected function getListData(): array
    {
        $query = PurchaseReturn::with(['supplier', 'purchaseOrder', 'creator'])->latest('id');

        if ($this->search !== '') {
            $query->where(function ($q) {
                $q->where('return_no', 'like', '%' . $this->search . '%')
                    ->orWhereHas('supplier', function ($sq) {
                        $sq->where('name', 'like', '%' . $this->search . '%');
                    })
                    ->orWhereHas('purchaseOrder', function ($pq) {
                        $pq->where('invoice_number', 'like', '%' . $this->search . '%')
                           ->orWhere('order_code', 'like', '%' . $this->search . '%');
                    });
            });
        }

        if ($this->supplierFilter !== '') {
            $query->where('supplier_id', (int) $this->supplierFilter);
        }

        if ($this->dateFrom !== '') {
            $query->whereDate('return_date', '>=', $this->dateFrom);
        }

        if ($this->dateTo !== '') {
            $query->whereDate('return_date', '<=', $this->dateTo);
        }

        $returns = $query->paginate($this->perPage);
        $suppliers = ProductSupplier::orderBy('name')->get(['id', 'name']);

        return [
            'returns' => $returns,
            'suppliers' => $suppliers,
        ];
    }
}
