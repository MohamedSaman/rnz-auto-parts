<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Title;
use App\Livewire\Concerns\WithDynamicLayout;
use App\Models\Sale;
use App\Models\Customer;
use App\Services\VoucherService;
use Illuminate\Support\Facades\Log;
use Barryvdh\DomPDF\Facade\Pdf;

#[Title('Sales Voucher List')]
class SalesVoucherList extends Component
{
    use WithDynamicLayout, WithPagination;

    // Filters
    public $search = '';
    public $dateFrom = '';
    public $dateTo = '';
    public $customerFilter = '';
    public $paymentTypeFilter = '';
    public $statusFilter = '';
    public $perPage = 25;

    // View modal
    public $showViewModal = false;
    public $selectedSale = null;

    // Delete modal
    public $showDeleteModal = false;
    public $deleteId = null;

    public function mount()
    {
        $this->dateFrom = now()->startOfMonth()->toDateString();
        $this->dateTo = now()->toDateString();
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }
    public function updatedDateFrom()
    {
        $this->resetPage();
    }
    public function updatedDateTo()
    {
        $this->resetPage();
    }
    public function updatedCustomerFilter()
    {
        $this->resetPage();
    }
    public function updatedPaymentTypeFilter()
    {
        $this->resetPage();
    }
    public function updatedStatusFilter()
    {
        $this->resetPage();
    }

    public function getVouchersProperty()
    {
        $query = Sale::with(['customer', 'items', 'user'])
            ->where('status', '!=', 'cancelled');

        // Search
        if ($this->search) {
            $search = $this->search;
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                    ->orWhere('sale_id', 'like', "%{$search}%")
                    ->orWhereHas('customer', function ($q2) use ($search) {
                        $q2->where('name', 'like', "%{$search}%")
                            ->orWhere('business_name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    });
            });
        }

        // Date range
        if ($this->dateFrom) {
            $query->whereDate('created_at', '>=', $this->dateFrom);
        }
        if ($this->dateTo) {
            $query->whereDate('created_at', '<=', $this->dateTo);
        }

        // Customer filter
        if ($this->customerFilter) {
            $query->where('customer_id', $this->customerFilter);
        }

        // Payment type
        if ($this->paymentTypeFilter) {
            $query->where('billing_type', $this->paymentTypeFilter);
        }

        // Status
        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }

        return $query->orderBy('created_at', 'desc')->paginate($this->perPage);
    }

    public function getCustomersProperty()
    {
        return Customer::orderBy('name')->get(['id', 'name', 'business_name']);
    }

    public function getTotalAmountProperty()
    {
        $query = Sale::where('status', '!=', 'cancelled');
        if ($this->dateFrom) $query->whereDate('created_at', '>=', $this->dateFrom);
        if ($this->dateTo) $query->whereDate('created_at', '<=', $this->dateTo);
        if ($this->customerFilter) $query->where('customer_id', $this->customerFilter);
        if ($this->paymentTypeFilter) $query->where('billing_type', $this->paymentTypeFilter);
        return $query->sum('total_amount');
    }

    public function getVoucherCountProperty()
    {
        $query = Sale::where('status', '!=', 'cancelled');
        if ($this->dateFrom) $query->whereDate('created_at', '>=', $this->dateFrom);
        if ($this->dateTo) $query->whereDate('created_at', '<=', $this->dateTo);
        return $query->count();
    }

    // --- View ---
    public function viewVoucher($id)
    {
        $this->selectedSale = Sale::with(['customer', 'items.product', 'user', 'payments'])->find($id);
        $this->showViewModal = true;
    }

    public function closeViewModal()
    {
        $this->showViewModal = false;
        $this->selectedSale = null;
    }

    // --- Modify ---
    public function modifyVoucher($id)
    {
        return redirect()->route('admin.sales-voucher-modify', ['saleId' => $id]);
    }

    // --- Delete ---
    public function confirmDelete($id)
    {
        $this->deleteId = $id;
        $this->showDeleteModal = true;
    }

    public function deleteVoucher()
    {
        if (!$this->deleteId) return;

        try {
            VoucherService::deleteSalesVoucher($this->deleteId);
            session()->flash('success', 'Voucher deleted/cancelled successfully.');
        } catch (\Exception $e) {
            Log::error('Delete voucher failed: ' . $e->getMessage());
            session()->flash('error', 'Failed to delete voucher: ' . $e->getMessage());
        }

        $this->showDeleteModal = false;
        $this->deleteId = null;
    }

    public function closeDeleteModal()
    {
        $this->showDeleteModal = false;
        $this->deleteId = null;
    }

    // --- Print ---
    public function printVoucher($id)
    {
        return redirect()->route('admin.print.sale', $id);
    }

    public function clearFilters()
    {
        $this->search = '';
        $this->dateFrom = now()->startOfMonth()->toDateString();
        $this->dateTo = now()->toDateString();
        $this->customerFilter = '';
        $this->paymentTypeFilter = '';
        $this->statusFilter = '';
        $this->resetPage();
    }

    public function render()
    {
        return view('livewire.admin.sales-voucher-list', [
            'vouchers' => $this->vouchers,
            'customers' => $this->customers,
        ])->layout($this->layout, ['erpContext' => 'list']);
    }
}
