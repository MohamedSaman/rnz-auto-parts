<div x-data="voucherKeyboard()" x-init="initKeyboard()" @keydown.window="handleGlobalKey($event)"
    @customer-selected.window="focusBillingType()"
    @product-selected.window="focusRowQty($event.detail.index)">
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

    {{-- Voucher Header Bar --}}
    <div class="card shadow-sm mb-2 border-0" style="background: linear-gradient(135deg, #1e293b 0%, #334155 100%);">
        <div class="card-body py-2 px-3">
            <div class="row align-items-center">
                <div class="col-auto">
                    <h5 class="text-white mb-0 fw-bold">
                        <i class="bi bi-receipt-cutoff me-2"></i>Sales Voucher — Add
                    </h5>
                </div>
                <div class="col text-end">
                    <span class="badge bg-light text-dark fs-6 px-3 py-2">
                        <i class="bi bi-hash"></i> {{ $voucherNumber }}
                    </span>
                </div>
            </div>
        </div>
    </div>

    {{-- Voucher Details Section --}}
    <div class="card shadow-sm mb-2 border">
        <div class="card-body py-2 px-3">
            <div class="row g-2 align-items-end">
                {{-- Voucher Date --}}
                <div class="col-md-2">
                    <label class="form-label small fw-semibold text-muted mb-0">
                        <i class="bi bi-calendar3 me-1"></i>Voucher Date <span class="text-danger">*</span>
                        <kbd class="ms-1 small" style="font-size:9px">F2</kbd>
                    </label>
                    <input type="date" class="form-control form-control-sm"
                        wire:model.live="voucherDate" id="voucherDate">
                </div>

                {{-- Party Account (Customer) --}}
                <div class="col-md-3 position-relative">
                    <label class="form-label small fw-semibold text-muted mb-0">
                        <i class="bi bi-person me-1"></i>Party Account <span class="text-danger">*</span>
                        <kbd class="ms-1 small" style="font-size:9px">F3</kbd>
                    </label>
                    <div class="input-group input-group-sm">
                        <input type="text" class="form-control"
                            wire:model.live.debounce.300ms="customerSearch"
                            placeholder="Search customer..."
                            id="customerSearchInput"
                            @focus="$wire.showCustomerDropdown = true"
                            @input="resetCustomerSelection()"
                            @keydown.arrow-down.prevent="handleCustomerArrow(1)"
                            @keydown.arrow-up.prevent="handleCustomerArrow(-1)"
                            @keydown.enter.prevent="handleCustomerEnter()"
                            @blur="setTimeout(() => $wire.showCustomerDropdown = false, 200)"
                            autocomplete="off">
                        <button class="btn btn-outline-primary" type="button" wire:click="openCustomerModal" title="Add New Customer">
                            <i class="bi bi-plus"></i>
                        </button>
                    </div>

                    {{-- Customer dropdown --}}
                    @if($showCustomerDropdown)
                    <div class="position-absolute w-100 bg-white border shadow-lg rounded-bottom" style="z-index:1050; max-height:250px; overflow-y:auto; top:100%;">
                        @forelse($this->filteredCustomers as $customer)
                        <div class="px-3 py-2 border-bottom cursor-pointer hover-bg-light"
                            wire:click="selectCustomer({{ $customer['id'] }})"
                            data-customer-option="true"
                            style="cursor:pointer;"
                            @mouseenter="this.style.background='#f1f5f9'"
                            @mouseleave="this.style.background='white'">
                            <div class="fw-semibold small">{{ $customer['business_name'] ?? $customer['name'] }}</div>
                            <div class="text-muted" style="font-size:11px">
                                {{ $customer['phone'] ?? 'No phone' }} • {{ ucfirst($customer['type'] ?? 'retail') }}
                            </div>
                        </div>
                        @empty
                        <div class="px-3 py-2 text-muted small">No customers found</div>
                        @endforelse
                    </div>
                    @endif
                </div>

                {{-- Billing Type --}}
                <div class="col-md-2">
                    <label class="form-label small fw-semibold text-muted mb-0">
                        <i class="bi bi-credit-card me-1"></i>Billing Type
                    </label>
                    <select class="form-select form-select-sm" wire:model.live="billingType" id="billingTypeSelect"
                        @keydown.enter.prevent="focusPriceType()">
                        <option value="cash">Cash</option>
                        <option value="credit">Credit</option>
                        <option value="cheque">Cheque</option>
                    </select>
                </div>

                {{-- Price Type --}}
                <div class="col-md-2">
                    <label class="form-label small fw-semibold text-muted mb-0">
                        <i class="bi bi-tags me-1"></i>Price Type
                    </label>
                    <select class="form-select form-select-sm" wire:model.live="priceType" id="priceTypeSelect"
                        @keydown.enter.prevent="focusFirstItemSearch()">
                        <option value="retail">Retail Price</option>
                        <option value="wholesale">Wholesale Price</option>
                        <option value="distributor">Distributor Price</option>
                    </select>
                </div>

                {{-- Salesman --}}
                <div class="col-md-1">
                    <label class="form-label small fw-semibold text-muted mb-0">
                        <i class="bi bi-person-badge me-1"></i>Salesman
                    </label>
                    <select class="form-select form-select-sm" wire:model="salesmanId">
                        <option value="">-- None --</option>
                        @foreach($salesmen as $sm)
                        <option value="{{ $sm['id'] }}">{{ $sm['name'] }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Notes --}}
                <div class="col-md-2">
                    <label class="form-label small fw-semibold text-muted mb-0">
                        <i class="bi bi-chat-dots me-1"></i>Narration / Notes
                    </label>
                    <input type="text" class="form-control form-control-sm"
                        wire:model="notes" placeholder="Optional notes...">
                </div>
            </div>

            {{-- Selected Customer Info --}}
            @if($this->selectedCustomer)
            <div class="mt-2 px-2 py-1 rounded" style="background:#f0fdf4; border:1px solid #bbf7d0;">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="small">
                        <strong>{{ $this->selectedCustomer['business_name'] ?? $this->selectedCustomer['name'] }}</strong>
                        <span class="text-muted ms-2">{{ $this->selectedCustomer['phone'] ?? '' }}</span>
                        <span class="badge bg-info ms-1" style="font-size:10px">{{ ucfirst($this->selectedCustomer['type'] ?? 'retail') }}</span>
                    </div>
                    <div class="small">
                        @if(($this->selectedCustomer['due_amount'] ?? 0) > 0)
                        <span class="text-danger fw-semibold">Balance Due: Rs.{{ number_format($this->selectedCustomer['due_amount'], 2) }}</span>
                        @else
                        <span class="text-success">No Outstanding</span>
                        @endif
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>

    {{-- Item Entry Grid --}}
    <div class="card shadow-sm mb-2 border">
        <div class="card-header py-1 px-3 d-flex justify-content-between align-items-center" style="background:#f8fafc;">
            <span class="fw-semibold small"><i class="bi bi-grid-3x3 me-1"></i>Item Entry
                <kbd class="ms-1 small" style="font-size:9px">F4</kbd> Item Search
            </span>
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

                            {{-- Item Name with search --}}
                            <td class="position-relative p-0">
                                <input type="text" class="form-control form-control-sm border-0 rounded-0"
                                    wire:model.live.debounce.300ms="items.{{ $index }}.product_search"
                                    placeholder="Type to search item..."
                                    id="item-search-{{ $index }}"
                                    autocomplete="off"
                                    @focus="$wire.activeItemIndex = {{ $index }}"
                                    @keydown.arrow-down.prevent="handleItemArrow({{ $index }}, 1)"
                                    @keydown.arrow-up.prevent="handleItemArrow({{ $index }}, -1)"
                                    @keydown.enter.prevent="handleItemEnter({{ $index }})">

                                {{-- Product search results dropdown --}}
                                @if($activeItemIndex === $index && $showProductDropdown && count($productSearchResults) > 0)
                                <div class="position-absolute w-100 bg-white border shadow-lg" style="z-index:1060; max-height:220px; overflow-y:auto; top:100%; left:0;">
                                    @foreach($productSearchResults as $product)
                                    <div class="px-2 py-1 border-bottom small"
                                        wire:click="selectProduct({{ $index }}, {{ $product['id'] }})"
                                        data-product-option="true"
                                        data-row-index="{{ $index }}"
                                        style="cursor:pointer;"
                                        @mouseenter="this.style.background='#eff6ff'"
                                        @mouseleave="this.style.background='white'">
                                        <div class="fw-semibold">{{ $product['name'] }}</div>
                                        <div class="d-flex justify-content-between text-muted" style="font-size:10px;">
                                            <span>Code: {{ $product['code'] }}</span>
                                            <span>Stock: {{ $product['available_stock'] }}</span>
                                            @php
                                            $rowPrice = $priceType === 'retail'
                                            ? ($product['retail_price'] ?? $product['distributor_price'] ?? $product['wholesale_price'])
                                            : ($priceType === 'wholesale'
                                            ? ($product['wholesale_price'] ?? $product['distributor_price'] ?? $product['retail_price'])
                                            : ($product['distributor_price'] ?? $product['wholesale_price'] ?? $product['retail_price']));
                                            @endphp
                                            <span>Rs.{{ number_format($rowPrice, 2) }}</span>
                                        </div>
                                    </div>
                                    @endforeach
                                </div>
                                @endif
                            </td>

                            {{-- Batch/SKU --}}
                            <td class="p-0">
                                <input type="text" class="form-control form-control-sm border-0 rounded-0 text-muted"
                                    value="{{ $item['sku'] ?? $item['product_code'] ?? '' }}" readonly tabindex="-1"
                                    style="background:transparent;">
                            </td>

                            {{-- Stock --}}
                            <td class="text-center p-0">
                                <span class="small {{ ($item['available_stock'] ?? 0) > 0 ? 'text-success' : 'text-danger' }}">
                                    {{ $item['available_stock'] ?? 0 }}
                                </span>
                            </td>

                            {{-- Quantity --}}
                            <td class="p-0">
                                <input type="number" class="form-control form-control-sm border-0 rounded-0 text-center"
                                    wire:model.live.debounce.500ms="items.{{ $index }}.quantity"
                                    id="qty-{{ $index }}"
                                    data-field="qty"
                                    min="1" max="{{ $item['available_stock'] ?? 99999 }}"
                                    @keydown.enter.prevent="$event.target.closest('tr').querySelector('[data-field=rate]')?.focus()"
                                    {{ !$item['product_id'] ? 'disabled' : '' }}>
                            </td>

                            {{-- Rate --}}
                            <td class="p-0">
                                <input type="number" step="0.01" class="form-control form-control-sm border-0 rounded-0 text-end"
                                    wire:model.live.debounce.500ms="items.{{ $index }}.rate"
                                    id="rate-{{ $index }}"
                                    data-field="rate"
                                    @keydown.enter.prevent="$event.target.closest('tr').querySelector('[data-field=disc]')?.focus()"
                                    {{ !$item['product_id'] ? 'disabled' : '' }}>
                            </td>

                            {{-- Discount per unit --}}
                            <td class="p-0">
                                <input type="number" step="0.01" class="form-control form-control-sm border-0 rounded-0 text-end"
                                    wire:model.live.debounce.500ms="items.{{ $index }}.discount"
                                    id="disc-{{ $index }}"
                                    data-field="disc"
                                    placeholder="0"
                                    @keydown.enter.prevent="focusNextItemSearch({{ $index }})"
                                    {{ !$item['product_id'] ? 'disabled' : '' }}>
                            </td>

                            {{-- Amount (computed) --}}
                            <td class="text-end fw-semibold small p-1">
                                {{ number_format($item['amount'] ?? 0, 2) }}
                            </td>

                            {{-- Remove --}}
                            <td class="text-center p-0">
                                @if($item['product_id'])
                                <button class="btn btn-sm text-danger border-0 p-0"
                                    wire:click="removeRow({{ $index }})"
                                    title="Remove item">
                                    <i class="bi bi-x-circle"></i>
                                </button>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Add Row Button --}}
            <div class="px-3 py-1">
                <button class="btn btn-sm btn-outline-secondary" wire:click="addEmptyRow">
                    <i class="bi bi-plus me-1"></i>Add Row
                </button>
            </div>
        </div>
    </div>

    {{-- Totals Footer --}}
    <div class="card shadow-sm border">
        <div class="card-body py-2 px-3">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <div class="d-flex gap-3">
                        <button class="btn btn-sm btn-success px-4" wire:click="saveVoucher"
                            wire:loading.attr="disabled" wire:target="saveVoucher">
                            <span wire:loading.remove wire:target="saveVoucher">
                                <i class="bi bi-check2-circle me-1"></i>Save Voucher
                                <kbd class="ms-1" style="font-size:9px">Alt+S</kbd>
                            </span>
                            <span wire:loading wire:target="saveVoucher">
                                <span class="spinner-border spinner-border-sm me-1"></span>Saving...
                            </span>
                        </button>
                        <button class="btn btn-sm btn-outline-secondary" wire:click="resetVoucherForm">
                            <i class="bi bi-arrow-counterclockwise me-1"></i>Reset
                            <kbd class="ms-1" style="font-size:9px">Esc</kbd>
                        </button>
                        @if($savedSale)
                        <button class="btn btn-sm btn-outline-primary" wire:click="printVoucher">
                            <i class="bi bi-printer me-1"></i>Print Last
                        </button>
                        @endif
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
                            <td class="text-end fw-bold fs-5 py-1" style="color:var(--primary);">
                                Rs.{{ number_format($this->grandTotal, 2) }}
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- Saved Sale Modal --}}
    @if($showSavedModal && $savedSale)
    <div class="modal fade show d-block" tabindex="-1" style="background:rgba(0,0,0,0.5);">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-success text-white py-2">
                    <h6 class="modal-title"><i class="bi bi-check-circle me-2"></i>Voucher Saved Successfully</h6>
                    <button class="btn-close btn-close-white" wire:click="closeSavedModal"></button>
                </div>
                <div class="modal-body text-center py-4">
                    <div class="mb-3">
                        <i class="bi bi-receipt text-success" style="font-size:3rem;"></i>
                    </div>
                    <h5>Invoice: {{ $savedSale->invoice_number }}</h5>
                    <p class="text-muted">Amount: <strong>Rs.{{ number_format($savedSale->total_amount, 2) }}</strong></p>
                    <p class="text-muted small">Customer: {{ $savedSale->customer->name ?? 'N/A' }}</p>
                    <div class="d-flex gap-2 justify-content-center mt-3">
                        <a href="{{ route('admin.print.sale', $savedSale->id) }}" target="_blank" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-printer me-1"></i>Print
                        </a>
                        <button class="btn btn-primary btn-sm" wire:click="closeSavedModal">
                            <i class="bi bi-plus me-1"></i>New Voucher
                        </button>
                        <a href="{{ route('admin.sales-voucher-list') }}" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-list me-1"></i>View All
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- Customer Creation Modal --}}
    @if($showCustomerModal)
    <div class="modal fade show d-block" tabindex="-1" style="background:rgba(0,0,0,0.5);">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h6 class="modal-title"><i class="bi bi-person-plus me-2"></i>Add New Customer</h6>
                    <button class="btn-close" wire:click="closeCustomerModal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-2">
                        <div class="col-md-6">
                            <label class="form-label small">Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm" wire:model="newCustomerName">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small">Phone</label>
                            <input type="text" class="form-control form-control-sm" wire:model="newCustomerPhone">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small">Email</label>
                            <input type="email" class="form-control form-control-sm" wire:model="newCustomerEmail">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small">Type</label>
                            <select class="form-select form-select-sm" wire:model="newCustomerType">
                                <option value="retail">Retail</option>
                                <option value="wholesale">Wholesale</option>
                                <option value="distributor">Distributor</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small">Business Name</label>
                            <input type="text" class="form-control form-control-sm" wire:model="newBusinessName">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label small">Address</label>
                            <input type="text" class="form-control form-control-sm" wire:model="newCustomerAddress">
                        </div>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button class="btn btn-sm btn-secondary" wire:click="closeCustomerModal">Cancel</button>
                    <button class="btn btn-sm btn-primary" wire:click="createCustomer">
                        <i class="bi bi-check me-1"></i>Save Customer
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- Cheque Entry Modal --}}
    @if($showChequeModal)
    <div class="modal fade show d-block" tabindex="-1" style="background:rgba(0,0,0,0.5);">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-info text-white py-2">
                    <h6 class="modal-title"><i class="bi bi-receipt me-2"></i>Add Cheques</h6>
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
                    <button class="btn btn-sm btn-success" wire:click="completeChequeVoucherSave">
                        <i class="bi bi-check2-circle me-1"></i>Complete Sale
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- Keyboard Shortcut Help --}}
    <div class="mt-2 text-center">
        <small class="text-muted">
            <kbd>Alt+S</kbd> Save &nbsp;|&nbsp;
            <kbd>Esc</kbd> Reset/Cancel &nbsp;|&nbsp;
            <kbd>F2</kbd> Date &nbsp;|&nbsp;
            <kbd>F3</kbd> Party Search &nbsp;|&nbsp;
            <kbd>F4</kbd> Item Search &nbsp;|&nbsp;
            <kbd>Enter</kbd> Next Field
        </small>
    </div>

    @push('scripts')
    <script>
    function voucherKeyboard() {
        return {
            customerOptionIndex: -1,
            productOptionIndexes: {},
            initKeyboard() {
                // Focus on customer search on load
                this.$nextTick(() => {
                    document.getElementById('customerSearchInput')?.focus();
                });
            },
            resetCustomerSelection() {
                this.customerOptionIndex = -1;
                this.clearOptionHighlight('[data-customer-option]');
            },
            getOptions(selector) {
                return Array.from(document.querySelectorAll(selector));
            },
            clearOptionHighlight(selector) {
                this.getOptions(selector).forEach((el) => el.classList.remove('keyboard-highlight'));
            },
            highlightOption(selector, index) {
                const options = this.getOptions(selector);
                options.forEach((el, i) => {
                    el.classList.toggle('keyboard-highlight', i === index);
                });
            },
            moveIndex(current, max, direction) {
                if (max <= 0) return -1;
                if (current < 0) {
                    return direction > 0 ? 0 : max - 1;
                }
                const next = current + direction;
                if (next < 0) return 0;
                if (next >= max) return max - 1;
                return next;
            },
            handleCustomerArrow(direction) {
                const selector = '[data-customer-option]';
                const options = this.getOptions(selector);
                if (!options.length) return;

                this.customerOptionIndex = this.moveIndex(this.customerOptionIndex, options.length, direction);
                this.highlightOption(selector, this.customerOptionIndex);
            },
            handleCustomerEnter() {
                const options = this.getOptions('[data-customer-option]');
                if (!options.length) {
                    this.focusBillingType();
                    return;
                }

                const index = this.customerOptionIndex < 0 ? 0 : this.customerOptionIndex;
                options[index]?.click();
                this.customerOptionIndex = -1;
                this.clearOptionHighlight('[data-customer-option]');
            },
            focusBillingType() {
                this.$nextTick(() => {
                    document.getElementById('billingTypeSelect')?.focus();
                });
            },
            focusPriceType() {
                this.$nextTick(() => {
                    document.getElementById('priceTypeSelect')?.focus();
                });
            },
            focusFirstItemSearch() {
                this.$nextTick(() => {
                    document.getElementById('item-search-0')?.focus();
                });
            },
            getProductSelector(rowIndex) {
                return `[data-product-option][data-row-index="${rowIndex}"]`;
            },
            handleItemArrow(rowIndex, direction) {
                const selector = this.getProductSelector(rowIndex);
                const options = this.getOptions(selector);
                if (!options.length) return;

                const current = this.productOptionIndexes[rowIndex] ?? -1;
                this.productOptionIndexes[rowIndex] = this.moveIndex(current, options.length, direction);
                this.highlightOption(selector, this.productOptionIndexes[rowIndex]);
            },
            handleItemEnter(rowIndex) {
                const selector = this.getProductSelector(rowIndex);
                const options = this.getOptions(selector);

                if (options.length) {
                    const index = (this.productOptionIndexes[rowIndex] ?? -1) < 0 ? 0 : this.productOptionIndexes[rowIndex];
                    options[index]?.click();
                    this.productOptionIndexes[rowIndex] = -1;
                    this.clearOptionHighlight(selector);
                    return;
                }

                this.focusRowQty(rowIndex);
            },
            focusRowQty(rowIndex) {
                this.$nextTick(() => {
                    setTimeout(() => {
                        document.getElementById(`qty-${rowIndex}`)?.focus();
                    }, 40);
                });
            },
            focusNextItemSearch(rowIndex) {
                this.$nextTick(() => {
                    const nextIndex = Number(rowIndex) + 1;
                    document.getElementById(`item-search-${nextIndex}`)?.focus();
                });
            },
            handleGlobalKey(e) {
                // Alt+S = Save
                if (e.altKey && e.key.toLowerCase() === 's') {
                    e.preventDefault();
                    @this.saveVoucher();
                }
                // F2 = Focus date
                if (e.key === 'F2') {
                    e.preventDefault();
                    document.getElementById('voucherDate')?.focus();
                }
                // F3 = Focus party search
                if (e.key === 'F3') {
                    e.preventDefault();
                    document.getElementById('customerSearchInput')?.focus();
                }
                // F4 = Focus first item search
                if (e.key === 'F4') {
                    e.preventDefault();
                    let firstSearch = document.querySelector('[id^="item-search-"]');
                    if (firstSearch) firstSearch.focus();
                }
                // Escape = Reset
                if (e.key === 'Escape') {
                    // Only reset if no modal is open
                    if (!@this.showSavedModal && !@this.showCustomerModal && !@this.showChequeModal) {
                        e.preventDefault();
                        @this.resetVoucherForm();
                    }
                }
            }
        }
    }
    </script>
    @endpush

    <style>
        .voucher-grid input:focus { background-color: #eff6ff !important; outline: 2px solid #3b82f6; }
        .voucher-grid input { font-size: 12px; }
        .voucher-grid td { vertical-align: middle; }
        .voucher-grid thead th { font-size: 11px; white-space: nowrap; }
        kbd { background: #374151; color: #fff; padding: 1px 4px; border-radius: 3px; font-size: 10px; }
        .keyboard-highlight { background: #e0f2fe !important; }
    </style>
</div>
