<div class="container-fluid py-3" x-data @keydown.window.alt.s.prevent="$wire.save()" @keydown.window.alt.p.prevent="$wire.saveAndPrint()" @keydown.window.escape.prevent="$wire.cancel()">
    <div class="busy-header-card mb-3">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h5 class="mb-1 fw-bold text-white"><i class="bi bi-arrow-return-left me-2"></i>Sales Return Voucher</h5>
                <small class="text-white-50">Busy / Tally Style Entry</small>
            </div>
            <div class="d-flex align-items-center gap-2">
                <kbd class="erp-kbd">Alt+S Save</kbd>
                <kbd class="erp-kbd">Alt+P Save & Print</kbd>
                <kbd class="erp-kbd">Esc Cancel</kbd>
            </div>
        </div>
    </div>

    <div class="busy-tabs mb-4">
        <a href="{{ route('admin.sales-return-add') }}" class="busy-tab active"><i class="bi bi-plus-circle-fill"></i> Add</a>
        <a href="{{ route('admin.sales-return-modify') }}" class="busy-tab"><i class="bi bi-pencil-square"></i> Modify</a>
        <a href="{{ route('admin.sales-return-list') }}" class="busy-tab"><i class="bi bi-list-ul"></i> List</a>
    </div>

    <div class="card mb-3">
        <div class="card-header py-2"><strong>Header Details</strong></div>
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-2">
                    <label class="form-label fw-semibold">Return No</label>
                    <input type="text" class="form-control" wire:model="returnNo" readonly>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold">Date</label>
                    <input type="date" class="form-control" wire:model="returnDate">
                    @error('returnDate') <small class="text-danger">{{ $message }}</small> @enderror
                </div>
                <div class="col-md-4 position-relative">
                    <label class="form-label fw-semibold">Customer</label>
                    <div class="input-group">
                        <input type="text" class="form-control" wire:model.live.debounce.300ms="customerSearch" placeholder="Search customer name / phone">
                        <a class="btn btn-outline-primary" href="{{ route('admin.manage-customer') }}" target="_blank">Add New</a>
                    </div>
                    @if(!empty($customerResults))
                        <div class="list-group position-absolute w-100 shadow-sm" style="z-index: 10; max-height: 220px; overflow:auto;">
                            @foreach($customerResults as $customer)
                                <button type="button" class="list-group-item list-group-item-action" wire:click="selectCustomer({{ $customer['id'] }})">
                                    {{ $customer['name'] }} <small class="text-muted">{{ $customer['phone'] ?? '-' }}</small>
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>
                <div class="col-md-4 position-relative">
                    <label class="form-label fw-semibold">Reference Invoice No <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" wire:model.live.debounce.300ms="invoiceSearch" placeholder="Search and select invoice" autocomplete="off">
                    @error('selectedInvoiceId') <small class="text-danger">{{ $message }}</small> @enderror
                    @if(!empty($invoiceResults))
                        <div class="list-group position-absolute w-100 shadow-sm" style="z-index: 10; max-height: 240px; overflow:auto;">
                            @foreach($invoiceResults as $invoice)
                                <button type="button" class="list-group-item list-group-item-action" wire:click="selectInvoice({{ $invoice['id'] }})">
                                    <div class="d-flex justify-content-between">
                                        <span>#{{ $invoice['invoice_number'] }} - {{ $invoice['customer_name'] }}</span>
                                        <span class="text-muted">Rs.{{ number_format($invoice['total'], 2) }}</span>
                                    </div>
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @if(!empty($saleItems))
    <div class="card mb-3">
        <div class="card-header py-2 d-flex justify-content-between align-items-center">
            <strong>Products (Invoice-linked)</strong>
            <span class="badge bg-dark">{{ count($saleItems) }} lines</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-bordered mb-0 align-middle busy-table">
                    <thead>
                        <tr>
                            <th>Product Name</th>
                            <th class="text-end">Sold Qty</th>
                            <th class="text-end">Already Returned</th>
                            <th class="text-end">Balance Returnable</th>
                            <th class="text-end">Return Qty</th>
                            <th class="text-end">Rate</th>
                            <th class="text-end">Discount</th>
                            <th class="text-end">Tax</th>
                            <th class="text-end">Line Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($saleItems as $i => $line)
                        <tr>
                            <td>
                                {{ $line['product_name'] }}
                                <input type="hidden" wire:model="saleItems.{{ $i }}.sale_item_id">
                            </td>
                            <td class="text-end">{{ number_format((float) $line['sold_qty']) }}</td>
                            <td class="text-end">{{ number_format((float) $line['already_returned_qty']) }}</td>
                            <td class="text-end text-success">{{ number_format((float) $line['balance_returnable_qty']) }}</td>
                            <td style="min-width:120px;">
                                <input type="number" step="0.001" min="0" max="{{ $line['balance_returnable_qty'] }}" class="form-control form-control-sm text-end" wire:model.live="saleItems.{{ $i }}.return_qty">
                            </td>
                            <td style="min-width:120px;"><input type="number" step="0.01" min="0" class="form-control form-control-sm text-end" wire:model.live="saleItems.{{ $i }}.rate"></td>
                            <td style="min-width:120px;"><input type="number" step="0.01" min="0" class="form-control form-control-sm text-end" wire:model.live="saleItems.{{ $i }}.discount"></td>
                            <td style="min-width:120px;"><input type="number" step="0.01" min="0" class="form-control form-control-sm text-end" wire:model.live="saleItems.{{ $i }}.tax"></td>
                            <td class="text-end fw-semibold">Rs.{{ number_format((float) $line['line_total'], 2) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @error('saleItems') <div class="p-2 text-danger small">{{ $message }}</div> @enderror
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-8">
            <div class="card h-100">
                <div class="card-header py-2"><strong>Previous Return History (Selected Invoice)</strong></div>
                <div class="card-body p-0">
                    <div class="table-responsive" style="max-height: 240px;">
                        <table class="table table-sm mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Return No</th>
                                    <th>Date</th>
                                    <th>Refund Type</th>
                                    <th class="text-end">Qty</th>
                                    <th class="text-end">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($invoiceHistory as $history)
                                <tr>
                                    <td>{{ $history['return_no'] }}</td>
                                    <td>{{ $history['date'] }}</td>
                                    <td>{{ strtoupper(str_replace('_', ' ', $history['refund_type'])) }}</td>
                                    <td class="text-end">{{ number_format((float) $history['items'], 3) }}</td>
                                    <td class="text-end">Rs.{{ number_format((float) $history['total'], 2) }}</td>
                                </tr>
                                @empty
                                <tr><td colspan="5" class="text-center text-muted py-3">No previous returns for this invoice.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header py-2"><strong>Totals & Refund</strong></div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-1"><span>Subtotal</span><strong>Rs.{{ number_format($subtotal, 2) }}</strong></div>
                    <div class="mb-2">
                        <label class="form-label small mb-1">Overall Discount</label>
                        <input type="number" class="form-control form-control-sm text-end" min="0" step="0.01" wire:model.live="overallDiscount">
                    </div>
                    <div class="d-flex justify-content-between mb-1"><span>Tax Total</span><strong>Rs.{{ number_format($taxTotal, 2) }}</strong></div>
                    <div class="d-flex justify-content-between pt-2 border-top mb-3"><span class="fw-semibold">Grand Total</span><strong class="text-success">Rs.{{ number_format($grandTotal, 2) }}</strong></div>

                    <label class="form-label small mb-1">Refund Type</label>
                    <select class="form-select form-select-sm mb-2" wire:model.live="refundType">
                        <option value="cash">Cash Refund</option>
                        <option value="credit_note">Adjust to Customer Account (Credit Note)</option>
                        <option value="replacement">Replacement (Stock only)</option>
                    </select>

                    @if($refundType === 'cash')
                    <label class="form-label small mb-1">Cash Refund Amount</label>
                    <input type="number" class="form-control form-control-sm text-end" min="0" step="0.01" wire:model.live="cashRefundAmount">
                    @error('cashRefundAmount') <small class="text-danger">{{ $message }}</small> @enderror
                    @endif

                    <label class="form-label small mt-2 mb-1">Notes</label>
                    <textarea class="form-control form-control-sm" rows="3" wire:model="notes" placeholder="Optional notes"></textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-end gap-2 mb-4">
        <button class="btn btn-success" wire:click="save"><i class="bi bi-check2-circle me-1"></i> Save</button>
        <button class="btn btn-primary" wire:click="saveAndPrint"><i class="bi bi-printer me-1"></i> Save & Print</button>
        <button class="btn btn-outline-secondary" wire:click="cancel"><i class="bi bi-x-circle me-1"></i> Cancel</button>
    </div>
    @endif

    <div class="toast-container position-fixed top-0 end-0 p-3">
        <div id="livewire-toast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="toast-header">
                <strong class="me-auto">Notification</strong>
                <small>just now</small>
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
    .busy-tab { display:inline-flex; align-items:center; gap:6px; padding:6px 12px; border:1px solid #cbd5e1; border-radius:6px; background:#f8fafc; color:#111827; text-decoration:none; font-weight:600; font-size:.85rem; }
    .busy-tab.active { background:#0f172a; border-color:#0f172a; color:#fff; }
    .erp-kbd { background:#111827; color:#fff; border:1px solid #374151; border-radius:4px; padding:2px 6px; font-size:.72rem; }
    .busy-table thead th { background: #eef2f7; white-space: nowrap; font-size: .78rem; text-transform: uppercase; }
    .busy-table td { font-size: .86rem; }
</style>
@endpush

@push('scripts')
<script>
    document.addEventListener('livewire:initialized', () => {
        Livewire.on('showToast', (e) => {
            const toast = document.getElementById('livewire-toast');
            if (!toast) return;
            toast.querySelector('.toast-body').textContent = e.message;
            toast.querySelector('.toast-header').className = 'toast-header text-white bg-' + e.type;
            new bootstrap.Toast(toast).show();
        });
    });
</script>
@endpush
