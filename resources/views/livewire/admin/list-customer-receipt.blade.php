<div class="container-fluid py-4">
    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold text-dark mb-2">
                <i class="bi bi-receipt text-success me-2"></i> Customer Payment List
            </h3>
            <p class="text-muted mb-0">View all customer receipts and payment allocations</p>
        </div>
    </div>

    {{-- Customer List Table --}}
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center bg-light">
            <h5 class="fw-bold mb-0">
                <i class="bi bi-people me-2"></i> Customers with Payments
            </h5>
            <span class="badge bg-primary">{{ $customers->total() }} customers</span>
        </div>
        <div class="card-body p-0 overflow-auto">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Customer Name</th>
                            <th class="text-center">Total Paid</th>
                            <th class="text-center">No. of Receipts</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($customers as $customer)
                        <tr wire:key="customer-{{ $customer->id }}">
                            <td class="ps-4 fw-semibold">{{ $customer->name }}</td>
                            <td class="text-center">Rs.{{ number_format($customer->total_paid, 2) }}</td>
                            <td class="text-center">{{ $customer->receipts_count }}</td>
                            <td class="text-center">
                                <button class="btn btn-sm btn-primary" wire:click="showCustomerPayments({{ $customer->id }})">
                                    <i class="bi bi-eye me-1"></i> View Receipts
                                </button>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="text-center text-muted py-4">
                                <i class="bi bi-x-circle display-4 d-block mb-2"></i>
                                No customer payments found.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            @if($customers->hasPages())
            <div class="card-footer">
                <div class="d-flex justify-content-center">
                    {{ $customers->links('livewire.custom-pagination') }}
                </div>
            </div>
            @endif
        </div>
    </div>

    {{-- Payment Details Modal --}}
    @if($showPaymentModal && $selectedCustomer)
    <div class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5);">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-gradient-success text-white">
                    <div>
                        <h5 class="modal-title fw-bold mb-1">
                            <i class="bi bi-receipt-cutoff me-2"></i> Payment History
                        </h5>
                        <small class="opacity-75">{{ $selectedCustomer->name }}</small>
                    </div>
                    <button type="button" class="btn-close btn-close-white" wire:click="closePaymentModal"></button>
                </div>
                <div class="modal-body p-0">
                    {{-- Customer Info Card --}}
                    <div class="bg-light border-bottom p-3">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-person-circle text-success me-2" style="font-size: 1.5rem;"></i>
                                    <div>
                                        <small class="text-muted d-block">Customer Name</small>
                                        <strong>{{ $selectedCustomer->name }}</strong>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-telephone text-primary me-2" style="font-size: 1.5rem;"></i>
                                    <div>
                                        <small class="text-muted d-block">Phone</small>
                                        <strong>{{ $selectedCustomer->phone ?? 'N/A' }}</strong>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-envelope text-info me-2" style="font-size: 1.5rem;"></i>
                                    <div>
                                        <small class="text-muted d-block">Email</small>
                                        <strong>{{ $selectedCustomer->email }}</strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-md-6">
                                <div class="card bg-white border-0 shadow-sm">
                                    <div class="card-body py-2">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="text-muted">Total Payments Made</span>
                                            <span class="badge bg-success rounded-pill">{{ count($payments) }} receipts</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card bg-success text-white border-0 shadow-sm">
                                    <div class="card-body py-2">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span>Total Amount Paid</span>
                                            <strong class="fs-5">Rs.{{ number_format($payments->sum('amount'), 2) }}</strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Payment List --}}
                    <div class="p-3">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        <th>Receipt ID</th>
                                        <th>Date</th>
                                        <th>Payment Method</th>
                                        <th class="text-end">Amount</th>
                                        <th class="text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($payments as $index => $payment)
                                    <tr wire:key="payment-{{ $payment->id }}">
                                        <td>{{ $index + 1 }}</td>
                                        <td><span class="badge bg-success">#{{ $payment->id }}</span></td>
                                        <td>
                                            <i class="bi bi-calendar3 me-1"></i>
                                            {{ $payment->payment_date ? date('M d, Y', strtotime($payment->payment_date)) : '-' }}
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary">
                                                <i class="bi bi-{{ $payment->payment_method === 'cash' ? 'cash' : ($payment->payment_method === 'cheque' ? 'receipt' : 'bank') }} me-1"></i>
                                                {{ ucfirst(str_replace('_', ' ', $payment->payment_method)) }}
                                            </span>
                                        </td>
                                        <td class="text-end fw-bold text-success">Rs.{{ number_format($payment->amount, 2) }}</td>
                                        <td class="text-center">
                                            <button class="btn btn-sm btn-info" wire:click="viewPaymentReceipt({{ $payment->id }})">
                                                <i class="bi bi-receipt me-1"></i> View Receipt
                                            </button>
                                        </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="6" class="text-center py-5 text-muted">
                                            <i class="bi bi-inbox" style="font-size: 3rem;"></i>
                                            <p class="mt-3">No payments found for this customer.</p>
                                        </td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" wire:click="closePaymentModal">
                        <i class="bi bi-x-circle me-1"></i> Close
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- Individual Payment Receipt Modal --}}
    @if($showReceiptModal && $selectedPayment)
    <div class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5); z-index: 1060;">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <div>
                        <h5 class="modal-title fw-bold mb-0">
                            <i class="bi bi-receipt-cutoff me-2"></i> Payment Receipt #{{ $selectedPayment->id }}
                        </h5>
                    </div>
                    <button type="button" class="btn-close btn-close-white" wire:click="closeReceiptModal"></button>
                </div>
                <div class="modal-body">
                    {{-- Receipt Header --}}
                    <div class="text-center mb-4 pb-3 border-bottom">
                        <h4 class="fw-bold text-success mb-1">PAYMENT RECEIPT</h4>
                        <p class="text-muted mb-0">Receipt #{{ $selectedPayment->id }}</p>
                    </div>

                    {{-- Payment & Customer Information --}}
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="card border-0 bg-light">
                                <div class="card-body">
                                    <h6 class="text-muted mb-3 fw-bold">CUSTOMER INFORMATION</h6>
                                    <table class="table table-sm table-borderless mb-0">
                                        <tr>
                                            <td class="text-muted" style="width: 40%;">Name:</td>
                                            <td class="fw-semibold">{{ $selectedPayment->customer->name ?? 'N/A' }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">Phone:</td>
                                            <td class="fw-semibold">{{ $selectedPayment->customer->phone ?? 'N/A' }}</td>
                                        </tr>
                                        @if($selectedPayment->customer && $selectedPayment->customer->email)
                                        <tr>
                                            <td class="text-muted">Email:</td>
                                            <td class="fw-semibold">{{ $selectedPayment->customer->email }}</td>
                                        </tr>
                                        @endif
                                    </table>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card border-0 bg-light">
                                <div class="card-body">
                                    <h6 class="text-muted mb-3 fw-bold">PAYMENT INFORMATION</h6>
                                    <table class="table table-sm table-borderless mb-0">
                                        <tr>
                                            <td class="text-muted" style="width: 40%;">Date:</td>
                                            <td class="fw-semibold">{{ $selectedPayment->payment_date ? date('M d, Y', strtotime($selectedPayment->payment_date)) : '-' }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">Method:</td>
                                            <td>
                                                <span class="badge bg-secondary">
                                                    {{ ucfirst(str_replace('_', ' ', $selectedPayment->payment_method)) }}
                                                </span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">Amount:</td>
                                            <td class="fw-bold text-success fs-5">Rs.{{ number_format($selectedPayment->amount, 2) }}</td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Payment Method Details --}}
                    @if($selectedPayment->payment_method === 'cheque' && $selectedPayment->cheques && count($selectedPayment->cheques) > 0)
                    <div class="card border-info mb-4">
                        <div class="card-header bg-info text-white">
                            <h6 class="mb-0 fw-bold"><i class="bi bi-receipt me-2"></i>Cheque Details</h6>
                        </div>
                        <div class="card-body">
                            @foreach($selectedPayment->cheques as $cheque)
                            <div class="row {{ !$loop->last ? 'mb-3 pb-3 border-bottom' : '' }}">
                                <div class="col-md-4">
                                    <small class="text-muted d-block">Cheque Number</small>
                                    <strong class="text-dark">{{ $cheque->cheque_number }}</strong>
                                </div>
                                <div class="col-md-4">
                                    <small class="text-muted d-block">Bank Name</small>
                                    <strong class="text-dark">{{ $cheque->bank_name }}</strong>
                                </div>
                                <div class="col-md-4">
                                    <small class="text-muted d-block">Cheque Date</small>
                                    <strong class="text-dark">{{ $cheque->cheque_date ? date('M d, Y', strtotime($cheque->cheque_date)) : '-' }}</strong>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @elseif($selectedPayment->payment_method === 'bank_transfer')
                    <div class="card border-info mb-4">
                        <div class="card-header bg-info text-white">
                            <h6 class="mb-0 fw-bold"><i class="bi bi-bank me-2"></i>Bank Transfer Details</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <small class="text-muted d-block">Bank Name</small>
                                    <strong class="text-dark">{{ $selectedPayment->bank_name ?? 'N/A' }}</strong>
                                </div>
                                <div class="col-md-6">
                                    <small class="text-muted d-block">Reference Number</small>
                                    <strong class="text-dark">{{ $selectedPayment->transfer_reference ?? $selectedPayment->payment_reference ?? 'N/A' }}</strong>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endif

                    {{-- Allocated Invoices --}}
                    <div class="card border-success mb-4">
                        <div class="card-header bg-success text-white">
                            <h6 class="mb-0 fw-bold"><i class="bi bi-file-earmark-text me-2"></i>Payment Allocation</h6>
                        </div>
                        <div class="card-body p-0">
                            {{-- Debug Info (Remove after testing) --}}
                            @if(config('app.debug'))
                            <div class="alert alert-info m-3">
                                <strong>Debug Info:</strong><br>
                                Payment ID: {{ $selectedPayment->id }}<br>
                                Allocations Count: {{ $selectedPayment->allocations ? $selectedPayment->allocations->count() : 'NULL' }}<br>
                                Allocations Loaded: {{ $selectedPayment->relationLoaded('allocations') ? 'YES' : 'NO' }}
                            </div>
                            @endif
                            
                            @if($selectedPayment->allocations && count($selectedPayment->allocations) > 0)
                            <div class="table-responsive" style="min-height: 100px !important;">
                                <table class="table table-bordered mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 15%;">Invoice ID</th>
                                            <th style="width: 35%;">Invoice Number</th>
                                            <th class="text-end" style="width: 25%;">Invoice Total</th>
                                            <th class="text-end" style="width: 25%;">Allocated Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($selectedPayment->allocations as $alloc)
                                        <tr>
                                            <td><span class="badge bg-dark">#{{ $alloc->sale_id }}</span></td>
                                            <td class="fw-semibold">{{ $alloc->sale ? $alloc->sale->invoice_number : 'N/A' }}</td>
                                            <td class="text-end">Rs.{{ $alloc->sale ? number_format($alloc->sale->total_amount, 2) : '0.00' }}</td>
                                            <td class="text-end fw-bold text-success">Rs.{{ number_format($alloc->allocated_amount, 2) }}</td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                    <tfoot class="table-light">
                                        <tr>
                                            <td colspan="3" class="text-end fw-bold">Total Allocated:</td>
                                            <td class="text-end fw-bold text-primary fs-5">Rs.{{ number_format($selectedPayment->allocations->sum('allocated_amount'), 2) }}</td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                            @else
                            <div class="p-4 text-center text-muted">
                                <i class="bi bi-exclamation-triangle fs-2 d-block mb-2"></i>
                                <p class="mb-0">No invoice allocation found for this payment</p>
                            </div>
                            @endif
                        </div>
                    </div>

                    {{-- Notes --}}
                    @if($selectedPayment->notes)
                    <div class="card border-warning">
                        <div class="card-header bg-warning">
                            <h6 class="mb-0 fw-bold"><i class="bi bi-chat-left-text me-2"></i>Notes</h6>
                        </div>
                        <div class="card-body">
                            <p class="mb-0">{{ $selectedPayment->notes }}</p>
                        </div>
                    </div>
                    @endif

                    {{-- Receipt Footer --}}
                    <div class="text-center mt-4 pt-3 border-top">
                        <small class="text-muted">
                            <i class="bi bi-calendar-check me-1"></i>
                            Receipt generated on {{ now()->format('M d, Y h:i A') }}
                        </small>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" wire:click="closeReceiptModal">
                        <i class="bi bi-x-circle me-1"></i> Close
                    </button>
                    <button type="button" class="btn btn-success" onclick="window.print()">
                        <i class="bi bi-printer me-1"></i> Print Receipt
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif

    <style>
        .sticky-top {
            position: sticky;
            z-index: 10;
        }

        .table th {
            font-weight: 600;
        }

        .badge {
            font-size: 0.75em;
        }

        .modal.show {
            display: block !important;
        }

        .btn-group-sm>.btn {
            padding: 0.25rem 0.5rem;
        }

        .input-group-lg .form-control {
            font-size: 1.25rem;
            font-weight: 600;
        }

        .table-responsive {
            
            overflow-y: auto;
        }
    </style>
</div>