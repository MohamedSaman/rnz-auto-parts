<div x-data="modifyVoucherKeyboard()" x-init="initKeyboard()" @keydown.window="handleGlobalKey($event)">
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
    <div class="card shadow-sm mb-2 border-0" style="background: linear-gradient(135deg, #7c3aed 0%, #6d28d9 100%);">
        <div class="card-body py-2 px-3">
            <div class="row align-items-center">
                <div class="col-auto">
                    <h5 class="text-white mb-0 fw-bold">
                        <i class="bi bi-pencil-square me-2"></i>Sales Voucher — Modify
                    </h5>
                </div>
                <div class="col text-end">
                    @if($isLoaded)
                    <span class="badge bg-warning text-dark fs-6 px-3 py-2">
                        <i class="bi bi-hash"></i> {{ $voucherNumber }}
                    </span>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @if(!$isLoaded)
    {{-- Search Section --}}
    <div class="card shadow-sm mb-3 border">
        <div class="card-header py-2" style="background:#f8fafc;">
            <h6 class="mb-0 fw-semibold"><i class="bi bi-search me-2"></i>Search Voucher</h6>
        </div>
        <div class="card-body py-3">
            <div class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Search By</label>
                    <select class="form-select form-select-sm" wire:model.live="searchType">
                        <option value="voucher_number">Voucher / Invoice Number</option>
                        <option value="date">Date</option>
                        <option value="customer">Customer Name / Phone</option>
                    </select>
                </div>
                <div class="col-md-5">
                    <label class="form-label small fw-semibold">Search</label>
                    @if($searchType === 'date')
                    <input type="date" class="form-control form-control-sm" wire:model.live.debounce.300ms="searchQuery">
                    @else
                    <input type="text" class="form-control form-control-sm"
                        wire:model.live.debounce.300ms="searchQuery"
                        placeholder="Type to search..."
                        id="voucherSearchInput"
                        autofocus>
                    @endif
                </div>
                <div class="col-md-2">
                    <button class="btn btn-sm btn-primary w-100" wire:click="searchVouchers">
                        <i class="bi bi-search me-1"></i>Search
                    </button>
                </div>
            </div>

            {{-- Search Results --}}
            @if($showSearchResults && count($searchResults) > 0)
            <div class="mt-3">
                <div class="table-responsive">
                    <table class="table table-sm table-hover table-bordered mb-0" style="font-size:12px;">
                        <thead class="table-light">
                            <tr>
                                <th>Invoice #</th>
                                <th>Date</th>
                                <th>Customer</th>
                                <th class="text-center">Items</th>
                                <th class="text-end">Total</th>
                                <th>Status</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($searchResults as $result)
                            <tr>
                                <td class="fw-semibold">{{ $result['invoice_number'] }}</td>
                                <td>{{ \Carbon\Carbon::parse($result['created_at'])->format('d-M-Y') }}</td>
                                <td>{{ $result['customer']['name'] ?? 'N/A' }}</td>
                                <td class="text-center">{{ count($result['items'] ?? []) }}</td>
                                <td class="text-end">Rs.{{ number_format($result['total_amount'], 2) }}</td>
                                <td>
                                    <span class="badge bg-{{ $result['status'] === 'confirm' ? 'success' : ($result['status'] === 'pending' ? 'warning' : 'secondary') }}">
                                        {{ ucfirst($result['status']) }}
                                    </span>
                                </td>
                                <td class="text-center">
                                    <button class="btn btn-sm btn-outline-primary" wire:click="loadVoucher({{ $result['id'] }})">
                                        <i class="bi bi-pencil me-1"></i>Load
                                    </button>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @elseif($showSearchResults && count($searchResults) === 0)
            <div class="alert alert-info mt-3 py-2 small">
                <i class="bi bi-info-circle me-1"></i>No vouchers found matching your search.
            </div>
            @endif
        </div>
    </div>

    <div class="card shadow-sm mb-3 border">
        <div class="card-header py-2 d-flex justify-content-between align-items-center" style="background:#f8fafc;">
            <h6 class="mb-0 fw-semibold"><i class="bi bi-table me-2"></i>Sales Voucher List</h6>
            <small class="text-muted">Latest 25 records</small>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover table-bordered mb-0" style="font-size:12px;">
                    <thead class="table-light">
                        <tr>
                            <th>Invoice #</th>
                            <th>Date</th>
                            <th>Customer</th>
                            <th class="text-center">Items</th>
                            <th class="text-end">Total</th>
                            <th>Status</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentVouchers as $voucher)
                        <tr>
                            <td class="fw-semibold">{{ $voucher->invoice_number }}</td>
                            <td>{{ optional($voucher->created_at)->format('d-M-Y') }}</td>
                            <td>{{ $voucher->customer->name ?? ($voucher->customer->business_name ?? 'N/A') }}</td>
                            <td class="text-center">{{ $voucher->items->count() }}</td>
                            <td class="text-end">Rs.{{ number_format((float) $voucher->total_amount, 2) }}</td>
                            <td>
                                <span class="badge bg-{{ $voucher->status === 'confirm' ? 'success' : ($voucher->status === 'pending' ? 'warning' : 'secondary') }}">
                                    {{ ucfirst($voucher->status) }}
                                </span>
                            </td>
                            <td class="text-center">
                                <button class="btn btn-sm btn-outline-primary" wire:click="loadVoucher({{ $voucher->id }})">
                                    <i class="bi bi-pencil me-1"></i>Load
                                </button>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-3">No sales vouchers found.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @else
    {{-- Voucher Edit Form (same layout as Add) --}}
    <div class="card shadow-sm mb-2 border">
        <div class="card-body py-2 px-3">
            <div class="row g-2 align-items-end">
                <div class="col-md-2">
                    <label class="form-label small fw-semibold text-muted mb-0">
                        <i class="bi bi-calendar3 me-1"></i>Voucher Date
                    </label>
                    <input type="date" class="form-control form-control-sm" wire:model.live="voucherDate" id="voucherDate">
                </div>

                <div class="col-md-3 position-relative">
                    <label class="form-label small fw-semibold text-muted mb-0">
                        <i class="bi bi-person me-1"></i>Party Account
                    </label>
                    <input type="text" class="form-control form-control-sm"
                        wire:model.live.debounce.300ms="customerSearch"
                        placeholder="Search customer..."
                        id="customerSearchInput"
                        @focus="$wire.showCustomerDropdown = true"
                        @blur="setTimeout(() => $wire.showCustomerDropdown = false, 200)"
                        autocomplete="off">

                    @if($showCustomerDropdown)
                    <div class="position-absolute w-100 bg-white border shadow-lg rounded-bottom" style="z-index:1050; max-height:250px; overflow-y:auto; top:100%;">
                        @forelse($this->filteredCustomers as $customer)
                        <div class="px-3 py-2 border-bottom small"
                            wire:click="selectCustomer({{ $customer['id'] }})"
                            style="cursor:pointer;"
                            @mouseenter="this.style.background='#f1f5f9'"
                            @mouseleave="this.style.background='white'">
                            <div class="fw-semibold">{{ $customer['business_name'] ?? $customer['name'] }}</div>
                            <div class="text-muted" style="font-size:10px">{{ $customer['phone'] ?? '' }}</div>
                        </div>
                        @empty
                        <div class="px-3 py-2 text-muted small">No match</div>
                        @endforelse
                    </div>
                    @endif
                </div>

                <div class="col-md-2">
                    <label class="form-label small fw-semibold text-muted mb-0">Billing Type</label>
                    <select class="form-select form-select-sm" wire:model.live="billingType">
                        <option value="cash">Cash</option>
                        <option value="credit">Credit</option>
                        <option value="cheque">Cheque</option>
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label small fw-semibold text-muted mb-0">Price Type</label>
                    <select class="form-select form-select-sm" wire:model.live="priceType">
                        <option value="retail">Retail Price</option>
                        <option value="wholesale">Wholesale Price</option>
                        <option value="distributor">Distributor Price</option>
                    </select>
                </div>

                <div class="col-md-1">
                    <label class="form-label small fw-semibold text-muted mb-0">Salesman</label>
                    <select class="form-select form-select-sm" wire:model="salesmanId">
                        <option value="">-- None --</option>
                        @foreach($salesmen as $sm)
                        <option value="{{ $sm['id'] }}">{{ $sm['name'] }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label small fw-semibold text-muted mb-0">Notes</label>
                    <input type="text" class="form-control form-control-sm" wire:model="notes" placeholder="Notes...">
                </div>
            </div>
        </div>
    </div>

    {{-- Item Grid --}}
    <div class="card shadow-sm mb-2 border">
        <div class="card-header py-1 px-3 d-flex justify-content-between align-items-center" style="background:#f8fafc;">
            <span class="fw-semibold small"><i class="bi bi-grid-3x3 me-1"></i>Item Entry</span>
            <span class="badge bg-primary">{{ $this->itemCount }} items</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-bordered table-hover mb-0 voucher-grid">
                    <thead class="table-dark" style="font-size:11px;">
                        <tr>
                            <th style="width:30px" class="text-center">#</th>
                            <th style="width:30%">Item Name</th>
                            <th style="width:100px">Batch/SKU</th>
                            <th style="width:70px" class="text-center">Stock</th>
                            <th style="width:70px" class="text-center">Qty</th>
                            <th style="width:90px" class="text-end">Rate</th>
                            <th style="width:70px" class="text-end">Disc/Unit</th>
                            <th style="width:100px" class="text-end">Amount</th>
                            <th style="width:35px" class="text-center"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($items as $index => $item)
                        <tr wire:key="item-row-{{ $index }}" class="{{ $item['product_id'] ? '' : 'table-light' }}">
                            <td class="text-center small text-muted">{{ $index + 1 }}</td>
                            <td class="position-relative p-0">
                                <input type="text" class="form-control form-control-sm border-0 rounded-0"
                                    wire:model.live.debounce.300ms="items.{{ $index }}.product_search"
                                    placeholder="Type to search..."
                                    id="item-search-{{ $index }}"
                                    autocomplete="off"
                                    @focus="$wire.activeItemIndex = {{ $index }}">

                                @if($activeItemIndex === $index && $showProductDropdown && count($productSearchResults) > 0)
                                <div class="position-absolute w-100 bg-white border shadow-lg" style="z-index:1060; max-height:200px; overflow-y:auto; top:100%; left:0;">
                                    @foreach($productSearchResults as $product)
                                    <div class="px-2 py-1 border-bottom small"
                                        wire:click="selectProduct({{ $index }}, {{ $product['id'] }})"
                                        style="cursor:pointer;"
                                        @mouseenter="this.style.background='#eff6ff'"
                                        @mouseleave="this.style.background='white'">
                                        <div class="fw-semibold">{{ $product['name'] }}</div>
                                        <div class="text-muted" style="font-size:10px">
                                            {{ $product['code'] }} | Stock: {{ $product['available_stock'] }} |
                                            @php
                                            $rowPrice = $priceType === 'retail'
                                            ? ($product['retail_price'] ?? $product['distributor_price'] ?? $product['wholesale_price'])
                                            : ($priceType === 'wholesale'
                                            ? ($product['wholesale_price'] ?? $product['distributor_price'] ?? $product['retail_price'])
                                            : ($product['distributor_price'] ?? $product['wholesale_price'] ?? $product['retail_price']));
                                            @endphp
                                            Rs.{{ number_format($rowPrice, 2) }}
                                        </div>
                                    </div>
                                    @endforeach
                                </div>
                                @endif
                            </td>
                            <td class="p-0">
                                <input type="text" class="form-control form-control-sm border-0 rounded-0 text-muted"
                                    value="{{ $item['sku'] ?? '' }}" readonly tabindex="-1" style="background:transparent;">
                            </td>
                            <td class="text-center p-0">
                                <span class="small {{ ($item['available_stock'] ?? 0) > 0 ? 'text-success' : 'text-danger' }}">
                                    {{ $item['available_stock'] ?? 0 }}
                                </span>
                            </td>
                            <td class="p-0">
                                <input type="number" class="form-control form-control-sm border-0 rounded-0 text-center"
                                    wire:model.live.debounce.500ms="items.{{ $index }}.quantity"
                                    min="1" {{ !$item['product_id'] ? 'disabled' : '' }}>
                            </td>
                            <td class="p-0">
                                <input type="number" step="0.01" class="form-control form-control-sm border-0 rounded-0 text-end"
                                    wire:model.live.debounce.500ms="items.{{ $index }}.rate"
                                    {{ !$item['product_id'] ? 'disabled' : '' }}>
                            </td>
                            <td class="p-0">
                                <input type="number" step="0.01" class="form-control form-control-sm border-0 rounded-0 text-end"
                                    wire:model.live.debounce.500ms="items.{{ $index }}.discount"
                                    {{ !$item['product_id'] ? 'disabled' : '' }}>
                            </td>
                            <td class="text-end fw-semibold small p-1">{{ number_format($item['amount'] ?? 0, 2) }}</td>
                            <td class="text-center p-0">
                                @if($item['product_id'])
                                <button class="btn btn-sm text-danger border-0 p-0" wire:click="removeRow({{ $index }})">
                                    <i class="bi bi-x-circle"></i>
                                </button>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="px-3 py-1">
                <button class="btn btn-sm btn-outline-secondary" wire:click="addEmptyRow">
                    <i class="bi bi-plus me-1"></i>Add Row
                </button>
            </div>
        </div>
    </div>

    {{-- Totals & Actions --}}
    <div class="card shadow-sm border">
        <div class="card-body py-2 px-3">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <div class="d-flex gap-2">
                        <button class="btn btn-sm btn-success px-4" wire:click="updateVoucher"
                            wire:loading.attr="disabled" wire:target="updateVoucher">
                            <span wire:loading.remove wire:target="updateVoucher">
                                <i class="bi bi-check2-circle me-1"></i>Update Voucher
                                <kbd class="ms-1" style="font-size:9px">Alt+S</kbd>
                            </span>
                            <span wire:loading wire:target="updateVoucher">
                                <span class="spinner-border spinner-border-sm me-1"></span>Updating...
                            </span>
                        </button>
                        <button class="btn btn-sm btn-outline-secondary" wire:click="clearVoucher">
                            <i class="bi bi-arrow-left me-1"></i>Back to Search
                            <kbd class="ms-1" style="font-size:9px">Esc</kbd>
                        </button>
                    </div>
                </div>
                <div class="col-md-6">
                    <table class="table table-sm table-borderless mb-0 float-end" style="width:280px;">
                        <tr>
                            <td class="text-muted small py-0">Subtotal:</td>
                            <td class="text-end small py-0">Rs.{{ number_format($this->subtotal, 2) }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted small py-0">Discount:</td>
                            <td class="text-end small text-danger py-0">- Rs.{{ number_format($this->totalDiscount, 2) }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted small py-0">Tax:</td>
                            <td class="text-end small py-0">+ Rs.{{ number_format($this->totalTax, 2) }}</td>
                        </tr>
                        <tr class="border-top">
                            <td class="fw-bold py-1">Grand Total:</td>
                            <td class="text-end fw-bold fs-5 py-1" style="color:#7c3aed;">
                                Rs.{{ number_format($this->grandTotal, 2) }}
                            </td>
                        </tr>
                    </table>
                </div>
            </div>

            @if($billingType === 'cheque')
            <div class="row mt-2">
                <div class="col-md-12">
                    <div class="px-2 py-2 rounded" style="background:#eff6ff; border:1px solid #bfdbfe;">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div class="small">
                                <strong>Cheque Summary:</strong>
                                Total Cheques: Rs.{{ number_format($this->totalChequeAmount, 2) }} |
                                Remaining: <span class="{{ abs($this->remainingChequeAmount) < 0.01 ? 'text-success fw-semibold' : 'text-danger fw-semibold' }}">Rs.{{ number_format($this->remainingChequeAmount, 2) }}</span>
                            </div>
                            <button class="btn btn-sm btn-outline-primary" wire:click="$set('showChequeModal', true)">
                                <i class="bi bi-receipt me-1"></i>Manage Cheques
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>
    @endif

    {{-- Cheque Entry Modal --}}
    @if($showChequeModal)
    <div class="modal fade show d-block" tabindex="-1" style="background:rgba(0,0,0,0.5);">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-info text-white py-2">
                    <h6 class="modal-title"><i class="bi bi-receipt me-2"></i>Update Cheques</h6>
                    <button class="btn-close btn-close-white" wire:click="closeChequeModal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-2 align-items-end mb-3">
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold mb-1">Cheque Number</label>
                            <input type="text" class="form-control form-control-sm" wire:model="tempChequeNumber" placeholder="Cheque #">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold mb-1">Bank Name</label>
                            <input type="text" class="form-control form-control-sm" wire:model="tempChequeBankName" placeholder="Bank name">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold mb-1">Cheque Date</label>
                            <input type="date" class="form-control form-control-sm" wire:model="tempChequeDate">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-semibold mb-1">Amount</label>
                            <input type="number" step="0.01" class="form-control form-control-sm" wire:model="tempChequeAmount">
                        </div>
                        <div class="col-md-1 d-grid">
                            <button class="btn btn-sm btn-primary" wire:click="addCheque" title="Add cheque">
                                <i class="bi bi-plus"></i>
                            </button>
                        </div>
                    </div>

                    <div class="table-responsive border rounded">
                        <table class="table table-sm table-bordered mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Cheque #</th>
                                    <th>Bank</th>
                                    <th>Date</th>
                                    <th class="text-end">Amount</th>
                                    <th class="text-center" style="width:60px;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($cheques as $index => $chq)
                                <tr>
                                    <td>{{ $chq['number'] }}</td>
                                    <td>{{ $chq['bank_name'] }}</td>
                                    <td>{{ \Carbon\Carbon::parse($chq['date'])->format('d-M-Y') }}</td>
                                    <td class="text-end">Rs.{{ number_format($chq['amount'], 2) }}</td>
                                    <td class="text-center">
                                        <button class="btn btn-sm text-danger border-0 p-0" wire:click="removeCheque({{ $index }})">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-3">No cheques added yet.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="row mt-3">
                        <div class="col-md-6">
                            <div class="small text-muted">Grand Total: <strong>Rs.{{ number_format($this->grandTotal, 2) }}</strong></div>
                            <div class="small text-muted">Cheque Total: <strong>Rs.{{ number_format($this->totalChequeAmount, 2) }}</strong></div>
                            <div class="small {{ abs($this->remainingChequeAmount) < 0.01 ? 'text-success' : 'text-danger' }}">
                                Remaining: <strong>Rs.{{ number_format($this->remainingChequeAmount, 2) }}</strong>
                            </div>
                        </div>
                        <div class="col-md-6 text-end">
                            @if(abs($this->remainingChequeAmount) < 0.01)
                            <span class="badge bg-success">Cheque total matched</span>
                            @else
                            <span class="badge bg-warning text-dark">Add cheques to match total</span>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button class="btn btn-sm btn-outline-secondary" wire:click="closeChequeModal">Cancel</button>
                    <button class="btn btn-sm btn-success" wire:click="completeChequeVoucherUpdate">
                        <i class="bi bi-check2-circle me-1"></i>Complete Update
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- Success Modal --}}
    @if($showSavedModal && $savedSale)
    <div class="modal fade show d-block" tabindex="-1" style="background:rgba(0,0,0,0.5);">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-success text-white py-2">
                    <h6 class="modal-title"><i class="bi bi-check-circle me-2"></i>Voucher Updated</h6>
                    <button class="btn-close btn-close-white" wire:click="closeSavedModal"></button>
                </div>
                <div class="modal-body text-center py-4">
                    <i class="bi bi-check-circle text-success" style="font-size:3rem;"></i>
                    <h5 class="mt-2">{{ $savedSale->invoice_number }}</h5>
                    <p class="text-muted">Updated Total: <strong>Rs.{{ number_format($savedSale->total_amount, 2) }}</strong></p>
                    <div class="d-flex gap-2 justify-content-center mt-3">
                        <button class="btn btn-primary btn-sm" wire:click="closeSavedModal">OK</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- Keyboard Shortcut Help --}}
    <div class="mt-2 text-center">
        <small class="text-muted">
            <kbd>Alt+S</kbd> Update &nbsp;|&nbsp;
            <kbd>Esc</kbd> Back &nbsp;|&nbsp;
            <kbd>F2</kbd> Date &nbsp;|&nbsp;
            <kbd>F3</kbd> Party Search &nbsp;|&nbsp;
            <kbd>F4</kbd> Item Search &nbsp;|&nbsp;
            <kbd>Enter</kbd> Next Field
        </small>
    </div>

    @push('scripts')
    <script>
    function modifyVoucherKeyboard() {
        return {
            initKeyboard() {
                this.$nextTick(() => {
                    document.getElementById('voucherSearchInput')?.focus();
                });
            },
            handleGlobalKey(e) {
                if (e.altKey && e.key.toLowerCase() === 's') {
                    e.preventDefault();
                    @this.updateVoucher();
                }
                if (e.key === 'F2') {
                    e.preventDefault();
                    document.getElementById('voucherDate')?.focus();
                }
                if (e.key === 'F3') {
                    e.preventDefault();
                    document.getElementById('customerSearchInput')?.focus();
                }
                if (e.key === 'F4') {
                    e.preventDefault();
                    document.querySelector('[id^="item-search-"]')?.focus();
                }
                if (e.key === 'Escape') {
                    if (!@this.showSavedModal && !@this.showChequeModal) {
                        e.preventDefault();
                        @this.clearVoucher();
                    }
                }
            }
        }
    }
    </script>
    @endpush

    <style>
        .voucher-grid input:focus { background-color: #f5f3ff !important; outline: 2px solid #7c3aed; }
        .voucher-grid input { font-size: 12px; }
        .voucher-grid td { vertical-align: middle; }
        kbd { background: #374151; color: #fff; padding: 1px 4px; border-radius: 3px; font-size: 10px; }
    </style>
</div>
