<div class="container-fluid py-3">
    <div class="busy-header-card mb-3">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h5 class="mb-1 fw-bold text-white">
                    <i class="bi bi-arrow-return-left me-2"></i>Purchase Return Voucher
                </h5>
                <small class="text-white-50">Mode: Modify Entry</small>
            </div>
            <div class="d-flex align-items-center gap-2">
                <kbd class="erp-kbd">Alt+M</kbd>
            </div>
        </div>
    </div>

    <div class="busy-tabs mb-4">
        <a href="{{ route('admin.purchase-return-add') }}" class="busy-tab">
            <i class="bi bi-plus-circle-fill"></i> Add
        </a>
        <a href="{{ route('admin.purchase-return-modify') }}" class="busy-tab active">
            <i class="bi bi-pencil-square"></i> Modify
        </a>
        <a href="{{ route('admin.purchase-return-list') }}" class="busy-tab">
            <i class="bi bi-list-ul"></i> List
        </a>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white">
            <h5 class="mb-0 fw-bold"><i class="bi bi-funnel me-2"></i>Filters</h5>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Search</label>
                    <input type="text" class="form-control" wire:model.live="search" placeholder="Return no / invoice / supplier">
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold">Supplier</label>
                    <select class="form-select" wire:model.live="supplierFilter">
                        <option value="">All</option>
                        @foreach($suppliers as $supplier)
                            <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold">From</label>
                    <input type="date" class="form-control" wire:model.live="dateFrom">
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold">To</label>
                    <input type="date" class="form-control" wire:model.live="dateTo">
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold">Rows</label>
                    <select class="form-select" wire:model.live="perPage">
                        <option value="10">10</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                </div>
                <div class="col-md-1 d-flex align-items-end">
                    <button class="btn btn-outline-secondary w-100" wire:click="resetFilters">Reset</button>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold"><i class="bi bi-list-check me-2"></i>Purchase Returns (Modify)</h5>
            <a href="{{ route('admin.purchase-return-add') }}" class="btn btn-sm btn-primary">
                <i class="bi bi-plus-circle me-1"></i>New
            </a>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Return No</th>
                            <th>Date</th>
                            <th>Supplier</th>
                            <th>Invoice</th>
                            <th>Type</th>
                            <th class="text-end">Amount</th>
                            <th>Created By</th>
                            <th class="text-end pe-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($returns as $return)
                            <tr>
                                <td class="ps-3 fw-semibold">{{ $return->return_no }}</td>
                                <td>{{ optional($return->return_date)->format('Y-m-d') }}</td>
                                <td>{{ $return->supplier?->name ?? '-' }}</td>
                                <td>{{ $return->purchaseOrder?->invoice_number ?: ($return->purchaseOrder?->order_code ?? '-') }}</td>
                                <td><span class="badge bg-info">{{ strtoupper(str_replace('_', ' ', $return->return_type)) }}</span></td>
                                <td class="text-end fw-semibold">Rs.{{ number_format($return->grand_total, 2) }}</td>
                                <td>{{ $return->creator?->name ?? '-' }}</td>
                                <td class="text-end pe-3">
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-outline-info" wire:click="viewReturn({{ $return->id }})">View</button>
                                        <button class="btn btn-outline-primary" wire:click="openEdit({{ $return->id }})">Edit</button>
                                        <a class="btn btn-outline-secondary" target="_blank" href="{{ route('admin.purchase-return-print', $return->id) }}">Print</a>
                                        <button class="btn btn-outline-danger" wire:click="deleteReturn({{ $return->id }})"
                                            wire:confirm="Delete this return and reverse stock/accounts?">Delete</button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">No purchase returns found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer bg-white">
            {{ $returns->links('livewire.custom-pagination') }}
        </div>
    </div>

    <div wire:ignore.self class="modal fade" id="viewPurchaseReturnModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold">Purchase Return Details</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    @if($selectedReturn)
                        <div class="row mb-3">
                            <div class="col-md-6"><strong>Return No:</strong> {{ $selectedReturn->return_no }}</div>
                            <div class="col-md-6"><strong>Date:</strong> {{ optional($selectedReturn->return_date)->format('Y-m-d') }}</div>
                            <div class="col-md-6"><strong>Supplier:</strong> {{ $selectedReturn->supplier?->name ?? '-' }}</div>
                            <div class="col-md-6"><strong>Type:</strong> {{ strtoupper(str_replace('_', ' ', $selectedReturn->return_type)) }}</div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered">
                                <thead class="table-light">
                                    <tr>
                                        <th>Product</th>
                                        <th class="text-end">Qty</th>
                                        <th class="text-end">Rate</th>
                                        <th class="text-end">Discount</th>
                                        <th class="text-end">Tax</th>
                                        <th class="text-end">Line Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($selectedReturn->items as $item)
                                        <tr>
                                            <td>{{ $item->product?->name ?? '-' }} @if($item->variant_value) ({{ $item->variant_value }}) @endif</td>
                                            <td class="text-end">{{ number_format($item->return_qty, 3) }}</td>
                                            <td class="text-end">{{ number_format($item->rate, 2) }}</td>
                                            <td class="text-end">{{ number_format($item->discount_amount, 2) }}</td>
                                            <td class="text-end">{{ number_format($item->tax_amount, 2) }}</td>
                                            <td class="text-end fw-semibold">{{ number_format($item->line_total, 2) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="d-flex justify-content-end">
                            <h5 class="mb-0">Grand Total: Rs.{{ number_format($selectedReturn->grand_total, 2) }}</h5>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div wire:ignore.self class="modal fade" id="editPurchaseReturnModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title fw-bold">Modify Purchase Return</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3 mb-3">
                        <div class="col-md-3">
                            <label class="form-label">Return Date</label>
                            <input type="date" class="form-control" wire:model="editReturnDate">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Return Type</label>
                            <select class="form-select" wire:model="editReturnType">
                                <option value="cash_refund">Cash Refund</option>
                                <option value="debit_note">Debit Note</option>
                                <option value="replacement">Replacement</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Notes</label>
                            <input type="text" class="form-control" wire:model="editNotes">
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Product</th>
                                    <th class="text-end">Balance</th>
                                    <th>Qty</th>
                                    <th>Rate</th>
                                    <th>Discount</th>
                                    <th>Tax</th>
                                    <th class="text-end">Line Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($editItems as $i => $item)
                                    <tr>
                                        <td>{{ $item['product_name'] }}</td>
                                        <td class="text-end">{{ number_format($item['balance_returnable_qty'], 3) }}</td>
                                        <td><input type="number" step="0.001" min="0" class="form-control form-control-sm" wire:model.live="editItems.{{ $i }}.return_qty"></td>
                                        <td><input type="number" step="0.01" min="0" class="form-control form-control-sm" wire:model.live="editItems.{{ $i }}.rate"></td>
                                        <td><input type="number" step="0.01" min="0" class="form-control form-control-sm" wire:model.live="editItems.{{ $i }}.discount"></td>
                                        <td><input type="number" step="0.01" min="0" class="form-control form-control-sm" wire:model.live="editItems.{{ $i }}.tax"></td>
                                        <td class="text-end">{{ number_format($item['line_total'] ?? 0, 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="text-end mt-2">
                        <strong>Grand Total: Rs.{{ number_format($this->grandTotalEdit, 2) }}</strong>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                    <button class="btn btn-primary" wire:click="updateReturn">Update Return</button>
                </div>
            </div>
        </div>
    </div>

    @push('styles')
        <style>
            .busy-header-card {
                background: linear-gradient(90deg, #1f2937 0%, #374151 100%);
                border-radius: 8px;
                padding: 12px 16px;
            }
            .busy-tabs {
                display: flex;
                gap: 8px;
                flex-wrap: wrap;
            }
            .busy-tab {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                padding: 6px 12px;
                border: 1px solid #cbd5e1;
                border-radius: 6px;
                background: #f8fafc;
                color: #111827;
                text-decoration: none;
                font-weight: 600;
                font-size: 0.85rem;
            }
            .busy-tab.active {
                background: #0f172a;
                border-color: #0f172a;
                color: #fff;
            }
            .erp-kbd {
                background: #111827;
                color: #fff;
                border: 1px solid #374151;
                border-radius: 4px;
                padding: 2px 6px;
                font-size: 0.72rem;
            }
        </style>
    @endpush

    @push('scripts')
        <script>
            document.addEventListener('livewire:init', function () {
                Livewire.on('showModal', function (modalId) {
                    const id = Array.isArray(modalId) ? modalId[0] : modalId;
                    const el = document.getElementById(id);
                    if (el) {
                        bootstrap.Modal.getOrCreateInstance(el).show();
                    }
                });

                Livewire.on('hideModal', function (modalId) {
                    const id = Array.isArray(modalId) ? modalId[0] : modalId;
                    const el = document.getElementById(id);
                    if (el) {
                        bootstrap.Modal.getOrCreateInstance(el).hide();
                    }
                });
            });
        </script>
    @endpush
</div>
