@php
    $returnsData = $returns ?? collect();
@endphp

<div class="container-fluid py-3">
    <div class="busy-header-card mb-3">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h5 class="mb-1 fw-bold text-white">
                    <i class="bi bi-arrow-return-left me-2"></i>Sales Return Voucher
                </h5>
                <small class="text-white-50">Mode: Modify Entry</small>
            </div>
            <kbd class="erp-kbd">Alt+M</kbd>
        </div>
    </div>

    <div class="busy-tabs mb-4">
        <a href="{{ route('admin.sales-return-add') }}" class="busy-tab">
            <i class="bi bi-plus-circle-fill"></i> Add
        </a>
        <a href="{{ route('admin.sales-return-modify') }}" class="busy-tab active">
            <i class="bi bi-pencil-square"></i> Modify
        </a>
        <a href="{{ route('admin.sales-return-list') }}" class="busy-tab">
            <i class="bi bi-list-ul"></i> List
        </a>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center gap-2 flex-wrap">
            <div>
                <h5 class="fw-bold mb-0"><i class="bi bi-list-ul text-primary me-2"></i>Sales Return List</h5>
                <span class="badge bg-primary">{{ method_exists($returnsData, 'total') ? $returnsData->total() : count($returnsData) }} records</span>
            </div>

            <div class="d-flex align-items-center gap-2" style="min-width: 340px;">
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0">
                        <i class="bi bi-search text-muted"></i>
                    </span>
                    <input type="text" class="form-control border-start-0" wire:model.live="returnSearch"
                        placeholder="Search by return no / invoice / customer">
                </div>
                <select wire:model.live="perPage" class="form-select form-select-sm" style="width: 90px;">
                    <option value="10">10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
            </div>
        </div>

        <div class="card-body p-0 overflow-auto">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">#</th>
                            <th>Return No</th>
                            <th>Invoice</th>
                            <th>Customer</th>
                            <th class="text-center">Items</th>
                            <th class="text-end">Total</th>
                            <th>Date</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($returnsData as $index => $return)
                        <tr wire:key="return-{{ $return->id }}">
                            <td class="ps-4">{{ $index + 1 }}</td>
                            <td class="fw-semibold">{{ $return->return_no }}</td>
                            <td>{{ $return->sale?->invoice_number ?? '-' }}</td>
                            <td>{{ $return->customer?->name ?? ($return->sale?->customer?->name ?? 'Walk-in') }}</td>
                            <td class="text-center">{{ $return->items->count() }}</td>
                            <td class="text-end">Rs.{{ number_format((float) $return->grand_total, 2) }}</td>
                            <td>{{ optional($return->return_date)->format('M d, Y') }}</td>
                            <td class="text-end pe-4">
                                <div class="btn-group btn-group-sm">
                                    <button class="btn btn-outline-info" wire:click="showReceipt({{ $return->id }})">View</button>
                                    <button class="btn btn-outline-primary" wire:click="editReturn({{ $return->id }})">Modify</button>
                                    <button class="btn btn-outline-danger" wire:click="deleteReturn({{ $return->id }})">Delete</button>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">
                                <i class="bi bi-arrow-return-left display-4 d-block mb-2"></i>
                                No returns found.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if (method_exists($returnsData, 'hasPages') && $returnsData->hasPages())
            <div class="card-footer bg-light">
                <div class="d-flex justify-content-center">
                    {{ $returnsData->links('livewire.custom-pagination') }}
                </div>
            </div>
            @endif
        </div>
    </div>

    <div wire:ignore.self class="modal fade" id="receiptModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-xl">
            <div class="modal-content" id="printableReturnReceipt">
                <div class="modal-header text-center border-0" style="background: linear-gradient(135deg, #2a83df 0%, #1a5fb8 100%); color: #fff;">
                    <div class="w-100">
                        <img src="{{ asset('images/RNZ.png') }}" alt="Logo" class="img-fluid mb-2" style="max-height:60px;">
                        <h4 class="mb-0 fw-bold">RNZ AUTO PARTS</h4>
                    </div>
                    <button type="button" class="btn-close btn-close-white closebtn" wire:click="closeModal"></button>
                </div>

                @if($selectedReturn)
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div><strong>Return No:</strong> {{ $selectedReturn->return_no }}</div>
                            <div><strong>Invoice:</strong> {{ $selectedReturn->sale?->invoice_number ?? '-' }}</div>
                            <div><strong>Date:</strong> {{ optional($selectedReturn->return_date)->format('d/m/Y') }}</div>
                        </div>
                        <div class="col-md-6">
                            <div><strong>Customer:</strong> {{ $selectedReturn->customer?->name ?? ($selectedReturn->sale?->customer?->name ?? 'Walk-in Customer') }}</div>
                            <div><strong>Refund Type:</strong> {{ strtoupper(str_replace('_', ' ', $selectedReturn->refund_type)) }}</div>
                            <div><strong>Status:</strong> Completed</div>
                        </div>
                    </div>

                    <div class="table-responsive mb-3">
                        <table class="table table-bordered table-sm">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>Item</th>
                                    <th class="text-center">Qty</th>
                                    <th class="text-end">Rate</th>
                                    <th class="text-end">Discount</th>
                                    <th class="text-end">Tax</th>
                                    <th class="text-end">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($selectedReturn->items as $idx => $item)
                                <tr>
                                    <td>{{ $idx + 1 }}</td>
                                    <td>{{ $item->product?->name ?? '-' }}{{ $item->variant_value ? ' (' . $item->variant_value . ')' : '' }}</td>
                                    <td class="text-center">{{ number_format((float) $item->return_qty, 3) }}</td>
                                    <td class="text-end">Rs.{{ number_format((float) $item->rate, 2) }}</td>
                                    <td class="text-end">Rs.{{ number_format((float) $item->discount_amount, 2) }}</td>
                                    <td class="text-end">Rs.{{ number_format((float) $item->tax_amount, 2) }}</td>
                                    <td class="text-end">Rs.{{ number_format((float) $item->line_total, 2) }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="row">
                        <div class="col-md-7"></div>
                        <div class="col-md-5">
                            <table class="table table-sm table-borderless">
                                <tr><td class="text-end"><strong>Subtotal</strong></td><td class="text-end">Rs.{{ number_format((float) $selectedReturn->subtotal, 2) }}</td></tr>
                                <tr><td class="text-end"><strong>Discount</strong></td><td class="text-end">Rs.{{ number_format((float) $selectedReturn->overall_discount, 2) }}</td></tr>
                                <tr><td class="text-end"><strong>Tax</strong></td><td class="text-end">Rs.{{ number_format((float) $selectedReturn->tax_total, 2) }}</td></tr>
                                <tr><td class="text-end"><strong>Grand Total</strong></td><td class="text-end">Rs.{{ number_format((float) $selectedReturn->grand_total, 2) }}</td></tr>
                            </table>
                        </div>
                    </div>
                </div>
                @endif

                <div class="modal-footer bg-light justify-content-between">
                    <button type="button" class="btn btn-secondary" wire:click="closeModal">Close</button>
                    <button type="button" class="btn btn-primary" onclick="printReturnReceipt()">
                        <i class="bi bi-printer me-1"></i> Print
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div wire:ignore.self class="modal fade" id="editReturnModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2"></i>Modify Sales Return (Full Invoice)</h5>
                    <button type="button" class="btn-close btn-close-white" wire:click="closeModal"></button>
                </div>

                <div class="modal-body">
                    <div class="row g-2 mb-3">
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Return Date</label>
                            <input type="date" class="form-control" wire:model="editReturnDate">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Refund Type</label>
                            <select class="form-select" wire:model.live="editRefundType">
                                <option value="cash">Cash Refund</option>
                                <option value="credit_note">Credit Note</option>
                                <option value="replacement">Replacement</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Overall Discount</label>
                            <input type="number" class="form-control" step="0.01" min="0" wire:model.live="editOverallDiscount">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Cash Refund Amount</label>
                            <input type="number" class="form-control" step="0.01" min="0" wire:model.live="editCashRefundAmount" {{ $editRefundType !== 'cash' ? 'disabled' : '' }}>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Item</th>
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
                                    <td><input type="number" step="0.001" min="0.001" class="form-control form-control-sm" wire:model.live="editItems.{{ $i }}.return_qty"></td>
                                    <td><input type="number" step="0.01" min="0" class="form-control form-control-sm" wire:model.live="editItems.{{ $i }}.rate"></td>
                                    <td><input type="number" step="0.01" min="0" class="form-control form-control-sm" wire:model.live="editItems.{{ $i }}.discount_amount"></td>
                                    <td><input type="number" step="0.01" min="0" class="form-control form-control-sm" wire:model.live="editItems.{{ $i }}.tax_amount"></td>
                                    <td class="text-end">Rs.{{ number_format((float) ($item['line_total'] ?? 0), 2) }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="row mt-2">
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Notes</label>
                            <textarea class="form-control" rows="2" wire:model="editReturnNotes" placeholder="Optional notes"></textarea>
                        </div>
                        <div class="col-md-4">
                            <div class="small text-muted">Subtotal: <strong>Rs.{{ number_format((float) $editSubtotal, 2) }}</strong></div>
                            <div class="small text-muted">Tax: <strong>Rs.{{ number_format((float) $editTaxTotal, 2) }}</strong></div>
                            <div class="small text-muted">Grand Total: <strong>Rs.{{ number_format((float) $editGrandTotal, 2) }}</strong></div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" wire:click="closeModal">Cancel</button>
                    <button type="button" class="btn btn-primary" wire:click="updateReturn">
                        <i class="bi bi-check2-circle me-1"></i>Update Return
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div wire:ignore.self class="modal fade" id="deleteReturnModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title fw-bold"><i class="bi bi-exclamation-triangle me-2"></i>Confirm Deletion</h5>
                    <button type="button" class="btn-close btn-close-white" wire:click="closeModal"></button>
                </div>
                <div class="modal-body">
                    @if($selectedReturn)
                    <p class="mb-2">Delete this sales return voucher?</p>
                    <ul class="mb-0">
                        <li><strong>Return No:</strong> {{ $selectedReturn->return_no }}</li>
                        <li><strong>Invoice:</strong> {{ $selectedReturn->sale?->invoice_number ?? '-' }}</li>
                        <li><strong>Total:</strong> Rs.{{ number_format((float) $selectedReturn->grand_total, 2) }}</li>
                    </ul>
                    @endif
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" wire:click="closeModal">Cancel</button>
                    <button type="button" class="btn btn-danger" wire:click="confirmDeleteReturn">Delete Return</button>
                </div>
            </div>
        </div>
    </div>

    <div class="toast-container position-fixed top-0 end-0 p-3">
        <div id="livewire-toast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="toast-header">
                <strong class="me-auto">Notification</strong>
                <small>Just now</small>
                <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
            <div class="toast-body"></div>
        </div>
    </div>
</div>

@push('styles')
<style>
    .busy-header-card { background: linear-gradient(90deg, #1f2937 0%, #374151 100%); border-radius: 8px; padding: 12px 16px; }
    .busy-tabs { display: flex; gap: 8px; flex-wrap: wrap; }
    .busy-tab { display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; border: 1px solid #cbd5e1; border-radius: 6px; background: #f8fafc; color: #111827; text-decoration: none; font-weight: 600; font-size: 0.85rem; }
    .busy-tab.active { background: #0f172a; border-color: #0f172a; color: #fff; }
    .erp-kbd { background: #111827; color: #fff; border: 1px solid #374151; border-radius: 4px; padding: 2px 6px; font-size: 0.72rem; }
    .closebtn { top: 3%; right: 3%; position: absolute; }
</style>
@endpush

@push('scripts')
<script>
    document.addEventListener('livewire:initialized', () => {
        Livewire.on('showModal', (modalId) => {
            const id = Array.isArray(modalId) ? modalId[0] : modalId;
            const el = document.getElementById(id);
            if (el) bootstrap.Modal.getOrCreateInstance(el).show();
        });

        Livewire.on('hideModal', (modalId) => {
            const id = Array.isArray(modalId) ? modalId[0] : modalId;
            const el = document.getElementById(id);
            if (!el) return;
            const modal = bootstrap.Modal.getOrCreateInstance(el);
            modal.hide();
        });

        Livewire.on('showToast', (e) => {
            const payload = Array.isArray(e) ? e[0] : e;
            const toast = document.getElementById('livewire-toast');
            if (!toast || !payload) return;
            toast.querySelector('.toast-body').textContent = payload.message || '';
            toast.querySelector('.toast-header').className = 'toast-header text-white bg-' + (payload.type || 'info');
            bootstrap.Toast.getOrCreateInstance(toast).show();
        });

        Livewire.on('printReceipt', () => {
            printReturnReceipt();
        });
    });

    function printReturnReceipt() {
        window.print();
    }
</script>
@endpush
