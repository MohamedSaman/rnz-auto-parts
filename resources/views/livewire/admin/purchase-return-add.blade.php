<div class="container-fluid py-3">
    <div class="busy-header-card mb-3">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h5 class="mb-1 fw-bold text-white">
                    <i class="bi bi-arrow-return-left me-2"></i>Purchase Return Voucher
                </h5>
                <small class="text-white-50">Mode: Add Entry</small>
            </div>
            <div class="d-flex align-items-center gap-2">
                <kbd class="erp-kbd">Alt+A</kbd>
                <kbd class="erp-kbd">Alt+P</kbd>
            </div>
        </div>
    </div>

    <div class="busy-tabs mb-4">
        <a href="{{ route('admin.purchase-return-add') }}" class="busy-tab active">
            <i class="bi bi-plus-circle-fill"></i> Add
        </a>
        <a href="{{ route('admin.purchase-return-modify') }}" class="busy-tab">
            <i class="bi bi-pencil-square"></i> Modify
        </a>
        <a href="{{ route('admin.purchase-return-list') }}" class="busy-tab">
            <i class="bi bi-list-ul"></i> List
        </a>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white">
            <h5 class="mb-0 fw-bold"><i class="bi bi-journal-plus me-2"></i>Header Details</h5>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Return No</label>
                    <input type="text" class="form-control" wire:model="returnNo" readonly>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Date</label>
                    <input type="date" class="form-control" wire:model="returnDate">
                    @error('returnDate') <small class="text-danger">{{ $message }}</small> @enderror
                </div>
                <div class="col-md-3 position-relative">
                    <label class="form-label fw-semibold">Supplier</label>
                    <input type="text" class="form-control" wire:model.live="supplierSearch" placeholder="Type supplier name or phone">
                    @if(!empty($supplierResults))
                        <div class="list-group position-absolute w-100" style="z-index: 1020; max-height: 220px; overflow: auto;">
                            @foreach($supplierResults as $supplier)
                                <button type="button" class="list-group-item list-group-item-action"
                                    wire:click="selectSupplier({{ $supplier['id'] }})">
                                    <span class="fw-semibold">{{ $supplier['name'] }}</span>
                                    <small class="text-muted ms-2">{{ $supplier['phone'] ?? '-' }}</small>
                                </button>
                            @endforeach
                        </div>
                    @endif
                    @error('selectedSupplierId') <small class="text-danger">{{ $message }}</small> @enderror
                </div>
                <div class="col-md-3 position-relative">
                    <label class="form-label fw-semibold">Purchase Invoice</label>
                    <input type="text" class="form-control" wire:model.live="invoiceSearch" placeholder="Type invoice/order no">
                    @if(!empty($invoiceResults))
                        <div class="list-group position-absolute w-100" style="z-index: 1020; max-height: 250px; overflow: auto;">
                            @foreach($invoiceResults as $invoice)
                                <button type="button" class="list-group-item list-group-item-action"
                                    wire:click="selectPurchaseInvoice({{ $invoice['id'] }})">
                                    <div class="fw-semibold">{{ $invoice['invoice_number'] ?: $invoice['order_code'] }}</div>
                                    <small class="text-muted">{{ $invoice['supplier_name'] }} | {{ $invoice['date'] }} | Rs.{{ number_format($invoice['total'], 2) }}</small>
                                </button>
                            @endforeach
                        </div>
                    @endif
                    @error('selectedPurchaseId') <small class="text-danger">{{ $message }}</small> @enderror
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold"><i class="bi bi-box-seam me-2"></i>Invoice Items</h5>
            <span class="badge bg-primary">{{ count($purchaseItems) }} line(s)</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Product</th>
                            <th class="text-end">Purchased</th>
                            <th class="text-end">Returned</th>
                            <th class="text-end">Balance</th>
                            <th style="min-width:120px">Return Qty</th>
                            <th style="min-width:120px">Rate</th>
                            <th style="min-width:120px">Discount</th>
                            <th style="min-width:120px">Tax</th>
                            <th class="text-end pe-3">Line Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($purchaseItems as $i => $item)
                            <tr>
                                <td class="ps-3">{{ $item['product_name'] }}</td>
                                <td class="text-end">{{ rtrim(rtrim(number_format($item['purchased_qty'], 3, '.', ''), '0'), '.') }}</td>
                                <td class="text-end">{{ rtrim(rtrim(number_format($item['already_returned_qty'], 3, '.', ''), '0'), '.') }}</td>
                                <td class="text-end fw-semibold">{{ rtrim(rtrim(number_format($item['balance_returnable_qty'], 3, '.', ''), '0'), '.') }}</td>
                                <td><input type="number" step="0.001" min="0" class="form-control form-control-sm" wire:model.live="purchaseItems.{{ $i }}.return_qty"></td>
                                <td><input type="number" step="0.01" min="0" class="form-control form-control-sm" wire:model.live="purchaseItems.{{ $i }}.rate"></td>
                                <td><input type="number" step="0.01" min="0" class="form-control form-control-sm" wire:model.live="purchaseItems.{{ $i }}.discount"></td>
                                <td><input type="number" step="0.01" min="0" class="form-control form-control-sm" wire:model.live="purchaseItems.{{ $i }}.tax"></td>
                                <td class="text-end pe-3 fw-semibold">{{ number_format($item['line_total'] ?? 0, 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center text-muted py-4">
                                    Select supplier and invoice to load returnable items.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @error('purchaseItems') <div class="text-danger small px-3 py-2">{{ $message }}</div> @enderror
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white">
                    <h6 class="mb-0 fw-bold"><i class="bi bi-clock-history me-2"></i>Return History (This Invoice)</h6>
                </div>
                <div class="card-body">
                    @if(empty($invoiceHistory))
                        <p class="text-muted mb-0">No prior return history for selected invoice.</p>
                    @else
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead>
                                    <tr>
                                        <th>Return No</th>
                                        <th>Date</th>
                                        <th>Type</th>
                                        <th class="text-end">Items</th>
                                        <th class="text-end">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($invoiceHistory as $history)
                                        <tr>
                                            <td>{{ $history['return_no'] }}</td>
                                            <td>{{ $history['date'] }}</td>
                                            <td>{{ strtoupper(str_replace('_', ' ', $history['return_type'])) }}</td>
                                            <td class="text-end">{{ number_format($history['items'], 3) }}</td>
                                            <td class="text-end">Rs.{{ number_format($history['total'], 2) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white">
                    <h6 class="mb-0 fw-bold"><i class="bi bi-calculator me-2"></i>Summary</h6>
                </div>
                <div class="card-body">
                    <div class="mb-2 d-flex justify-content-between"><span>Subtotal</span><strong>Rs.{{ number_format($subtotal, 2) }}</strong></div>
                    <div class="mb-2 d-flex justify-content-between"><span>Tax</span><strong>Rs.{{ number_format($taxTotal, 2) }}</strong></div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold mb-1">Overall Discount</label>
                        <input type="number" step="0.01" min="0" class="form-control" wire:model.live="overallDiscount">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold mb-1">Return Type</label>
                        <select class="form-select" wire:model="returnType">
                            <option value="cash_refund">Cash Refund</option>
                            <option value="debit_note">Debit Note</option>
                            <option value="replacement">Replacement</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold mb-1">Notes</label>
                        <textarea class="form-control" rows="3" wire:model="notes" placeholder="Optional notes"></textarea>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="fw-bold fs-5">Grand Total</span>
                        <span class="fw-bold fs-4 text-success">Rs.{{ number_format($grandTotal, 2) }}</span>
                    </div>
                </div>
                <div class="card-footer bg-white d-flex justify-content-end gap-2">
                    <button class="btn btn-outline-secondary" wire:click="cancel">
                        <i class="bi bi-x-circle me-1"></i>Cancel
                    </button>
                    <button class="btn btn-primary" wire:click="save">
                        <i class="bi bi-save me-1"></i>Save
                    </button>
                    <button class="btn btn-success" wire:click="saveAndPrint">
                        <i class="bi bi-printer me-1"></i>Save & Print
                    </button>
                </div>
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
