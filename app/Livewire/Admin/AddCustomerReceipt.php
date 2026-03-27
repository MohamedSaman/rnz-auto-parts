<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\Payment;
use App\Models\Cheque;
use App\Models\ReturnsProduct;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Title;
use Livewire\Attributes\Layout;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Livewire\Concerns\WithDynamicLayout;

#[Title("Add Customer Receipt")]
class AddCustomerReceipt extends Component
{
    use WithDynamicLayout;

    use WithPagination;

    public $search = '';
    public $userFilter = 'admin';
    public $selectedCustomer = null;
    public $customerSales = [];
    public $selectedInvoices = [];
    public $paymentData = [
        'payment_date' => '',
        'payment_method' => 'cash',
        'reference_number' => '',
        'notes' => ''
    ];

    // Cheque related properties
    public $cheque = [
        'cheque_number' => '',
        'bank_name' => '',
        'cheque_date' => '',
        'amount' => ''
    ];

    public $cheques = [];

    public $tempCheque = [
        'cheque_number' => '',
        'bank_name' => '',
        'cheque_date' => '',
        'amount' => ''
    ];

    public $bankTransfer = [
        'bank_name' => '',
        'transfer_date' => '',
        'reference_number' => ''
    ];

    public $allocations = [];
    public $totalDueAmount = 0;
    public $totalPaymentAmount = null;
    public $remainingAmount = 0;
    public $customerOverpaidAmount = 0;
    public $useCustomerOverpayment = false;
    public $customerOverpaymentToApply = 0;
    public $showPaymentModal = false;
    public $showViewModal = false;
    public $showReceiptModal = false;
    public $selectedSale = null;
    public $latestPayment = null;
    public $paymentSuccess = false;

    protected $rules = [
        'paymentData.payment_date' => 'required|date',
        'paymentData.payment_method' => 'required|in:cash,cheque,bank_transfer',
        'paymentData.reference_number' => 'nullable|string|max:100',
        'paymentData.notes' => 'nullable|string|max:500',
        'totalPaymentAmount' => 'nullable|numeric|min:0',
        'bankTransfer.bank_name' => 'required_if:paymentData.payment_method,bank_transfer|string|max:100',
        'bankTransfer.transfer_date' => 'required_if:paymentData.payment_method,bank_transfer|date',
        'bankTransfer.reference_number' => 'required_if:paymentData.payment_method,bank_transfer|string|max:100',
    ];

    protected $messages = [
        'paymentData.payment_date.required' => 'Payment date is required.',
        'paymentData.payment_method.required' => 'Payment method is required.',
        'totalPaymentAmount.min' => 'Payment amount cannot be negative.',
        'bankTransfer.bank_name.required_if' => 'Bank name is required for bank transfer.',
        'bankTransfer.transfer_date.required_if' => 'Transfer date is required for bank transfer.',
        'bankTransfer.reference_number.required_if' => 'Reference number is required for bank transfer.',
    ];

    public function mount()
    {
        $this->paymentData['payment_date'] = now()->format('Y-m-d');
        $this->cheque['cheque_date'] = now()->format('Y-m-d');
        $this->tempCheque['cheque_date'] = now()->format('Y-m-d');
        $this->bankTransfer['transfer_date'] = now()->format('Y-m-d');
        $this->totalPaymentAmount = null;
    }

    public function updatedSearch()
    {
        $this->resetPage();
        $this->selectedCustomer = null;
        $this->customerSales = [];
        $this->resetPaymentData();
    }

    public function updatedUserFilter()
    {
        $this->resetPage();
        $this->selectedCustomer = null;
        $this->customerSales = [];
        $this->resetPaymentData();
    }

    public function updatedTotalPaymentAmount()
    {
        if ($this->totalPaymentAmount === '' || $this->totalPaymentAmount === null) {
            $this->totalPaymentAmount = null;
            $this->calculateRemainingAmount();
            $this->autoAllocatePayment();
            return;
        }

        if ($this->totalPaymentAmount < 0) {
            $this->totalPaymentAmount = 0;
        }

        $this->calculateRemainingAmount();
        $this->autoAllocatePayment();
    }

    public function updatedPaymentDataPaymentMethod($value)
    {
        // Reset all payment method specific fields first
        $this->cheque = [
            'cheque_number' => '',
            'bank_name' => '',
            'cheque_date' => now()->format('Y-m-d'),
            'amount' => ''
        ];

        $this->cheques = [];
        $this->tempCheque = [
            'cheque_number' => '',
            'bank_name' => '',
            'cheque_date' => now()->format('Y-m-d'),
            'amount' => ''
        ];

        $this->bankTransfer = [
            'bank_name' => '',
            'transfer_date' => now()->format('Y-m-d'),
            'reference_number' => ''
        ];
    }

    public function selectCustomer($customerId)
    {
        $this->selectedCustomer = Customer::find($customerId);
        $this->loadCustomerSales();
        $this->selectedInvoices = [];
        $this->totalPaymentAmount = null;
        $this->totalDueAmount = 0;
        $this->customerOverpaidAmount = (float) ($this->selectedCustomer->overpaid_amount ?? 0);
        $this->useCustomerOverpayment = false;
        $this->customerOverpaymentToApply = 0;
        $this->initializeAllocations();
    }

    public function clearSelectedCustomer()
    {
        $this->selectedCustomer = null;
        $this->customerSales = [];
        $this->selectedInvoices = [];
        $this->allocations = [];
        $this->totalDueAmount = 0;
        $this->totalPaymentAmount = null;
        $this->remainingAmount = 0;
        $this->customerOverpaidAmount = 0;
        $this->useCustomerOverpayment = false;
        $this->customerOverpaymentToApply = 0;
        $this->resetPaymentData();
    }

    /**
     * Toggle invoice selection
     */
    public function toggleInvoiceSelection($saleId)
    {
        if (in_array($saleId, $this->selectedInvoices)) {
            $this->selectedInvoices = array_values(array_diff($this->selectedInvoices, [$saleId]));
        } else {
            $this->selectedInvoices[] = $saleId;
        }

        $this->calculateTotalDue();
        $this->totalPaymentAmount = null;
        $this->remainingAmount = $this->totalDueAmount;
        $this->initializeAllocations();
    }

    /**
     * Select all invoices
     */
    public function selectAllInvoices()
    {
        $this->selectedInvoices = array_column($this->customerSales, 'id');
        $this->calculateTotalDue();
        $this->totalPaymentAmount = null;
        $this->remainingAmount = $this->totalDueAmount;
        $this->initializeAllocations();
    }

    /**
     * Clear invoice selection
     */
    public function clearInvoiceSelection()
    {
        $this->selectedInvoices = [];
        $this->totalDueAmount = 0;
        $this->totalPaymentAmount = null;
        $this->remainingAmount = 0;
        $this->allocations = [];
    }

    /**
     * Load customer sales with opening balance
     */
    private function loadCustomerSales()
    {
        if (!$this->selectedCustomer) return;

        // Start with opening balance if customer has one
        $salesList = [];
        if ($this->selectedCustomer->opening_balance > 0) {
            $salesList[] = [
                'id' => 'opening_balance_' . $this->selectedCustomer->id,
                'invoice_number' => 'Opening Balance',
                'sale_id' => 'OB',
                'sale_date' => 'N/A',
                'total_amount' => $this->selectedCustomer->opening_balance,
                'due_amount' => $this->selectedCustomer->opening_balance,
                'paid_amount' => 0,
                'payment_status' => 'pending',
                'items_count' => 0,
                'is_opening_balance' => true,
            ];
        }

        $query = Sale::with(['items', 'payments', 'returns'])
            ->where('customer_id', $this->selectedCustomer->id)
            ->where(function ($query) {
                $query->where('payment_status', 'pending')
                    ->orWhere('payment_status', 'partial');
            });

        // Filter by user for staff
        if ($this->isStaff()) {
            $query->where('user_id', Auth::id())->where('sale_type', 'staff');
        }

        $sales = $query->orderBy('created_at', 'asc')
            ->get();

        $mappedSales = $sales->map(function ($sale) {
            $paidAmount = $sale->total_amount - $sale->due_amount;

            // Note: due_amount is already adjusted by return processing in ReturnProduct component
            // Do NOT calculate return amounts here to avoid double reduction

            return [
                'id' => $sale->id,
                'invoice_number' => $sale->invoice_number,
                'sale_id' => $sale->sale_id,
                'sale_date' => $sale->created_at->format('M d, Y'),
                'total_amount' => $sale->total_amount,
                'due_amount' => $sale->due_amount,
                'paid_amount' => $paidAmount,
                'payment_status' => $sale->payment_status,
                'items_count' => $sale->items->count(),
                'is_opening_balance' => false,
            ];
        })->filter(function ($sale) {
            // Only show sales with due amount > 0
            return $sale['due_amount'] > 0.01;
        })->values()->toArray();

        // Merge opening balance with sales
        $this->customerSales = array_merge($salesList, $mappedSales);

        $this->calculateTotalDue();
    }

    /**
     * Calculate total due amount for selected invoices (including opening balance if selected)
     */
    private function calculateTotalDue()
    {
        $this->totalDueAmount = collect($this->customerSales)
            ->whereIn('id', $this->selectedInvoices)
            ->sum('due_amount');
        $this->remainingAmount = $this->totalDueAmount;

        // Reset overpayment usage when invoice selection changes
        $this->useCustomerOverpayment = false;
        $this->customerOverpaymentToApply = 0;
    }

    private function calculateRemainingAmount()
    {
        $paymentAmount = (float) ($this->totalPaymentAmount ?: 0);
        $creditAmount = (float) ($this->customerOverpaymentToApply ?: 0);
        $this->remainingAmount = $this->totalDueAmount - $paymentAmount - $creditAmount;
    }

    public function toggleCustomerOverpayment()
    {
        $this->useCustomerOverpayment = !$this->useCustomerOverpayment;

        if ($this->useCustomerOverpayment && $this->customerOverpaidAmount > 0) {
            $this->customerOverpaymentToApply = min($this->customerOverpaidAmount, $this->totalDueAmount);
        } else {
            $this->customerOverpaymentToApply = 0;
        }

        $this->calculateRemainingAmount();
        $this->autoAllocatePayment();
    }

    public function updatedCustomerOverpaymentToApply()
    {
        if ($this->customerOverpaymentToApply > $this->customerOverpaidAmount) {
            $this->customerOverpaymentToApply = $this->customerOverpaidAmount;
        }
        if ($this->customerOverpaymentToApply > $this->totalDueAmount) {
            $this->customerOverpaymentToApply = $this->totalDueAmount;
        }
        if ($this->customerOverpaymentToApply < 0) {
            $this->customerOverpaymentToApply = 0;
        }

        $this->calculateRemainingAmount();
        $this->autoAllocatePayment();
    }

    private function initializeAllocations()
    {
        $this->allocations = [];

        foreach ($this->customerSales as $sale) {
            if (in_array($sale['id'], $this->selectedInvoices)) {
                $this->allocations[$sale['id']] = [
                    'sale_id' => $sale['id'],
                    'invoice_number' => $sale['invoice_number'],
                    'due_amount' => $sale['due_amount'],
                    'payment_amount' => 0,
                    'is_fully_paid' => false
                ];
            }
        }
    }

    private function autoAllocatePayment()
    {
        $remainingPayment = (float) ($this->totalPaymentAmount ?: 0) + (float) ($this->customerOverpaymentToApply ?: 0);

        foreach ($this->customerSales as $sale) {
            $saleId = $sale['id'];

            // Only allocate to selected invoices
            if (!in_array($saleId, $this->selectedInvoices)) {
                continue;
            }

            $dueAmount = $sale['due_amount'];

            if ($remainingPayment <= 0) {
                $this->allocations[$saleId]['payment_amount'] = 0;
                $this->allocations[$saleId]['is_fully_paid'] = false;
            } elseif ($remainingPayment >= $dueAmount) {
                $this->allocations[$saleId]['payment_amount'] = $dueAmount;
                $this->allocations[$saleId]['is_fully_paid'] = true;
                $remainingPayment -= $dueAmount;
            } else {
                $this->allocations[$saleId]['payment_amount'] = $remainingPayment;
                $this->allocations[$saleId]['is_fully_paid'] = false;
                $remainingPayment = 0;
            }
        }
    }

    private function syncChequePaymentAmount(): void
    {
        if ($this->paymentData['payment_method'] !== 'cheque') {
            return;
        }

        $this->totalPaymentAmount = round((float) collect($this->cheques)->sum(function ($cheque) {
            return (float) ($cheque['amount'] ?? 0);
        }), 2);

        $this->calculateRemainingAmount();
        $this->autoAllocatePayment();
    }

    public function openPaymentModal()
    {
        if (empty($this->selectedInvoices)) {
            $this->dispatch('show-toast', [
                'type' => 'error',
                'message' => 'Please select at least one invoice to make a payment.'
            ]);
            return;
        }

        // Validate cheque entries for cheque payments
        if ($this->paymentData['payment_method'] === 'cheque') {
            if (count($this->cheques) === 0 && (float) ($this->totalPaymentAmount ?: 0) > 0) {
                $this->dispatch('show-toast', [
                    'type' => 'error',
                    'message' => 'Please add at least one cheque for cheque payments.'
                ]);
                return;
            }

            $this->syncChequePaymentAmount();
        }

        // Validate payment amount
        if (((float) ($this->totalPaymentAmount ?: 0) <= 0) && ((float) ($this->customerOverpaymentToApply ?: 0) <= 0)) {
            $this->dispatch('show-toast', [
                'type' => 'error',
                'message' => 'Please enter a payment amount or apply customer overpayment credit.'
            ]);
            return;
        }

        // Allocate payment
        $this->autoAllocatePayment();

        // Show modal
        $this->showPaymentModal = true;
    }

    public function closePaymentModal()
    {
        $this->showPaymentModal = false;
        $this->paymentSuccess = false;
    }

    public function closeViewModal()
    {
        $this->showViewModal = false;
        $this->selectedSale = null;
    }

    public function openReceiptModal()
    {
        $this->showReceiptModal = true;
    }

    public function closeReceiptModal()
    {
        $this->showReceiptModal = false;
        $this->latestPayment = null;

        // Reset everything
        $this->selectedCustomer = null;
        $this->customerSales = [];
        $this->selectedInvoices = [];
        $this->allocations = [];
        $this->totalDueAmount = 0;
        $this->totalPaymentAmount = null;
        $this->remainingAmount = 0;
        $this->customerOverpaidAmount = 0;
        $this->useCustomerOverpayment = false;
        $this->customerOverpaymentToApply = 0;
        $this->cheque = [
            'cheque_number' => '',
            'bank_name' => '',
            'cheque_date' => now()->format('Y-m-d'),
            'amount' => ''
        ];
        $this->cheques = [];
        $this->tempCheque = [
            'cheque_number' => '',
            'bank_name' => '',
            'cheque_date' => now()->format('Y-m-d'),
            'amount' => ''
        ];
        $this->search = '';
        $this->resetPaymentData();

        // Reset page
        $this->resetPage();

        // Dispatch event to refresh the page
        $this->dispatch('payment-completed');
    }

    private function resetPaymentData()
    {
        $this->paymentData = [
            'payment_date' => now()->format('Y-m-d'),
            'payment_method' => 'cash',
            'reference_number' => '',
            'notes' => ''
        ];
        $this->totalPaymentAmount = null;
        $this->useCustomerOverpayment = false;
        $this->customerOverpaymentToApply = 0;
        $this->cheque = [
            'cheque_number' => '',
            'bank_name' => '',
            'cheque_date' => now()->format('Y-m-d'),
            'amount' => ''
        ];
        $this->cheques = [];
        $this->tempCheque = [
            'cheque_number' => '',
            'bank_name' => '',
            'cheque_date' => now()->format('Y-m-d'),
            'amount' => ''
        ];
        $this->bankTransfer = [
            'bank_name' => '',
            'transfer_date' => now()->format('Y-m-d'),
            'reference_number' => ''
        ];
    }

    public function addCheque()
    {
        $this->validate([
            'tempCheque.cheque_number' => 'required|string|max:50',
            'tempCheque.bank_name' => 'required|string|max:100',
            'tempCheque.cheque_date' => 'required|date',
            'tempCheque.amount' => 'required|numeric|min:0.01',
        ], [
            'tempCheque.cheque_number.required' => 'Cheque number is required.',
            'tempCheque.bank_name.required' => 'Bank name is required.',
            'tempCheque.cheque_date.required' => 'Cheque date is required.',
            'tempCheque.amount.required' => 'Cheque amount is required.',
        ]);

        $chequeNumber = trim((string) $this->tempCheque['cheque_number']);

        $existsInCurrentList = collect($this->cheques)->contains(function ($cheque) use ($chequeNumber) {
            return $cheque['cheque_number'] === $chequeNumber;
        });

        if ($existsInCurrentList) {
            $this->dispatch('show-toast', [
                'type' => 'error',
                'message' => "Cheque number '{$chequeNumber}' is already added in this payment."
            ]);
            return;
        }

        if (Cheque::where('cheque_number', $chequeNumber)->exists()) {
            $this->dispatch('show-toast', [
                'type' => 'error',
                'message' => "Cheque number '{$chequeNumber}' already exists in the system."
            ]);
            return;
        }

        $this->cheques[] = [
            'cheque_number' => $chequeNumber,
            'bank_name' => trim((string) $this->tempCheque['bank_name']),
            'cheque_date' => $this->tempCheque['cheque_date'],
            'amount' => (float) $this->tempCheque['amount'],
        ];

        $this->syncChequePaymentAmount();

        $this->tempCheque = [
            'cheque_number' => '',
            'bank_name' => '',
            'cheque_date' => now()->format('Y-m-d'),
            'amount' => ''
        ];
    }

    public function removeCheque($index)
    {
        unset($this->cheques[$index]);
        $this->cheques = array_values($this->cheques);
        $this->syncChequePaymentAmount();
    }

    public function viewSale($saleId)
    {
        $this->selectedSale = Sale::with(['customer', 'items', 'payments', 'returns.product'])->find($saleId);
        $this->showViewModal = true;
    }

    public function processPayment()
    {
        Log::info('Payment processing started', [
            'customer_id' => $this->selectedCustomer->id,
            'amount' => $this->totalPaymentAmount,
            'method' => $this->paymentData['payment_method']
        ]);

        if ($this->paymentData['payment_method'] === 'cheque') {
            $this->syncChequePaymentAmount();
        }

        if (((float) ($this->totalPaymentAmount ?: 0) <= 0) && ((float) ($this->customerOverpaymentToApply ?: 0) <= 0)) {
            $this->dispatch('show-toast', [
                'type' => 'error',
                'message' => 'Please enter a payment amount or apply customer overpayment credit.'
            ]);
            return;
        }

        // Validate inputs
        try {
            $this->validate();
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Validation failed', ['errors' => $e->errors()]);

            // Get first error message
            $firstError = collect($e->errors())->flatten()->first();

            $this->dispatch('show-toast', [
                'type' => 'error',
                'message' => $firstError ?? 'Please fill all required fields correctly.'
            ]);
            return;
        }

        // Additional validation: Check cheque list for cheque payments
        if ($this->paymentData['payment_method'] === 'cheque') {
            if (count($this->cheques) === 0 && (float) ($this->totalPaymentAmount ?: 0) > 0) {
                $this->dispatch('show-toast', [
                    'type' => 'error',
                    'message' => 'Please add at least one cheque for cheque payment.'
                ]);
                return;
            }

            $chequeNumbers = collect($this->cheques)->pluck('cheque_number');
            if ($chequeNumbers->count() !== $chequeNumbers->unique()->count()) {
                $this->dispatch('show-toast', [
                    'type' => 'error',
                    'message' => 'Duplicate cheque numbers found in the current payment list.'
                ]);
                return;
            }

            $existingCheques = Cheque::whereIn('cheque_number', $chequeNumbers->all())->pluck('cheque_number')->toArray();
            if (!empty($existingCheques)) {
                $this->dispatch('show-toast', [
                    'type' => 'error',
                    'message' => 'These cheque numbers already exist: ' . implode(', ', $existingCheques)
                ]);
                return;
            }

            $this->syncChequePaymentAmount();
        }

        try {
            DB::beginTransaction();

            // Apply customer overpayment credit first (if selected)
            $availableOverpaid = (float) ($this->selectedCustomer->overpaid_amount ?? 0);
            $overpaymentUsed = min(
                (float) ($this->customerOverpaymentToApply ?: 0),
                $availableOverpaid,
                (float) ($this->totalDueAmount ?: 0)
            );

            $this->customerOverpaymentToApply = $overpaymentUsed;
            $this->calculateRemainingAmount();
            $this->autoAllocatePayment();

            if ($overpaymentUsed > 0) {
                $this->selectedCustomer->overpaid_amount = max(0, $availableOverpaid - $overpaymentUsed);
                $this->selectedCustomer->save();
            }

            $totalProcessed = 0;
            $processedInvoices = [];

            // Create a single payment record for customer (not tied to specific sale)
            $paymentData = [
                'customer_id' => $this->selectedCustomer->id,
                'amount' => (float) ($this->totalPaymentAmount ?: 0),
                'payment_method' => $this->paymentData['payment_method'],
                'payment_reference' => $this->paymentData['reference_number'] ?? null,
                'payment_date' => $this->paymentData['payment_date'],
                'status' => 'paid',
                'is_completed' => 1,
                'notes' => $this->paymentData['notes'] ?? null,
                'created_by' => Auth::id(),
            ];

            // Add bank transfer details if payment method is bank transfer
            if ($this->paymentData['payment_method'] === 'bank_transfer') {
                $paymentData['bank_name'] = $this->bankTransfer['bank_name'];
                $paymentData['transfer_date'] = $this->bankTransfer['transfer_date'];
                $paymentData['transfer_reference'] = $this->bankTransfer['reference_number'];
            }

            $payment = Payment::create($paymentData);

            Log::info('Main payment created', ['payment_id' => $payment->id]);

            // Process cheques if payment method is cheque
            if ($this->paymentData['payment_method'] === 'cheque') {
                foreach ($this->cheques as $chequeItem) {
                    Cheque::create([
                        'payment_id' => $payment->id,
                        'cheque_number' => $chequeItem['cheque_number'],
                        'bank_name' => $chequeItem['bank_name'],
                        'cheque_date' => $chequeItem['cheque_date'],
                        'cheque_amount' => (float) $chequeItem['amount'],
                        'status' => 'pending',
                        'customer_id' => $this->selectedCustomer->id,
                    ]);
                }
                Log::info('Cheques created', ['count' => count($this->cheques)]);
            }

            // Process each sale allocation
            foreach ($this->customerSales as $sale) {
                $saleId = $sale['id'];

                // Only process selected invoices
                if (!in_array($saleId, $this->selectedInvoices)) {
                    continue;
                }

                $allocation = $this->allocations[$saleId];
                $paymentAmount = $allocation['payment_amount'];

                if ($paymentAmount <= 0) continue;

                // Check if this is opening balance
                if (isset($sale['is_opening_balance']) && $sale['is_opening_balance']) {
                    // Reduce opening balance from customer table
                    $newOpeningBalance = max(0, $this->selectedCustomer->opening_balance - $paymentAmount);
                    $this->selectedCustomer->opening_balance = $newOpeningBalance;
                    $this->selectedCustomer->save();

                    Log::info('Opening balance reduced', [
                        'customer_id' => $this->selectedCustomer->id,
                        'payment_amount' => $paymentAmount,
                        'remaining_balance' => $newOpeningBalance
                    ]);
                } else {
                    // Update sale with adjusted due amount
                    $saleModel = Sale::find($saleId);
                    if ($saleModel) {
                        $newDueAmount = $saleModel->due_amount - $paymentAmount;

                        // Note: due_amount is already adjusted by return processing
                        // Do not recalculate returns here
                        $saleModel->due_amount = max(0, $newDueAmount);

                        if ($saleModel->due_amount <= 0.01) {
                            $saleModel->payment_status = 'paid';
                            $saleModel->due_amount = 0;
                        } else {
                            $saleModel->payment_status = 'partial';
                        }

                        $saleModel->save();

                        // Create payment allocation record
                        DB::table('payment_allocations')->insert([
                            'payment_id' => $payment->id,
                            'sale_id' => $saleId,
                            'allocated_amount' => $paymentAmount,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);

                        Log::info('Sale updated and allocation created', [
                            'sale_id' => $saleId,
                            'payment_amount' => $paymentAmount,
                            'new_due' => $saleModel->due_amount
                        ]);
                    }
                }

                // Also reduce customer's due_amount
                if ($this->selectedCustomer->due_amount > 0) {
                    $this->selectedCustomer->due_amount = max(0, $this->selectedCustomer->due_amount - $paymentAmount);
                    $this->selectedCustomer->save();
                }

                $totalProcessed += $paymentAmount;
                $processedInvoices[] = $sale['invoice_number'];
            }

            $cashAppliedToInvoices = max(0, (float) $totalProcessed - (float) $overpaymentUsed);
            $overpaidAmount = max(0, ((float) $this->totalPaymentAmount - (float) $cashAppliedToInvoices));
            if ($overpaidAmount > 0) {
                $this->selectedCustomer->overpaid_amount = (float) ($this->selectedCustomer->overpaid_amount ?? 0) + $overpaidAmount;
                $this->selectedCustomer->save();

                Log::info('Customer overpayment credited', [
                    'customer_id' => $this->selectedCustomer->id,
                    'overpaid_amount' => $overpaidAmount,
                    'new_balance' => $this->selectedCustomer->overpaid_amount,
                ]);
            }

            DB::commit();

            Log::info('Payment processed successfully', [
                'total_processed' => $totalProcessed,
                'invoices' => $processedInvoices,
                'payment_id' => $payment->id
            ]);

            $this->paymentSuccess = true;
            $this->showPaymentModal = false;
            $this->latestPayment = $payment;

            $this->dispatch('show-toast', [
                'type' => 'success',
                'message' => "Payment of Rs." . number_format((float) $this->totalPaymentAmount, 2) . " processed successfully!"
            ]);

            // Open receipt modal
            $this->openReceiptModal();
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Payment processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Check if it's a duplicate entry error for cheque number
            $errorMessage = $e->getMessage();
            if (strpos($errorMessage, 'Duplicate entry') !== false && strpos($errorMessage, 'cheques_cheque_number_unique') !== false) {
                // Extract cheque number from error message
                preg_match("/Duplicate entry '([^']+)'/", $errorMessage, $matches);
                $chequeNumber = $matches[1] ?? 'unknown';

                $this->dispatch('show-toast', [
                    'type' => 'error',
                    'message' => "Cheque number '{$chequeNumber}' already exists in the system. Please use a different cheque number."
                ]);
            } else {
                $this->dispatch('show-toast', [
                    'type' => 'error',
                    'message' => 'Failed to process payment. Please check your input and try again.'
                ]);
            }
        }
    }

    public function downloadReceipt()
    {
        if (!$this->latestPayment) {
            $this->dispatch('show-toast', [
                'type' => 'error',
                'message' => 'No payment receipt available to download.'
            ]);
            return;
        }

        try {
            // Load payment with relationships
            $payment = Payment::with(['cheques'])
                ->find($this->latestPayment->id);

            // Get allocations from payment_allocations table with return information
            $allocations = DB::table('payment_allocations')
                ->join('sales', 'payment_allocations.sale_id', '=', 'sales.id')
                ->where('payment_allocations.payment_id', $payment->id)
                ->select(
                    'sales.id as sale_id',
                    'sales.invoice_number',
                    'sales.total_amount',
                    'payment_allocations.allocated_amount'
                )
                ->get();

            $receiptData = [
                'payment' => $payment,
                'customer' => $this->selectedCustomer,
                'received_by' => Auth::user()->name,
                'payment_date' => $payment->payment_date,
                'allocations' => $allocations,
            ];

            $pdf = PDF::loadView('admin.receipts.payment-receipt', $receiptData);
            $pdf->setPaper('a4', 'portrait');

            $filename = 'payment-receipt-' . $payment->id . '-' . date('Y-m-d') . '.pdf';

            return response()->streamDownload(
                function () use ($pdf) {
                    echo $pdf->output();
                },
                $filename
            );
        } catch (\Exception $e) {
            Log::error('Receipt download failed', [
                'error' => $e->getMessage(),
                'payment_id' => $this->latestPayment->id
            ]);

            $this->dispatch('show-toast', [
                'type' => 'error',
                'message' => 'Failed to generate receipt: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Calculate customer total due amount including opening balance
     */
    public function getCustomerTotalDue($customer)
    {
        $salesDue = $customer->sales
            ->whereIn('payment_status', ['pending', 'partial'])
            ->sum(function ($sale) {
                $returnAmount = $sale->returns ? $sale->returns->sum('total_amount') : 0;
                return max(0, $sale->due_amount - $returnAmount);
            });

        return ($customer->opening_balance ?? 0) + $salesDue;
    }

    public function getCustomersProperty()
    {
        return Customer::with(['sales' => function ($query) {
            $query->where(function ($q) {
                $q->where('payment_status', 'pending')
                    ->orWhere('payment_status', 'partial');
            });

            // Filter by user if not 'all'
            if ($this->userFilter !== 'all') {
                if ($this->userFilter === 'admin') {
                    // Show only admin sales (where user_id is null or user role is admin)
                    $query->where(function ($q) {
                        $q->whereNull('user_id')
                            ->orWhereHas('user', function ($userQuery) {
                                $userQuery->where('role', 'admin');
                            });
                    });
                } else {
                    // Filter by specific user ID
                    $query->where('user_id', $this->userFilter);
                }
            }
        }])
            ->where(function ($query) {
                // Show customers with either pending/partial sales OR opening balance
                $query->whereHas('sales', function ($q) {
                    $q->where(function ($sq) {
                        $sq->where('payment_status', 'pending')
                            ->orWhere('payment_status', 'partial');
                    });

                    // Apply the same user filter to whereHas
                    if ($this->userFilter !== 'all') {
                        if ($this->userFilter === 'admin') {
                            $q->where(function ($sq) {
                                $sq->whereNull('user_id')
                                    ->orWhereHas('user', function ($userQuery) {
                                        $userQuery->where('role', 'admin');
                                    });
                            });
                        } else {
                            $q->where('user_id', $this->userFilter);
                        }
                    }
                })
                    ->orWhere('opening_balance', '>', 0); // Also show if they have opening balance
            })
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'like', '%' . $this->search . '%')
                        ->orWhere('phone', 'like', '%' . $this->search . '%')
                        ->orWhere('email', 'like', '%' . $this->search . '%');
                });
            })
            ->orderBy('name')
            ->paginate(10);
    }

    public function getUsersProperty()
    {
        return User::where('role', 'staff')
            ->orderBy('name')
            ->get();
    }

    public function render()
    {
        return view('livewire.admin.add-customer-receipt', [
            'customers' => $this->customers,
            'users' => $this->users
        ])->layout($this->layout);
    }
}
