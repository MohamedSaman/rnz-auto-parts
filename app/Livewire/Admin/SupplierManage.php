<?php

namespace App\Livewire\Admin;

use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;
use App\Models\ProductSupplier;
use App\Models\PurchaseOrder;
use App\Models\PurchasePayment;
use Livewire\WithPagination;
use App\Livewire\Concerns\WithDynamicLayout;

#[Title("Supplier Management")]
class SupplierManage extends Component
{
    use WithDynamicLayout;

    use WithPagination;

    public $supplierId;
    public $name;
    public $businessname;
    public $contact;
    public $address;
    public $email;
    public $phone;
    public $status = 'active';
    public $notes;

    public $showCreateModal = false;
    public $showEditModal = false;
    public $showViewModal = false;
    public $perPage = 10;
    public $activeTab = 'overview';

    public $viewSupplierDetail = [];
    public $viewSupplierPurchases = [];
    public $viewSupplierPayments = [];
    public $viewSupplierDues = [];
    public $viewSupplierLedger = [];

    protected $rules = [
        'name' => 'required|string|max:255',
        'businessname' => 'nullable|string|max:255',
        'contact' => 'nullable|string|max:10',
        'address' => 'nullable|string|max:255',
        'email' => 'nullable|email|max:255',
        'phone' => 'nullable|string|max:10',
        'status' => 'required|in:active,inactive',
        'notes' => 'nullable|string|max:500',
    ];

    protected $messages = [
        'name.required' => 'The supplier name field is required.',
        'name.string' => 'The supplier name must be a valid string.',
        'name.max' => 'The supplier name may not be greater than 255 characters.',
        'businessname.string' => 'The business name must be a valid string.',
        'businessname.max' => 'The business name may not be greater than 255 characters.',
        'contact.string' => 'The contact number must be a valid string.',
        'contact.max' => 'The contact number may not be greater than 10 characters.',
        'address.string' => 'The address must be a valid string.',
        'address.max' => 'The address may not be greater than 255 characters.',
        'email.email' => 'Please enter a valid email address.',
        'email.max' => 'The email may not be greater than 255 characters.',
        'phone.string' => 'The phone number must be a valid string.',
        'phone.max' => 'The phone number may not be greater than 10 characters.',
        'status.required' => 'The status field is required.',
        'status.in' => 'The selected status is invalid.',
        'notes.string' => 'The notes must be a valid string.',
        'notes.max' => 'The notes may not be greater than 500 characters.',
    ];

    // -------------------- CREATE MODAL --------------------
    public function createSupplier()
    {
        $this->resetForm();
        $this->showCreateModal = true;
    }

    // -------------------- CREATE --------------------
    public function save()
    {
        $this->validate();

        ProductSupplier::create([
            'name' => $this->name,
            'businessname' => $this->businessname,
            'contact' => $this->contact,
            'address' => $this->address,
            'email' => $this->email,
            'phone' => $this->phone,
            'status' => $this->status,
            'notes' => $this->notes,
        ]);

        $this->resetForm();
        $this->showCreateModal = false;

        $this->dispatch('show-toast', 'success', 'Supplier created successfully!');
        $this->dispatch('refreshPage');
    }

    // -------------------- VIEW --------------------
    public function view($id)
    {
        $supplier = ProductSupplier::findOrFail($id);

        $this->supplierId = $supplier->id;
        $this->name = $supplier->name;
        $this->businessname = $supplier->businessname;
        $this->contact = $supplier->contact;
        $this->address = $supplier->address;
        $this->email = $supplier->email;
        $this->phone = $supplier->phone;
        $this->status = $supplier->status;
        $this->notes = $supplier->notes;

        $this->activeTab = 'overview';

        $this->viewSupplierDetail = [
            'id' => $supplier->id,
            'name' => $supplier->name,
            'business_name' => $supplier->businessname,
            'contact' => $supplier->contact,
            'email' => $supplier->email,
            'phone' => $supplier->phone,
            'status' => $supplier->status,
            'address' => $supplier->address,
            'notes' => $supplier->notes,
            'overpayment' => (float) ($supplier->overpayment ?? 0),
            'created_at' => $supplier->created_at,
            'updated_at' => $supplier->updated_at,
        ];

        $purchases = PurchaseOrder::where('supplier_id', $supplier->id)
            ->with(['items'])
            ->orderBy('order_date', 'desc')
            ->get();

        $this->viewSupplierPurchases = $purchases->map(function ($purchase) {
            $paidAmount = (float) ($purchase->total_amount ?? 0) - (float) ($purchase->due_amount ?? 0);

            return [
                'id' => $purchase->id,
                'order_code' => $purchase->order_code,
                'invoice_number' => $purchase->invoice_number,
                'order_date' => $purchase->order_date,
                'status' => $purchase->status,
                'payment_type' => $purchase->payment_type,
                'items_count' => $purchase->items->count(),
                'total_amount' => (float) ($purchase->total_amount ?? 0),
                'due_amount' => (float) ($purchase->due_amount ?? 0),
                'paid_amount' => $paidAmount,
                'created_at' => optional($purchase->created_at)->format('M d, Y h:i A') ?? '-',
            ];
        })->toArray();

        $payments = PurchasePayment::where('supplier_id', $supplier->id)
            ->with(['purchaseOrder'])
            ->orderBy('payment_date', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        $this->viewSupplierPayments = $payments->map(function ($payment) {
            return [
                'id' => $payment->id,
                'amount' => (float) ($payment->amount ?? 0),
                'overpayment_used' => (float) ($payment->overpayment_used ?? 0),
                'total_payment' => (float) ($payment->total_payment ?? (($payment->amount ?? 0) + ($payment->overpayment_used ?? 0))),
                'payment_method' => $payment->payment_method,
                'payment_reference' => $payment->payment_reference ?? $payment->reference,
                'payment_date' => optional($payment->payment_date)->format('M d, Y') ?? '-',
                'status' => $payment->status,
                'order_code' => $payment->purchaseOrder->order_code ?? '-',
                'notes' => null,
                'created_at' => optional($payment->created_at)->format('M d, Y h:i A') ?? '-',
            ];
        })->toArray();

        $this->viewSupplierDues = $purchases->filter(function ($purchase) {
            return (float) ($purchase->due_amount ?? 0) > 0;
        })->map(function ($purchase) {
            $paidAmount = (float) ($purchase->total_amount ?? 0) - (float) ($purchase->due_amount ?? 0);

            return [
                'id' => $purchase->id,
                'order_code' => $purchase->order_code,
                'invoice_number' => $purchase->invoice_number,
                'total_amount' => (float) ($purchase->total_amount ?? 0),
                'due_amount' => (float) ($purchase->due_amount ?? 0),
                'paid_amount' => $paidAmount,
                'status' => $purchase->status,
                'payment_type' => $purchase->payment_type,
                'created_at' => optional($purchase->created_at)->format('M d, Y h:i A') ?? '-',
            ];
        })->values()->toArray();

        $ledgerEntries = collect();

        foreach ($purchases as $purchase) {
            $ledgerEntries->push([
                'date' => optional($purchase->order_date)->format('M d, Y') ?? (optional($purchase->created_at)->format('M d, Y h:i A') ?? '-'),
                'description' => 'Purchase Voucher',
                'reference' => $purchase->order_code ?? $purchase->invoice_number ?? '-',
                'debit' => (float) ($purchase->total_amount ?? 0),
                'credit' => 0,
                'type' => 'purchase',
            ]);
        }

        foreach ($payments as $payment) {
            $ledgerEntries->push([
                'date' => optional($payment->payment_date)->format('M d, Y') ?? (optional($payment->created_at)->format('M d, Y h:i A') ?? '-'),
                'description' => 'Payment Made (' . ucfirst($payment->payment_method ?? 'N/A') . ')',
                'reference' => $payment->payment_reference ?? $payment->reference ?? ($payment->purchaseOrder->order_code ?? '-'),
                'debit' => 0,
                'credit' => (float) ($payment->total_payment ?? (($payment->amount ?? 0) + ($payment->overpayment_used ?? 0))),
                'type' => 'payment',
            ]);
        }

        if ((float) ($supplier->overpayment ?? 0) > 0) {
            $ledgerEntries->push([
                'date' => optional($supplier->updated_at)->format('M d, Y h:i A') ?? '-',
                'description' => 'Overpayment Credit Balance',
                'reference' => 'SUP-' . $supplier->id,
                'debit' => 0,
                'credit' => (float) ($supplier->overpayment ?? 0),
                'type' => 'credit_balance',
            ]);
        }

        $this->viewSupplierLedger = $ledgerEntries->sortBy('date')->values()->toArray();

        $this->showViewModal = true;
    }

    public function setActiveTab($tab)
    {
        $this->activeTab = $tab;
    }

    // -------------------- EDIT --------------------
    public function edit($id)
    {
        $supplier = ProductSupplier::findOrFail($id);

        $this->supplierId = $supplier->id;
        $this->name = $supplier->name;
        $this->businessname = $supplier->businessname;
        $this->contact = $supplier->contact;
        $this->address = $supplier->address;
        $this->email = $supplier->email;
        $this->phone = $supplier->phone;
        $this->status = $supplier->status;
        $this->notes = $supplier->notes;

        $this->showEditModal = true;
    }

    // -------------------- UPDATE --------------------
    public function updateSupplier()
    {
        $this->validate();

        if (!$this->supplierId) {
            $this->dispatch('show-toast', 'error', 'No supplier selected.');
            return;
        }

        $supplier = ProductSupplier::findOrFail($this->supplierId);

        $supplier->update([
            'name' => $this->name,
            'businessname' => $this->businessname,
            'contact' => $this->contact,
            'address' => $this->address,
            'email' => $this->email,
            'phone' => $this->phone,
            'status' => $this->status,
            'notes' => $this->notes,
        ]);

        $this->resetForm();
        $this->showEditModal = false;

        $this->dispatch('show-toast', 'success', 'Supplier updated successfully!');
        $this->dispatch('refreshPage');
    }

    // -------------------- CONFIRM DELETE --------------------
    public function confirmDelete($id)
    {
        $this->supplierId = $id;

        $this->dispatch('swal:confirm', [
            'title' => 'Are you sure?',
            'text' => 'You won\'t be able to revert this!',
            'icon' => 'warning',
            'id' => $id,
        ]);
    }

    // -------------------- DELETE --------------------
    #[On('delete-supplier')]
    public function deleteSupplier($id)
    {
        $supplier = ProductSupplier::find($id);

        if ($supplier) {
            $supplier->delete();
            $this->dispatch('show-toast', 'success', 'Supplier has been deleted.');
        } else {
            $this->dispatch('show-toast', 'error', 'Supplier not found.');
        }
        $this->dispatch('refreshPage');
    }

    public function closeModal()
    {
        $this->showCreateModal = false;
        $this->showEditModal = false;
        $this->showViewModal = false;
        $this->resetForm();
    }

    public function resetForm()
    {
        $this->reset(['supplierId', 'name', 'businessname', 'contact', 'address', 'email', 'phone', 'status', 'notes']);
        $this->status = 'active';
        $this->resetValidation();
    }
    public function updatedPerPage()
    {
        $this->resetPage();
    }

    public function render()
    {
        $suppliers = ProductSupplier::latest()->paginate($this->perPage);
        return view('livewire.admin.supplier-manage', compact('suppliers'))->layout($this->layout);
    }
}
