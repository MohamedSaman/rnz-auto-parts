<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Title;
use App\Livewire\Concerns\WithDynamicLayout;
use App\Models\PurchaseOrder;
use App\Models\ProductSupplier;
use App\Services\VoucherService;
use Illuminate\Support\Facades\Log;
use Barryvdh\DomPDF\Facade\Pdf;

#[Title('Purchase Voucher List')]
class PurchaseVoucherList extends Component
{
    use WithDynamicLayout, WithPagination;

    protected $paginationTheme = 'bootstrap';

    // Filters
    public $search = '';
    public $dateFrom;
    public $dateTo;
    public $supplierFilter = '';
    public $paymentTypeFilter = '';
    public $statusFilter = '';
    public $perPage = 15;

    // Delete
    public $showDeleteModal = false;
    public $deleteId = null;

    public function mount()
    {
        $this->dateFrom = now()->startOfMonth()->toDateString();
        $this->dateTo = now()->toDateString();
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }
    public function updatingDateFrom()
    {
        $this->resetPage();
    }
    public function updatingDateTo()
    {
        $this->resetPage();
    }
    public function updatingSupplierFilter()
    {
        $this->resetPage();
    }
    public function updatingPaymentTypeFilter()
    {
        $this->resetPage();
    }
    public function updatingStatusFilter()
    {
        $this->resetPage();
    }
    public function updatingPerPage()
    {
        $this->resetPage();
    }

    // ═══ Computed ═══

    public function getVouchersProperty()
    {
        $query = PurchaseOrder::with(['supplier', 'items'])
            ->whereBetween('order_date', [$this->dateFrom, $this->dateTo]);

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('order_code', 'like', '%' . $this->search . '%')
                    ->orWhere('invoice_number', 'like', '%' . $this->search . '%');
            });
        }

        if ($this->supplierFilter) {
            $query->where('supplier_id', $this->supplierFilter);
        }

        if ($this->paymentTypeFilter) {
            $query->where('payment_type', $this->paymentTypeFilter);
        }

        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }

        return $query->orderByDesc('order_date')->orderByDesc('id')->paginate($this->perPage);
    }

    public function getSuppliersProperty()
    {
        return ProductSupplier::orderBy('name')->get();
    }

    public function getTotalAmountProperty()
    {
        $query = PurchaseOrder::whereBetween('order_date', [$this->dateFrom, $this->dateTo])
            ->whereNotIn('status', ['cancelled']);

        if ($this->supplierFilter) $query->where('supplier_id', $this->supplierFilter);
        if ($this->paymentTypeFilter) $query->where('payment_type', $this->paymentTypeFilter);

        return $query->sum('total_amount');
    }

    public function getVoucherCountProperty()
    {
        return PurchaseOrder::whereBetween('order_date', [$this->dateFrom, $this->dateTo])
            ->whereNotIn('status', ['cancelled'])
            ->count();
    }

    // ═══ Actions ═══

    public function modifyVoucher($id)
    {
        return redirect()->route('admin.purchase-voucher-modify', ['load' => $id]);
    }

    public function confirmDelete($id)
    {
        $this->deleteId = $id;
        $this->showDeleteModal = true;
    }

    public function deleteVoucher()
    {
        if (!$this->deleteId) return;

        try {
            VoucherService::deletePurchaseVoucher($this->deleteId);
            $this->showDeleteModal = false;
            $this->deleteId = null;
            $this->js("Swal.fire({ icon:'success', title:'Deleted!', text:'Purchase voucher has been cancelled.', timer:1500, showConfirmButton:false });");
        } catch (\Exception $e) {
            Log::error('Delete purchase voucher error: ' . $e->getMessage());
            $this->js("Swal.fire('Error', 'Failed to delete: " . addslashes($e->getMessage()) . "', 'error');");
        }
    }

    public function closeDeleteModal()
    {
        $this->showDeleteModal = false;
        $this->deleteId = null;
    }

    public function printVoucher($id)
    {
        // Redirect to purchase PDF download (using PurchaseOrderList's existing PDF logic)
        $order = PurchaseOrder::with(['supplier', 'items.product'])->find($id);
        if (!$order) return;

        try {
            $pdf = Pdf::loadView('livewire.admin.purchase-voucher-print', [
                'order' => $order,
            ]);
            return response()->streamDownload(function () use ($pdf) {
                echo $pdf->stream();
            }, 'PurchaseVoucher-' . $order->order_code . '.pdf');
        } catch (\Exception $e) {
            Log::warning('PDF generation failed: ' . $e->getMessage());
            $this->js("Swal.fire('Error', 'PDF generation failed.', 'error');");
        }
    }

    public function clearFilters()
    {
        $this->search = '';
        $this->dateFrom = now()->startOfMonth()->toDateString();
        $this->dateTo = now()->toDateString();
        $this->supplierFilter = '';
        $this->paymentTypeFilter = '';
        $this->statusFilter = '';
        $this->resetPage();
    }

    public function render()
    {
        return view('livewire.admin.purchase-voucher-list', [
            'vouchers' => $this->vouchers,
            'suppliers' => $this->suppliers,
        ])->layout($this->layout, ['erpContext' => 'list']);
    }
}
