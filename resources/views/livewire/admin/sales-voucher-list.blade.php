<div x-data="listVoucherKeyboard()" @keydown.window="handleGlobalKey($event)">
    {{-- Flash Messages --}}
    @if (session()->has('success'))
    <div class="alert alert-success alert-dismissible fade show mb-2 py-2">
        <i class="bi bi-check-circle me-1"></i> {{ session('success') }}
        <button type="button" class="btn-close btn-sm" data-bs-dismiss="alert"></button>
    </div>
    @endif
    @if (session()->has('error'))
    <div class="alert alert-danger alert-dismissible fade show mb-2 py-2">
        <i class="bi bi-exclamation-circle me-1"></i> {{ session('error') }}
        <button type="button" class="btn-close btn-sm" data-bs-dismiss="alert"></button>
    </div>
    @endif

    {{-- Header --}}
    <div class="card shadow-sm mb-2 border-0" style="background: linear-gradient(135deg, #0f766e 0%, #115e59 100%);">
        <div class="card-body py-2 px-3">
            <div class="row align-items-center">
                <div class="col">
                    <h5 class="text-white mb-0 fw-bold">
                        <i class="bi bi-list-ul me-2"></i>Sales Voucher — List
                    </h5>
                </div>
                <div class="col-auto d-flex gap-2">
                    <a href="{{ route('admin.sales-voucher-add') }}" class="btn btn-sm btn-light">
                        <i class="bi bi-plus-circle me-1"></i>Add Voucher
                        <kbd class="ms-1" style="font-size:9px">Alt+A</kbd>
                    </a>
                    <a href="{{ route('admin.sales-voucher-modify') }}" class="btn btn-sm btn-outline-light">
                        <i class="bi bi-pencil me-1"></i>Modify
                        <kbd class="ms-1" style="font-size:9px">Alt+M</kbd>
                    </a>
                </div>
            </div>
        </div>
    </div>

    {{-- Stats Row --}}
    <div class="row g-2 mb-2">
        <div class="col-md-3">
            <div class="card border shadow-sm">
                <div class="card-body py-2 px-3 text-center">
                    <div class="small text-muted">Total Vouchers</div>
                    <div class="fs-5 fw-bold" style="color:#0f766e;">{{ $this->voucherCount }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border shadow-sm">
                <div class="card-body py-2 px-3 text-center">
                    <div class="small text-muted">Total Amount</div>
                    <div class="fs-5 fw-bold" style="color:#0f766e;">Rs.{{ number_format($this->totalAmount, 2) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border shadow-sm">
                <div class="card-body py-2 px-3 text-center">
                    <div class="small text-muted">Period</div>
                    <div class="small fw-semibold">{{ \Carbon\Carbon::parse($dateFrom)->format('d M') }} - {{ \Carbon\Carbon::parse($dateTo)->format('d M Y') }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border shadow-sm">
                <div class="card-body py-2 px-3 text-center">
                    <div class="small text-muted">Showing</div>
                    <div class="small fw-semibold">{{ $vouchers->count() }} of {{ $vouchers->total() }}</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="card shadow-sm mb-2 border">
        <div class="card-body py-2 px-3">
            <div class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small fw-semibold text-muted mb-0">Search</label>
                    <input type="text" class="form-control form-control-sm"
                        wire:model.live.debounce.300ms="search"
                        placeholder="Invoice #, Customer..." id="listSearchInput">
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold text-muted mb-0">From</label>
                    <input type="date" class="form-control form-control-sm" wire:model.live="dateFrom">
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold text-muted mb-0">To</label>
                    <input type="date" class="form-control form-control-sm" wire:model.live="dateTo">
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold text-muted mb-0">Customer</label>
                    <select class="form-select form-select-sm" wire:model.live="customerFilter">
                        <option value="">All</option>
                        @foreach($customers as $c)
                        <option value="{{ $c->id }}">{{ $c->business_name ?? $c->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-1">
                    <label class="form-label small fw-semibold text-muted mb-0">Type</label>
                    <select class="form-select form-select-sm" wire:model.live="paymentTypeFilter">
                        <option value="">All</option>
                        <option value="cash">Cash</option>
                        <option value="credit">Credit</option>
                    </select>
                </div>
                <div class="col-md-1">
                    <label class="form-label small fw-semibold text-muted mb-0">Status</label>
                    <select class="form-select form-select-sm" wire:model.live="statusFilter">
                        <option value="">All</option>
                        <option value="confirm">Confirmed</option>
                        <option value="pending">Pending</option>
                        <option value="rejected">Rejected</option>
                    </select>
                </div>
                <div class="col-md-1">
                    <button class="btn btn-sm btn-outline-secondary w-100" wire:click="clearFilters" title="Clear filters">
                        <i class="bi bi-x-circle"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Vouchers Table --}}
    <div class="card shadow-sm border">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover table-bordered mb-0" style="font-size:12px;">
                    <thead class="table-dark">
                        <tr>
                            <th style="width:40px" class="text-center">#</th>
                            <th>Voucher / Invoice #</th>
                            <th>Date</th>
                            <th>Customer</th>
                            <th class="text-center">Items</th>
                            <th class="text-end">Amount</th>
                            <th class="text-center">Payment</th>
                            <th class="text-center">Status</th>
                            <th class="text-center" style="width:150px">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($vouchers as $index => $sale)
                        <tr wire:key="voucher-{{ $sale->id }}">
                            <td class="text-center text-muted">{{ $vouchers->firstItem() + $index }}</td>
                            <td>
                                <div class="fw-semibold">{{ $sale->invoice_number }}</div>
                                <div class="text-muted" style="font-size:10px">{{ $sale->sale_id }}</div>
                            </td>
                            <td>{{ $sale->created_at->format('d-M-Y') }}</td>
                            <td>
                                <div class="fw-medium">{{ $sale->customer->business_name ?? $sale->customer->name ?? 'N/A' }}</div>
                                <div class="text-muted" style="font-size:10px">{{ $sale->customer->phone ?? '' }}</div>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-secondary">{{ $sale->items->count() }}</span>
                            </td>
                            <td class="text-end fw-semibold">Rs.{{ number_format($sale->total_amount, 2) }}</td>
                            <td class="text-center">
                                @php $bt = $sale->billing_type ?? ($sale->payment_status === 'paid' ? 'cash' : 'credit'); @endphp
                                <span class="badge bg-{{ $bt === 'cash' ? 'success' : 'warning text-dark' }}">
                                    {{ ucfirst($bt) }}
                                </span>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-{{ $sale->status === 'confirm' ? 'success' : ($sale->status === 'pending' ? 'warning text-dark' : 'danger') }}">
                                    {{ ucfirst($sale->status) }}
                                </span>
                            </td>
                            <td class="text-center">
                                <div class="btn-group btn-group-sm">
                                    <button class="btn btn-outline-info" wire:click="viewVoucher({{ $sale->id }})" title="View">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                    <button class="btn btn-outline-primary" wire:click="modifyVoucher({{ $sale->id }})" title="Modify">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <a href="{{ route('admin.print.sale', $sale->id) }}" target="_blank" class="btn btn-outline-secondary" title="Print">
                                        <i class="bi bi-printer"></i>
                                    </a>
                                    <button class="btn btn-outline-danger" wire:click="confirmDelete({{ $sale->id }})" title="Delete">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">
                                <i class="bi bi-inbox display-6 d-block mb-2"></i>
                                No vouchers found for the selected criteria.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            @if($vouchers->hasPages())
            <div class="px-3 py-2 border-top">
                {{ $vouchers->links() }}
            </div>
            @endif
        </div>
    </div>

    {{-- View Modal --}}
    @if($showViewModal && $selectedSale)
    <div class="modal fade show d-block" tabindex="-1" style="background:rgba(0,0,0,0.5);">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header py-2" style="background:#0f766e; color:white;">
                    <h6 class="modal-title"><i class="bi bi-receipt me-2"></i>Voucher: {{ $selectedSale->invoice_number }}</h6>
                    <button class="btn-close btn-close-white" wire:click="closeViewModal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-2 mb-3">
                        <div class="col-md-3">
                            <small class="text-muted d-block">Date</small>
                            <strong>{{ $selectedSale->created_at->format('d-M-Y') }}</strong>
                        </div>
                        <div class="col-md-3">
                            <small class="text-muted d-block">Customer</small>
                            <strong>{{ $selectedSale->customer->business_name ?? $selectedSale->customer->name ?? 'N/A' }}</strong>
                        </div>
                        <div class="col-md-3">
                            <small class="text-muted d-block">Billing Type</small>
                            <strong>{{ ucfirst($selectedSale->billing_type ?? 'cash') }}</strong>
                        </div>
                        <div class="col-md-3">
                            <small class="text-muted d-block">Created By</small>
                            <strong>{{ $selectedSale->user->name ?? 'N/A' }}</strong>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-sm table-bordered" style="font-size:12px;">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>Item</th>
                                    <th>Code</th>
                                    <th class="text-center">Qty</th>
                                    <th class="text-end">Rate</th>
                                    <th class="text-end">Discount</th>
                                    <th class="text-end">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($selectedSale->items as $i => $item)
                                <tr>
                                    <td>{{ $i + 1 }}</td>
                                    <td>{{ $item->product_name }}</td>
                                    <td>{{ $item->product_code }}</td>
                                    <td class="text-center">{{ $item->quantity }}</td>
                                    <td class="text-end">{{ number_format($item->unit_price, 2) }}</td>
                                    <td class="text-end">{{ number_format($item->total_discount, 2) }}</td>
                                    <td class="text-end fw-semibold">{{ number_format($item->total, 2) }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="table-light">
                                <tr>
                                    <td colspan="6" class="text-end fw-bold">Subtotal:</td>
                                    <td class="text-end fw-bold">Rs.{{ number_format($selectedSale->subtotal, 2) }}</td>
                                </tr>
                                @if($selectedSale->discount_amount > 0)
                                <tr>
                                    <td colspan="6" class="text-end text-danger">Discount:</td>
                                    <td class="text-end text-danger">- Rs.{{ number_format($selectedSale->discount_amount, 2) }}</td>
                                </tr>
                                @endif
                                <tr class="fw-bold">
                                    <td colspan="6" class="text-end">Grand Total:</td>
                                    <td class="text-end fs-5" style="color:#0f766e;">Rs.{{ number_format($selectedSale->total_amount, 2) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    @if($selectedSale->notes)
                    <div class="alert alert-info py-1 small mt-2">
                        <i class="bi bi-chat-dots me-1"></i><strong>Notes:</strong> {{ $selectedSale->notes }}
                    </div>
                    @endif
                </div>
                <div class="modal-footer py-2">
                    <a href="{{ route('admin.print.sale', $selectedSale->id) }}" target="_blank" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-printer me-1"></i>Print
                    </a>
                    <button class="btn btn-sm btn-outline-primary" wire:click="modifyVoucher({{ $selectedSale->id }})">
                        <i class="bi bi-pencil me-1"></i>Modify
                    </button>
                    <button class="btn btn-sm btn-secondary" wire:click="closeViewModal">Close</button>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- Delete Confirmation Modal --}}
    @if($showDeleteModal)
    <div class="modal fade show d-block" tabindex="-1" style="background:rgba(0,0,0,0.5);">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content">
                <div class="modal-header py-2 bg-danger text-white">
                    <h6 class="modal-title"><i class="bi bi-exclamation-triangle me-2"></i>Confirm Delete</h6>
                    <button class="btn-close btn-close-white" wire:click="closeDeleteModal"></button>
                </div>
                <div class="modal-body text-center py-3">
                    <p class="mb-1">Are you sure you want to delete this voucher?</p>
                    <p class="text-muted small">This will reverse all accounting entries and restore stock.</p>
                </div>
                <div class="modal-footer py-2">
                    <button class="btn btn-sm btn-secondary" wire:click="closeDeleteModal">Cancel</button>
                    <button class="btn btn-sm btn-danger" wire:click="deleteVoucher">
                        <i class="bi bi-trash me-1"></i>Delete
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- Keyboard Shortcut Help --}}
    <div class="mt-2 text-center">
        <small class="text-muted">
            <kbd>Alt+A</kbd> Add Voucher &nbsp;|&nbsp;
            <kbd>Alt+M</kbd> Modify Voucher &nbsp;|&nbsp;
            <kbd>Alt+L</kbd> List Voucher (current)
        </small>
    </div>

    @push('scripts')
    <script>
    function listVoucherKeyboard() {
        return {
            handleGlobalKey(e) {
                if (e.altKey && e.key.toLowerCase() === 'a') {
                    e.preventDefault();
                    window.location.href = "{{ route('admin.sales-voucher-add') }}";
                }
                if (e.altKey && e.key.toLowerCase() === 'm') {
                    e.preventDefault();
                    window.location.href = "{{ route('admin.sales-voucher-modify') }}";
                }
            }
        }
    }
    </script>
    @endpush

    <style>
        kbd { background: #374151; color: #fff; padding: 1px 4px; border-radius: 3px; font-size: 10px; }
    </style>
</div>
