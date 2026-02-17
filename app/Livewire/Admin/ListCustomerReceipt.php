<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Sale;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Livewire\Concerns\WithDynamicLayout;
use Illuminate\Support\Facades\Auth;

#[Title('List Customer Receipt')]
class ListCustomerReceipt extends Component
{
    use WithDynamicLayout;

    use WithPagination;

    public $showPaymentModal = false;
    public $showReceiptModal = false;
    public $selectedCustomer = null;
    public $selectedPayment = null;
    public $payments = [];

    public function getCustomersProperty()
    {
        // Get customers with total paid and receipt count (sum from payments table)
        $query = Customer::select(
            'customers.id',
            'customers.name',
            'customers.email',
            'customers.address',
            'customers.created_at',
            'customers.updated_at'
        )
            ->selectRaw('COALESCE(SUM(payments.amount),0) as total_paid')
            ->selectRaw('COUNT(payments.id) as receipts_count')
            ->leftJoin('payments', 'payments.customer_id', '=', 'customers.id');

        // Filter by user for staff - only show customers with payments from their sales
        if ($this->isStaff()) {
            $query->leftJoin('sales', 'payments.sale_id', '=', 'sales.id')
                ->where('sales.user_id', Auth::id())
                ->where('sales.sale_type', 'staff');
        }

        return $query->groupBy(
            'customers.id',
            'customers.name',
            'customers.email',
            'customers.address',
            'customers.created_at',
            'customers.updated_at'
        )
            ->having('total_paid', '>', 0)
            ->orderByDesc('total_paid')
            ->paginate(20);
    }

    public function showCustomerPayments($customerId)
    {
        $this->selectedCustomer = Customer::find($customerId);

        $query = Payment::with(['allocations', 'allocations.sale', 'cheques'])
            ->where('customer_id', $customerId);

        // Filter payments by user's sales for staff
        if ($this->isStaff()) {
            $query->whereHas('sale', function ($q) {
                $q->where('user_id', Auth::id())->where('sale_type', 'staff');
            });
        }

        $this->payments = $query->orderByDesc('payment_date')->get();
        $this->showPaymentModal = true;
    }

    public function closePaymentModal()
    {
        $this->showPaymentModal = false;
        $this->selectedCustomer = null;
        $this->payments = [];
    }

    public function viewPaymentReceipt($paymentId)
    {
        $this->selectedPayment = Payment::with(['customer', 'allocations.sale', 'cheques'])
            ->find($paymentId);

        // Debug log to check if allocations are loaded
        \Log::info('Payment Receipt View', [
            'payment_id' => $paymentId,
            'allocations_count' => $this->selectedPayment->allocations ? $this->selectedPayment->allocations->count() : 0,
            'payment_amount' => $this->selectedPayment->amount
        ]);

        $this->showReceiptModal = true;
    }

    public function closeReceiptModal()
    {
        $this->showReceiptModal = false;
        $this->selectedPayment = null;
    }

    public function render()
    {
        return view('livewire.admin.list-customer-receipt', [
            'customers' => $this->customers,
        ])->layout($this->layout);
    }
}
