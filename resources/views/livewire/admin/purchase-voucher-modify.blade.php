<div x-data="modifyPurchaseKeyboard()" x-init="initKeyboard()" @keydown.window="handleGlobalKey($event)">

    {{-- ═══ BUSY-Style Header Bar ═══ --}}
    <div class="d-flex align-items-center justify-content-between px-3 py-2" style="background:linear-gradient(135deg, #0f172a 0%, #1e293b 100%); border-bottom:2px solid var(--primary);">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-pencil-square text-white" style="font-size:1.2rem;"></i>
            <h5 class="fw-bold text-white mb-0" style="font-size:15px;">Modify Purchase Voucher</h5>
            @if($isLoaded)
                <span class="badge" style="background:var(--primary); font-size:11px;">{{ $voucherNumber }}</span>
            @endif
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('admin.purchase-voucher-list') }}" class="btn btn-sm btn-outline-light" style="font-size:11px;">
                <i class="bi bi-list-ul me-1"></i> List <kbd class="ms-1" style="font-size:9px;">Alt+L</kbd>
            </a>
            <a href="{{ route('admin.purchase-create') }}" class="btn btn-sm btn-outline-light" style="font-size:11px;">
                <i class="bi bi-plus-circle me-1"></i> Add New <kbd class="ms-1" style="font-size:9px;">Alt+A</kbd>
            </a>
        </div>
    </div>

    <div class="container-fluid px-3 py-2">

        {{-- ═══ SEARCH SECTION ═══ --}}
        @if(!$isLoaded)
        <div class="card mb-3" style="border-left:3px solid #0ea5e9;">
            <div class="card-header py-2 px-3 d-flex align-items-center gap-2" style="background:#f0f9ff; border-bottom:1px solid #bae6fd;">
                <i class="bi bi-search" style="color:#0ea5e9;"></i>
                <span class="fw-600" style="font-size:12px;">Search Purchase Voucher</span>
            </div>
            <div class="card-body py-3 px-3">
                <div class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label mb-1" style="font-size:11px; font-weight:600;">Search By</label>
                        <select class="form-select form-select-sm" wire:model="searchType">
                            <option value="voucher_number">Voucher / Invoice No.</option>
                            <option value="date">Date</option>
                            <option value="supplier">Supplier Name</option>
                        </select>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label mb-1" style="font-size:11px; font-weight:600;">Search Query</label>
                        @if($searchType === 'date')
                            <input type="date" class="form-control form-control-sm" wire:model="searchQuery" id="searchQueryInput">
                        @else
                            <input type="text" class="form-control form-control-sm" wire:model="searchQuery" placeholder="Enter search term..." id="searchQueryInput"
                                   @keydown.enter.prevent="$wire.searchVouchers()">
                        @endif
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-primary btn-sm w-100" wire:click="searchVouchers" wire:loading.attr="disabled">
                            <i class="bi bi-search me-1"></i> Search
                            <span wire:loading wire:target="searchVouchers" class="spinner-border spinner-border-sm ms-1"></span>
                        </button>
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-outline-secondary btn-sm w-100" wire:click="resetForm">
                            <i class="bi bi-arrow-counterclockwise me-1"></i> Reset
                        </button>
                    </div>
                </div>

                {{-- Search Results --}}
                @if($showSearchResults)
                <div class="mt-3" style="max-height:300px; overflow-y:auto;">
                    @forelse($searchResults as $result)
                        <div class="d-flex justify-content-between align-items-center px-3 py-2" style="border-bottom:1px solid var(--border); cursor:pointer; transition:background 0.15s;"
                             wire:click="loadVoucher({{ $result->id }})"
                             @mouseenter="this.style.background='#f0f9ff'" @mouseleave="this.style.background='transparent'">
                            <div>
                                <span class="fw-600" style="font-size:12px; color:var(--primary);">{{ $result->order_code }}</span>
                                @if($result->invoice_number)
                                    <span class="text-muted ms-2" style="font-size:11px;">INV: {{ $result->invoice_number }}</span>
                                @endif
                                <span class="text-muted ms-2" style="font-size:11px;">{{ \Carbon\Carbon::parse($result->order_date)->format('d M Y') }}</span>
                            </div>
                            <div class="d-flex align-items-center gap-3">
                                <span style="font-size:12px;">{{ $result->supplier->name ?? 'N/A' }}</span>
                                <span class="fw-700" style="font-size:12px; color:var(--success);">Rs {{ number_format($result->total_amount, 2) }}</span>
                                <span class="badge {{ $result->status === 'complete' ? 'bg-success' : ($result->status === 'pending' ? 'bg-warning' : 'bg-secondary') }}" style="font-size:10px;">
                                    {{ ucfirst($result->status) }}
                                </span>
                                <i class="bi bi-chevron-right" style="color:var(--text-muted);"></i>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-3 text-muted" style="font-size:12px;">
                            <i class="bi bi-inbox" style="font-size:1.5rem;"></i>
                            <p class="mt-1 mb-0">No vouchers found</p>
                        </div>
                    @endforelse
                </div>
                @endif
            </div>
        </div>
        @endif

        {{-- ═══ VOUCHER EDITOR ═══ --}}
        @if($isLoaded)

        {{-- Voucher Header --}}
        <div class="card mb-2" style="border-left:3px solid var(--primary);">
            <div class="card-body py-2 px-3">
                <div class="row g-2 align-items-end">
                    <div class="col-xl-2 col-md-3">
                        <label class="form-label fw-600 mb-1" style="font-size:10px; text-transform:uppercase; letter-spacing:.04em; color:var(--text-muted);">
                            <i class="bi bi-calendar-event me-1" style="color:var(--warning);"></i>Voucher Date <kbd style="font-size:8px;">F2</kbd>
                        </label>
                        <input type="date" class="form-control form-control-sm" wire:model="voucherDate" id="voucherDate">
                    </div>
                    <div class="col-xl-2 col-md-3">
                        <label class="form-label fw-600 mb-1" style="font-size:10px; text-transform:uppercase; letter-spacing:.04em; color:var(--text-muted);">
                            <i class="bi bi-hash me-1" style="color:var(--info);"></i>Voucher No.
                        </label>
                        <input type="text" class="form-control form-control-sm fw-bold" value="{{ $voucherNumber }}" readonly style="background:#f8fafc; font-family:monospace; color:var(--primary);">
                    </div>
                    <div class="col-xl-2 col-md-3">
                        <label class="form-label fw-600 mb-1" style="font-size:10px; text-transform:uppercase; letter-spacing:.04em; color:var(--text-muted);">
                            <i class="bi bi-receipt me-1" style="color:var(--info);"></i>Invoice No.
                        </label>
                        <input type="text" class="form-control form-control-sm fw-bold" wire:model="invoiceNumber" style="font-family:monospace;">
                    </div>
                    <div class="col-xl-3 col-md-4">
                        <label class="form-label fw-600 mb-1" style="font-size:10px; text-transform:uppercase; letter-spacing:.04em; color:var(--text-muted);">
                            <i class="bi bi-building me-1" style="color:var(--primary);"></i>Supplier Account <kbd style="font-size:8px;">F3</kbd>
                        </label>
                        <div class="position-relative">
                            <input type="text" class="form-control form-control-sm" wire:model.live.debounce.300ms="supplierSearch"
                                   placeholder="Search supplier..." id="supplierSearchInput"
                                   @focus="$wire.set('showSupplierDropdown', true)"
                                   @keydown.escape="$wire.set('showSupplierDropdown', false)">
                            @if($showSupplierDropdown && $filteredSuppliers->count() > 0)
                                <div class="position-absolute w-100 bg-white rounded-2 mt-1" style="z-index:1055; max-height:200px; overflow-y:auto; box-shadow:0 8px 24px rgba(0,0,0,.12); border:1px solid var(--border);">
                                    @foreach($filteredSuppliers as $s)
                                        <div class="px-3 py-2" style="cursor:pointer; font-size:12px; border-bottom:1px solid var(--border);"
                                             wire:click="selectSupplier({{ $s->id }})"
                                             @mouseenter="this.style.background='#f0f9ff'" @mouseleave="this.style.background='transparent'">
                                            <span class="fw-600">{{ $s->name }}</span>
                                            @if($s->business_name) <span class="text-muted ms-1">({{ $s->business_name }})</span> @endif
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                    <div class="col-xl-1 col-md-2">
                        <label class="form-label fw-600 mb-1" style="font-size:10px; text-transform:uppercase; letter-spacing:.04em; color:var(--text-muted);">Type</label>
                        <select class="form-select form-select-sm" wire:model="billingType">
                            <option value="cash">Cash</option>
                            <option value="credit">Credit</option>
                        </select>
                    </div>
                    <div class="col-xl-2 col-md-3">
                        <label class="form-label fw-600 mb-1" style="font-size:10px; text-transform:uppercase; letter-spacing:.04em; color:var(--text-muted);">
                            <i class="bi bi-truck me-1"></i>Transport
                        </label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text" style="font-size:10px;">Rs</span>
                            <input type="number" step="0.01" min="0" class="form-control" wire:model="transportCost">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Item Entry Grid --}}
        <div class="card mb-2">
            <div class="card-header py-2 px-3 d-flex align-items-center gap-2" style="background:#f8fafc; border-bottom:1px solid var(--border);">
                <i class="bi bi-table" style="color:var(--primary);"></i>
                <span class="fw-600" style="font-size:12px;">Purchase Items</span>
                <span class="badge ms-auto" style="background:var(--primary); font-size:10px;">{{ $this->itemCount }} items</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0 voucher-grid" style="font-size:12px;">
                        <thead style="background:#f1f5f9;">
                            <tr>
                                <th style="width:30px;" class="text-center">#</th>
                                <th style="width:30%; min-width:200px;">
                                    Item Name <kbd style="font-size:8px; background:#ddd; color:#333; padding:1px 3px; border-radius:2px;">F4</kbd>
                                </th>
                                <th style="width:80px;" class="text-center">Stock</th>
                                <th style="width:70px;" class="text-center">Qty</th>
                                <th style="width:60px;" class="text-center">Free</th>
                                <th style="width:100px;" class="text-center">Rate (Rs)</th>
                                <th style="width:80px;" class="text-center">Disc/Unit</th>
                                <th style="width:70px;" class="text-center">Tax %</th>
                                <th style="width:100px;" class="text-center">Amount</th>
                                <th style="width:40px;" class="text-center">×</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($items as $index => $item)
                                <tr wire:key="item-{{ $index }}">
                                    <td class="text-center align-middle" style="color:var(--text-muted); font-size:11px;">{{ $index + 1 }}</td>

                                    {{-- Item Search --}}
                                    <td class="position-relative">
                                        <input type="text" class="form-control form-control-sm border-0 px-1"
                                               wire:model.live.debounce.300ms="items.{{ $index }}.search"
                                               wire:change="searchProducts({{ $index }})"
                                               wire:keydown.debounce.300ms="searchProducts({{ $index }})"
                                               placeholder="Type to search..."
                                               id="item-search-{{ $index }}"
                                               @keydown.enter.prevent="$event.target.closest('tr').querySelector('[data-field=qty]')?.focus()">

                                        @if($showProductDropdown && $activeItemIndex === $index && count($productSearchResults) > 0)
                                            <div class="position-absolute w-100 bg-white rounded-2" style="z-index:1060; top:100%; left:0; max-height:220px; overflow-y:auto; box-shadow:0 8px 24px rgba(0,0,0,.15); border:1px solid var(--border);">
                                                @foreach($productSearchResults as $ri => $res)
                                                    <div class="px-2 py-1 d-flex justify-content-between align-items-center" style="cursor:pointer; font-size:11px; border-bottom:1px solid var(--border);"
                                                         wire:click="selectProduct({{ $index }}, {{ $ri }})"
                                                         @mouseenter="this.style.background='#f0f9ff'" @mouseleave="this.style.background='transparent'">
                                                        <div>
                                                            <span class="fw-600">{{ $res['name'] }}</span>
                                                            @if($res['variant_value'])
                                                                <span class="badge ms-1" style="background:#0ea5e9; font-size:9px;">{{ $res['variant_name'] }}: {{ $res['variant_value'] }}</span>
                                                            @endif
                                                            <span class="text-muted ms-1">[{{ $res['code'] }}]</span>
                                                        </div>
                                                        <div class="text-end">
                                                            <span class="fw-600" style="color:var(--success);">Rs {{ number_format($res['supplier_price'], 2) }}</span>
                                                            <span class="text-muted ms-1">Stk: {{ $res['available_stock'] }}</span>
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif
                                    </td>

                                    <td class="text-center align-middle">
                                        <span class="badge {{ ($item['available_stock'] ?? 0) > 0 ? 'bg-success' : 'bg-secondary' }}" style="font-size:10px;">
                                            {{ $item['available_stock'] ?? 0 }}
                                        </span>
                                    </td>

                                    <td>
                                        <input type="number" min="1" class="form-control form-control-sm border-0 text-center px-1" data-field="qty"
                                               wire:model.lazy="items.{{ $index }}.quantity"
                                               @change="$wire.calculateLineTotal({{ $index }})"
                                               @keydown.enter.prevent="$event.target.closest('tr').querySelector('[data-field=free]')?.focus()">
                                    </td>

                                    <td>
                                        <input type="number" min="0" class="form-control form-control-sm border-0 text-center px-1" data-field="free"
                                               wire:model.lazy="items.{{ $index }}.free_qty"
                                               @keydown.enter.prevent="$event.target.closest('tr').querySelector('[data-field=rate]')?.focus()">
                                    </td>

                                    <td>
                                        <input type="number" step="0.01" min="0" class="form-control form-control-sm border-0 text-center px-1" data-field="rate"
                                               wire:model.lazy="items.{{ $index }}.rate"
                                               @change="$wire.calculateLineTotal({{ $index }})"
                                               @keydown.enter.prevent="$event.target.closest('tr').querySelector('[data-field=disc]')?.focus()">
                                    </td>

                                    <td>
                                        <input type="number" step="0.01" min="0" class="form-control form-control-sm border-0 text-center px-1" data-field="disc"
                                               wire:model.lazy="items.{{ $index }}.discount"
                                               @change="$wire.calculateLineTotal({{ $index }})"
                                               @keydown.enter.prevent="$event.target.closest('tr').querySelector('[data-field=tax]')?.focus()">
                                    </td>

                                    <td>
                                        <input type="number" step="0.01" min="0" class="form-control form-control-sm border-0 text-center px-1" data-field="tax"
                                               wire:model.lazy="items.{{ $index }}.tax_percentage"
                                               @change="$wire.calculateLineTotal({{ $index }})"
                                               @keydown.enter.prevent="
                                                   let nextRow = $event.target.closest('tr').nextElementSibling;
                                                   if (nextRow) nextRow.querySelector('[id^=item-search-]')?.focus();
                                               ">
                                    </td>

                                    <td class="text-center align-middle">
                                        <span class="fw-700" style="font-size:12px; color:var(--success);">
                                            {{ number_format($item['amount'] ?? 0, 2) }}
                                        </span>
                                    </td>

                                    <td class="text-center align-middle">
                                        @if($item['product_id'])
                                            <button class="btn btn-sm p-0 px-1" style="color:#ef4444; font-size:11px;" wire:click="removeItem({{ $index }})">
                                                <i class="bi bi-x-lg"></i>
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Total Bar --}}
        <div class="card" style="border-top:3px solid var(--primary);">
            <div class="card-body py-2 px-3">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <div class="d-flex gap-4" style="font-size:12px;">
                            <span class="text-muted">Items: <strong>{{ $this->itemCount }}</strong></span>
                            <span class="text-muted">Subtotal: <strong>Rs {{ number_format($this->subtotal, 2) }}</strong></span>
                            <span class="text-muted">Transport: <strong>Rs {{ number_format(floatval($transportCost), 2) }}</strong></span>
                        </div>
                    </div>
                    <div class="col-md-6 text-end">
                        <span class="fw-800" style="font-size:20px; color:var(--primary);">
                            Grand Total: Rs {{ number_format($this->grandTotal, 2) }}
                        </span>
                    </div>
                </div>
                <div class="d-flex justify-content-between align-items-center mt-2 pt-2" style="border-top:1px solid var(--border);">
                    <div class="d-flex gap-2">
                        <button class="btn btn-outline-secondary btn-sm" wire:click="resetForm">
                            <i class="bi bi-arrow-counterclockwise me-1"></i> Back to Search
                        </button>
                    </div>
                    <button class="btn btn-success btn-sm px-4 fw-700" wire:click="updateVoucher" wire:loading.attr="disabled"
                            @if(!$loadedOrderId || $this->itemCount === 0) disabled @endif>
                        <i class="bi bi-check-circle me-1"></i> Update Voucher
                        <kbd class="ms-1" style="font-size:9px;">Alt+S</kbd>
                        <span wire:loading wire:target="updateVoucher" class="spinner-border spinner-border-sm ms-1"></span>
                    </button>
                </div>
            </div>
        </div>

        @endif
    </div>

    {{-- Status Bar --}}
    <div class="px-3 py-1 d-flex justify-content-between align-items-center" style="background:#f1f5f9; border-top:1px solid var(--border); font-size:11px; color:var(--text-muted);">
        <div>
            <kbd>Alt+S</kbd> Update &nbsp;|&nbsp;
            <kbd>F2</kbd> Date &nbsp;|&nbsp;
            <kbd>F3</kbd> Supplier &nbsp;|&nbsp;
            <kbd>F4</kbd> Item Search &nbsp;|&nbsp;
            <kbd>Esc</kbd> Back
        </div>
        <div>Purchase Voucher — Modify Mode</div>
    </div>

    @push('scripts')
    <script>
    function modifyPurchaseKeyboard() {
        return {
            initKeyboard() {
                this.$nextTick(() => {
                    document.getElementById('searchQueryInput')?.focus();
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
                    document.getElementById('supplierSearchInput')?.focus();
                }
                if (e.key === 'F4') {
                    e.preventDefault();
                    let firstSearch = document.querySelector('[id^="item-search-"]');
                    if (firstSearch) firstSearch.focus();
                }
                if (e.key === 'Escape') {
                    if (!@this.showSavedModal) {
                        e.preventDefault();
                        @this.resetForm();
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
    </style>
</div>
