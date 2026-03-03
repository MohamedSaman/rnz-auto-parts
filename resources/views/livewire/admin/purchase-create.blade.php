<div x-data="purchaseCreateApp()" x-init="init()" @keydown.window="handleKeyboard($event)" style="min-height:100vh; background:var(--page-bg);">

    {{-- ═══ Top Bar ═══ --}}
    <div style="background:var(--sidebar-bg); border-bottom:3px solid var(--primary); padding:0 24px;">
        <div class="d-flex justify-content-between align-items-center" style="height:56px;">
            <div class="d-flex align-items-center gap-3">
                <div style="background:var(--primary); border-radius:8px; width:32px; height:32px; display:flex; align-items:center; justify-content:center;">
                    <i class="bi bi-cart-plus-fill text-white" style="font-size:1rem;"></i>
                </div>
                <div>
                    <div class="fw-700 text-white" style="font-size:1rem; letter-spacing:-0.02em;">Create Purchase Order</div>
                    <div style="font-size:11px; color:var(--text-light); margin-top:1px;">Add products and save a new purchasing record</div>
                </div>
            </div>
            <a href="{{ route(auth()->user()->role === 'staff' ? 'staff.purchase-order-list' : 'admin.purchase-order-list') }}"
               style="font-size:12px; color:var(--text-light); background:rgba(255,255,255,.08); border:1px solid rgba(255,255,255,.15); border-radius:6px; padding:5px 14px; text-decoration:none; display:flex; align-items:center; gap:6px; transition:all .2s;"
               onmouseover="this.style.background='rgba(255,255,255,.16)'"
               onmouseout="this.style.background='rgba(255,255,255,.08)'">
                <i class="bi bi-arrow-left"></i> Back to List
            </a>
        </div>
    </div>

    <div class="container-fluid px-4 py-3">

    {{-- ═══ Header Fields ═══ --}}
    <div class="card mb-3" style="border-left:3px solid var(--primary) !important;">
        <div class="card-body py-2 px-3">
            <div class="row g-2 align-items-end">

                {{-- Supplier --}}
                <div class="col-xl-3 col-md-4">
                    <label class="form-label fw-600 mb-1" style="font-size:11px; text-transform:uppercase; letter-spacing:.04em; color:var(--text-muted);">
                        <i class="bi bi-building me-1" style="color:var(--primary);"></i>Supplier <span class="text-danger">*</span>
                    </label>
                    <div class="input-group input-group-sm">
                        <select class="form-select" wire:model.live="supplier_id" style="border-right:0;">
                            <option value="">-- Select Supplier --</option>
                            @foreach($suppliers as $supplier)
                                <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                            @endforeach
                        </select>
                        <button class="btn btn-outline-secondary" type="button" data-bs-toggle="modal" data-bs-target="#createSupplierModal" title="Add new supplier" style="border-left:0; padding:0 8px;">
                            <i class="bi bi-plus-lg" style="color:var(--success);"></i>
                        </button>
                    </div>
                </div>

                {{-- Invoice Number --}}
                <div class="col-xl-2 col-md-3">
                    <label class="form-label fw-600 mb-1" style="font-size:11px; text-transform:uppercase; letter-spacing:.04em; color:var(--text-muted);">
                        <i class="bi bi-receipt me-1" style="color:var(--info);"></i>Invoice No.
                    </label>
                    <input type="text" class="form-control form-control-sm fw-bold" wire:model="invoiceNumber" readonly
                           style="background:#f8fafc; font-family:monospace; color:var(--primary); letter-spacing:.02em;">
                </div>

                {{-- Purchase Date --}}
                <div class="col-xl-2 col-md-3">
                    <label class="form-label fw-600 mb-1" style="font-size:11px; text-transform:uppercase; letter-spacing:.04em; color:var(--text-muted);">
                        <i class="bi bi-calendar-event me-1" style="color:var(--warning);"></i>Purchase Date
                    </label>
                    <input type="date" class="form-control form-control-sm" wire:model="purchaseDate">
                </div>

                {{-- Transport Cost --}}
                <div class="col-xl-2 col-md-3">
                    <label class="form-label fw-600 mb-1" style="font-size:11px; text-transform:uppercase; letter-spacing:.04em; color:var(--text-muted);">
                        <i class="bi bi-truck me-1" style="color:var(--text-muted);"></i>Transport Cost
                    </label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text" style="font-size:11px; color:var(--text-muted); background:#f8fafc;">Rs</span>
                        <input type="number" step="0.01" min="0" class="form-control" wire:model="transportCost" placeholder="0.00">
                    </div>
                </div>

                {{-- Payment Type --}}
                <div class="col-xl-3 col-md-4">
                    <label class="form-label fw-600 mb-1" style="font-size:11px; text-transform:uppercase; letter-spacing:.04em; color:var(--text-muted);">
                        <i class="bi bi-credit-card me-1" style="color:var(--success);"></i>Payment Type
                    </label>
                    <select class="form-select form-select-sm" wire:model="paymentType">
                        <option value="cash">💵 Cash</option>
                        <option value="credit">🏦 Credit</option>
                    </select>
                </div>

            </div>
        </div>
    </div>

    {{-- ═══ Main Content: Left (Search + Create) & Right (Cart) ═══ --}}
    <div class="row g-3" style="align-items:flex-start;">

        {{-- ══════════════ LEFT SIDE (65%) ══════════════ --}}
        <div class="col-xl-8 col-lg-8">

            {{-- Search Bar --}}
            <div class="card mb-3" style="border-left:3px solid #0ea5e9 !important;">
                <div class="card-header py-2 px-3 d-flex align-items-center gap-2" style="background:#f8fafc; border-bottom:1px solid var(--border);">
                    <i class="bi bi-search" style="color:#0ea5e9;"></i>
                    <span class="fw-600" style="font-size:12px;">Product Search</span>
                    <span style="font-size:11px; color:var(--text-muted); margin-left:auto;">
                        Press <kbd style="font-size:10px; padding:1px 5px;color:black; background:#e9ecef; border:1px solid #5b5d5e; border-radius:3px;">F2</kbd> to focus
                    </span>
                </div>
                <div class="card-body py-2 px-3">
                    <div class="position-relative" x-ref="searchWrap">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text" style="background:#f8fafc;">
                                <i class="bi bi-upc-scan" style="color:var(--primary);"></i>
                            </span>
                            <input
                                type="text"
                                class="form-control"
                                wire:model.live.debounce.300ms="searchProduct"
                                placeholder="Type product name or code... (min 2 characters)"
                                x-ref="searchInput"
                                @keydown.arrow-down.prevent="focusSearchResult(0)"
                                @keydown.escape="$wire.set('products', [])"
                                id="productSearchInput"
                                style="border-left:0;"
                            >
                            <span wire:loading wire:target="searchProduct" class="input-group-text" style="background:#fff;">
                                <div class="spinner-border spinner-border-sm" style="color:var(--primary);" role="status"></div>
                            </span>
                        </div>

                        {{-- Search Results Dropdown --}}
                        @if(count($products) > 0)
                        <div class="position-absolute w-100 bg-white rounded-2 mt-1"
                             style="z-index:1055; max-height:320px; overflow-y:auto; box-shadow:0 8px 24px rgba(0,0,0,.12); border:1px solid var(--border);" x-ref="searchResults">
                            @foreach($products as $index => $product)
                                <div class="d-flex align-items-center px-3 py-2 search-result-item"
                                     style="cursor:pointer; border-bottom:1px solid var(--border);"
                                     tabindex="0"
                                     x-on:keydown.arrow-down.prevent="focusSearchResult({{ $index + 1 }})"
                                     x-on:keydown.arrow-up.prevent="focusSearchResult({{ $index - 1 }})"
                                     x-on:keydown.enter.prevent="selectResult({{ $index }})"
                                     x-on:keydown.escape.prevent="$wire.set('products', []); $refs.searchInput.focus()"
                                     @if($product['type'] === 'variant')
                                        wire:click="selectProductVariant({{ $product['product_id'] }}, '{{ $product['variant_value'] }}')"
                                     @else
                                        wire:click="selectSearchProduct({{ $index }})"
                                     @endif
                                     @mouseenter="this.focus()">
                                    <div class="me-3 flex-shrink-0">
                                        <img src="{{ asset('storage/' . ($product['image'] ?? 'images/product.jpg')) }}"
                                             class="rounded" style="width:38px; height:38px; object-fit:cover; border:1px solid var(--border);"
                                             onerror="this.src='{{ asset('images/product.jpg') }}'">
                                    </div>
                                    <div class="flex-grow-1" style="min-width:0;">
                                        <div class="fw-600" style="font-size:12px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                                            {{ $product['name'] }}
                                            @if($product['type'] === 'variant')
                                                <span class="badge ms-1" style="background:#0ea5e9; font-size:10px;">
                                                    {{ $product['variant_name'] }}: {{ $product['variant_value'] }}
                                                </span>
                                            @endif
                                        </div>
                                        <div style="font-size:11px; color:var(--text-muted); margin-top:2px;">
                                            <i class="bi bi-upc me-1"></i>{{ $product['code'] }}
                                            &nbsp;&bull;&nbsp;
                                            <i class="bi bi-box me-1"></i>Stock: {{ $product['available_stock'] }}
                                        </div>
                                    </div>
                                    <div class="text-end flex-shrink-0 ms-3">
                                        <div class="fw-700" style="font-size:12px; color:var(--success);">Rs {{ number_format($product['supplier_price'], 2) }}</div>
                                        <div style="font-size:10px; color:var(--text-muted);">Cost</div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Create / Edit Product Section --}}
            <div class="card" style="border-left:3px solid var(--warning) !important;">
                <div class="card-header py-2 px-3 d-flex justify-content-between align-items-center" style="background:#f8fafc; border-bottom:1px solid var(--border);">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-box-seam" style="color:var(--warning);"></i>
                        <span class="fw-600" style="font-size:12px;">
                            @if($isExistingProduct)
                                Edit Product Details
                                <span class="badge ms-2" style="background:var(--success); font-size:10px;">Existing</span>
                            @else
                                Create New Product
                                <span class="badge ms-2" style="background:var(--warning); color:#000; font-size:10px;">New</span>
                            @endif
                        </span>
                    </div>
                    @if($isExistingProduct)
                        <button class="btn btn-sm" style="font-size:11px; border:1px solid var(--border); background:#fff;"
                                wire:click="$set('isExistingProduct', false); $set('existingProductId', null); $set('selectedSearchProduct', null)">
                            <i class="bi bi-x-lg me-1"></i> Clear
                        </button>
                    @endif
                </div>
                <div class="card-body py-3 px-3">
                    <div class="row g-2">
                        {{-- Item Name --}}
                        <div class="col-md-4">
                            <label class="form-label fw-600 mb-1" style="font-size:11px; text-transform:uppercase; letter-spacing:.04em; color:var(--text-muted);">Item Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm" wire:model="newProductName" placeholder="Product name" x-ref="productNameInput">
                        </div>

                        {{-- Brand --}}
                        <div class="col-md-3">
                            <label class="form-label fw-600 mb-1" style="font-size:11px; text-transform:uppercase; letter-spacing:.04em; color:var(--text-muted);">Brand</label>
                            <div class="input-group input-group-sm">
                                <select class="form-select" wire:model="newProductBrand" style="border-right:0;">
                                    <option value="">-- Brand --</option>
                                    @foreach($brands as $brand)
                                        <option value="{{ $brand->id }}">{{ $brand->brand_name }}</option>
                                    @endforeach
                                </select>
                                <button class="btn btn-outline-secondary" type="button" data-bs-toggle="modal" data-bs-target="#createBrandModal" title="Add Brand" style="padding:0 8px; border-left:0;">
                                    <i class="bi bi-plus-lg" style="color:var(--success);"></i>
                                </button>
                            </div>
                        </div>

                        {{-- Category --}}
                        <div class="col-md-3">
                            <label class="form-label fw-600 mb-1" style="font-size:11px; text-transform:uppercase; letter-spacing:.04em; color:var(--text-muted);">Category</label>
                            <div class="input-group input-group-sm">
                                <select class="form-select" wire:model="newProductCategory" style="border-right:0;">
                                    <option value="">-- Category --</option>
                                    @foreach($categories as $cat)
                                        <option value="{{ $cat->id }}">{{ $cat->category_name }}</option>
                                    @endforeach
                                </select>
                                <button class="btn btn-outline-secondary" type="button" data-bs-toggle="modal" data-bs-target="#createCategoryModal" title="Add Category" style="padding:0 8px; border-left:0;">
                                    <i class="bi bi-plus-lg" style="color:var(--success);"></i>
                                </button>
                            </div>
                        </div>

                        {{-- Model --}}
                        <div class="col-md-2">
                            <label class="form-label fw-600 mb-1" style="font-size:11px; text-transform:uppercase; letter-spacing:.04em; color:var(--text-muted);">Model</label>
                            <div class="input-group input-group-sm">
                                <select class="form-select" wire:model="newProductModel" style="border-right:0;">
                                    <option value="">-- Model --</option>
                                    @foreach($models as $model)
                                        <option value="{{ $model->id }}">{{ $model->model_name }}</option>
                                    @endforeach
                                </select>
                                <button class="btn btn-outline-secondary" type="button" data-bs-toggle="modal" data-bs-target="#createModelModal" title="Add Model" style="padding:0 8px; border-left:0;">
                                    <i class="bi bi-plus-lg" style="color:var(--success);"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="my-2" style="border-top:1px dashed var(--border);"></div>

                    <div class="row g-2">
                        {{-- Cost Price --}}
                        <div class="col-md-2">
                            <label class="form-label fw-600 mb-1" style="font-size:11px; text-transform:uppercase; letter-spacing:.04em; color:var(--text-muted);">Cost Price <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0" class="form-control form-control-sm" wire:model="newProductCostPrice" placeholder="0.00"
                                   @change="$wire.calculateProductTotal()">
                        </div>

                        {{-- Wholesale Price --}}
                        <div class="col-md-2">
                            <label class="form-label fw-600 mb-1" style="font-size:11px; text-transform:uppercase; letter-spacing:.04em; color:var(--text-muted);">Wholesale</label>
                            <input type="number" step="0.01" min="0" class="form-control form-control-sm" wire:model="newProductWholesalePrice" placeholder="0.00">
                        </div>

                        {{-- Distributor Price --}}
                        <div class="col-md-2">
                            <label class="form-label fw-600 mb-1" style="font-size:11px; text-transform:uppercase; letter-spacing:.04em; color:var(--text-muted);">Distributor</label>
                            <input type="number" step="0.01" min="0" class="form-control form-control-sm" wire:model="newProductDistributorPrice" placeholder="0.00">
                        </div>

                        {{-- Retail Price --}}
                        <div class="col-md-2">
                            <label class="form-label fw-600 mb-1" style="font-size:11px; text-transform:uppercase; letter-spacing:.04em; color:var(--text-muted);">Retail Price</label>
                            <input type="number" step="0.01" min="0" class="form-control form-control-sm" wire:model="newProductRetailPrice" placeholder="0.00">
                        </div>

                        {{-- Fast Moving --}}
                        <div class="col-md-2">
                            <label class="form-label fw-600 mb-1" style="font-size:11px; text-transform:uppercase; letter-spacing:.04em; color:var(--text-muted);">Fast Moving</label>
                            <select class="form-select form-select-sm" wire:model="newProductFastMoving">
                                <option value="0">No</option>
                                <option value="1">Yes</option>
                            </select>
                        </div>

                        {{-- Min Stock --}}
                        <div class="col-md-2">
                            <label class="form-label fw-600 mb-1" style="font-size:11px; text-transform:uppercase; letter-spacing:.04em; color:var(--text-muted);">Min Stock</label>
                            <input type="number" min="0" class="form-control form-control-sm" wire:model="newProductMinStock" placeholder="5">
                        </div>
                    </div>

                    <div class="row g-2 mt-1">
                        {{-- Store Location --}}
                        <div class="col-md-3">
                            <label class="form-label fw-600 mb-1" style="font-size:11px; text-transform:uppercase; letter-spacing:.04em; color:var(--text-muted);">Store Location</label>
                            <input type="text" class="form-control form-control-sm" wire:model="newProductStoreLocation" placeholder="e.g. Warehouse A">
                        </div>

                        {{-- Rack Number --}}
                        <div class="col-md-2">
                            <label class="form-label fw-600 mb-1" style="font-size:11px; text-transform:uppercase; letter-spacing:.04em; color:var(--text-muted);">Rack No.</label>
                            <input type="text" class="form-control form-control-sm" wire:model="newProductRackNumber" placeholder="e.g. R-01">
                        </div>

                        {{-- Quantity --}}
                        <div class="col-md-2">
                            <label class="form-label fw-600 mb-1" style="font-size:11px; text-transform:uppercase; letter-spacing:.04em; color:var(--text-muted);">Qty <span class="text-danger">*</span></label>
                            <input type="number" min="1" class="form-control form-control-sm" wire:model="newProductQty" placeholder="1"
                                   x-ref="qtyInput"
                                   @change="$wire.calculateProductTotal()"
                                   @keydown.enter.prevent="$refs.freeQtyInput?.focus()">
                        </div>

                        {{-- Free Quantity --}}
                        <div class="col-md-2">
                            <label class="form-label fw-600 mb-1" style="font-size:11px; text-transform:uppercase; letter-spacing:.04em; color:var(--text-muted);">Free Qty</label>
                            <input type="number" min="0" class="form-control form-control-sm" wire:model="newProductFreeQty" placeholder="0"
                                   x-ref="freeQtyInput"
                                   @keydown.enter.prevent="$refs.addCartBtn?.click()">
                        </div>

                        {{-- Total Cost Display --}}
                        <div class="col-md-3 d-flex align-items-end">
                            <div class="w-100">
                                <label class="form-label fw-600 mb-1" style="font-size:11px; text-transform:uppercase; letter-spacing:.04em; color:var(--text-muted);">Total Cost</label>
                                <div class="form-control form-control-sm fw-700" style="background:#f0fdf4; color:var(--success); border-color:#bbf7d0;">
                                    Rs {{ number_format($newProductCalculatedTotal, 2) }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-3 d-flex align-items-center justify-content-between">
                        <span style="font-size:11px; color:var(--text-muted);">
                            <kbd style="font-size:10px; padding:1px 5px;color:black; background:#e9ecef; border:1px solid #ced4da; border-radius:3px;">Ctrl+Enter</kbd>
                            Add to cart
                        </span>
                        <button class="btn btn-primary btn-sm px-4" wire:click="addToCart" wire:loading.attr="disabled"
                                x-ref="addCartBtn">
                            <i class="bi bi-cart-plus me-1"></i>
                            @if($isExistingProduct)
                                Add to Cart
                            @else
                                Create &amp; Add to Cart
                            @endif
                            <span wire:loading wire:target="addToCart" class="spinner-border spinner-border-sm ms-1"></span>
                        </button>
                    </div>
                </div>
            </div>

        </div>

        {{-- ══════════════ RIGHT SIDE - CART (35%) ══════════════ --}}
        <div class="col-xl-4 col-lg-4">
            <div class="card" style="position:sticky; top:10px; border-top:3px solid var(--sidebar-bg) !important;">
                {{-- Cart Header --}}
                <div class="card-header py-2 px-3 d-flex justify-content-between align-items-center" style="background:var(--sidebar-bg);">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-cart3 text-white" style="font-size:1rem;"></i>
                        <span class="fw-700 text-white" style="font-size:13px;">Purchase Cart</span>
                        @if(count($cart) > 0)
                            <span class="badge" style="background:var(--primary); font-size:10px;">{{ count($cart) }}</span>
                        @endif
                    </div>
                    <span style="font-size:11px; color:rgba(255,255,255,.6);">{{ collect($cart)->sum('quantity') }} units</span>
                </div>

                {{-- Cart Items --}}
                <div class="card-body p-0" style="max-height:52vh; overflow-y:auto;">
                    @forelse($cart as $index => $item)
                        <div class="px-3 py-2 cart-item" style="border-bottom:1px solid var(--border);">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="flex-grow-1 me-2" style="min-width:0;">
                                    <div class="fw-600" style="font-size:12px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="{{ $item['name'] }}">
                                        {{ $item['name'] }}
                                    </div>
                                    @if(!empty($item['variant_value']))
                                        <span class="badge" style="background:#0ea5e9; font-size:10px;">{{ $item['variant_value'] }}</span>
                                    @endif
                                </div>
                                <button class="btn btn-sm p-0 px-1" style="border:1px solid #fecaca; color:#ef4444; background:#fff5f5;" wire:click="removeCartItem({{ $index }})" title="Remove">
                                    <i class="bi bi-trash" style="font-size:11px;"></i>
                                </button>
                            </div>
                            <div class="row g-1 mt-1">
                                <div class="col-3">
                                    <div style="font-size:10px; color:var(--text-muted); margin-bottom:2px;">Qty</div>
                                    <input type="number" min="1" class="form-control form-control-sm text-center"
                                           style="font-size:11px; padding:2px 4px;"
                                           wire:model.live="cart.{{ $index }}.quantity"
                                           @change="$wire.updateCartItemQty({{ $index }})">
                                </div>
                                <div class="col-3">
                                    <div style="font-size:10px; color:var(--text-muted); margin-bottom:2px;">Free Qty</div>
                                    <input type="number" min="0" class="form-control form-control-sm text-center"
                                           style="font-size:11px; padding:2px 4px;"
                                           wire:model.live="cart.{{ $index }}.free_qty">
                                </div>
                                <div class="col-3">
                                    <div style="font-size:10px; color:var(--text-muted); margin-bottom:2px;">Price</div>
                                    <input type="number" step="0.01" min="0" class="form-control form-control-sm text-center"
                                           style="font-size:11px; padding:2px 4px;"
                                           wire:model.live="cart.{{ $index }}.supplier_price"
                                           @change="$wire.updateCartItemPrice({{ $index }})">
                                </div>
                                <div class="col-3">
                                    <div style="font-size:10px; color:var(--text-muted); margin-bottom:2px;">Total</div>
                                    <div class="form-control form-control-sm text-center fw-700"
                                         style="font-size:11px; padding:2px 4px; background:#f0fdf4; color:var(--success); border-color:#bbf7d0;">
                                        {{ number_format($item['total_price'], 2) }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-5" style="color:var(--text-muted);">
                            <i class="bi bi-cart-x" style="font-size:2.5rem; color:var(--border);"></i>
                            <p class="mt-2 mb-1" style="font-size:12px; font-weight:600;">Cart is empty</p>
                            <p style="font-size:11px; color:var(--text-muted);">Search and add products above</p>
                        </div>
                    @endforelse
                </div>

                {{-- Cart Footer / Totals --}}
                <div class="card-footer py-3 px-3" style="background:#f8fafc; border-top:1px solid var(--border);">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span style="font-size:12px; color:var(--text-muted);">Subtotal:</span>
                        <span class="fw-600" style="font-size:12px;">Rs {{ number_format($grandTotal, 2) }}</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span style="font-size:12px; color:var(--text-muted);">Transport:</span>
                        <span class="fw-600" style="font-size:12px;">Rs {{ number_format(floatval($transportCost), 2) }}</span>
                    </div>
                    <div style="border-top:2px solid var(--border); padding-top:8px;" class="d-flex justify-content-between align-items-center mb-3">
                        <span class="fw-700" style="font-size:13px;">Grand Total:</span>
                        <span class="fw-700" style="font-size:18px; color:var(--primary);">Rs {{ number_format($grandTotal + floatval($transportCost), 2) }}</span>
                    </div>

                    <div class="mb-2">
                        <span style="font-size:11px; color:var(--text-muted);">
                            <kbd style="font-size:10px; padding:1px 5px; color:black; background:#e9ecef; border:1px solid #ced4da; border-radius:3px;">F9</kbd>
                            Save order
                        </span>
                    </div>

                    <button class="btn w-100 fw-700"
                            style="background:var(--success); color:#fff; border:none; font-size:13px; padding:10px;"
                            wire:click="savePurchaseOrder"
                            wire:loading.attr="disabled"
                            @if(empty($cart)) disabled @endif>
                        <i class="bi bi-check-circle me-1"></i> Save Purchase Order
                        <span wire:loading wire:target="savePurchaseOrder" class="spinner-border spinner-border-sm ms-1"></span>
                    </button>
                </div>
            </div>
        </div>

    </div>

    {{-- ═══ MODALS ═══ --}}

    {{-- Create Supplier Modal --}}
    <div wire:ignore.self class="modal fade" id="createSupplierModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content" style="border-radius:10px; border:none; box-shadow:0 20px 60px rgba(0,0,0,.15);">
                <div class="modal-header py-3 px-4" style="border-bottom:1px solid var(--border); background:#f8fafc;">
                    <h6 class="modal-title fw-700" style="font-size:13px;">
                        <i class="bi bi-building-add me-2" style="color:var(--primary);"></i>New Supplier
                    </h6>
                    <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body px-4 py-3">
                    <div class="row g-2">
                        <div class="col-md-6">
                            <label class="form-label fw-600 mb-1" style="font-size:11px; text-transform:uppercase; letter-spacing:.04em; color:var(--text-muted);">Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm" wire:model="newSupplierName" placeholder="Supplier name">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-600 mb-1" style="font-size:11px; text-transform:uppercase; letter-spacing:.04em; color:var(--text-muted);">Business Name</label>
                            <input type="text" class="form-control form-control-sm" wire:model="newSupplierBusinessName" placeholder="Business name">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-600 mb-1" style="font-size:11px; text-transform:uppercase; letter-spacing:.04em; color:var(--text-muted);">Phone</label>
                            <input type="text" class="form-control form-control-sm" wire:model="newSupplierPhone" placeholder="Phone">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-600 mb-1" style="font-size:11px; text-transform:uppercase; letter-spacing:.04em; color:var(--text-muted);">Email</label>
                            <input type="email" class="form-control form-control-sm" wire:model="newSupplierEmail" placeholder="Email">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-600 mb-1" style="font-size:11px; text-transform:uppercase; letter-spacing:.04em; color:var(--text-muted);">Address</label>
                            <input type="text" class="form-control form-control-sm" wire:model="newSupplierAddress" placeholder="Address">
                        </div>
                    </div>
                </div>
                <div class="modal-footer py-2 px-4" style="border-top:1px solid var(--border);">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-sm btn-primary" wire:click="createSupplier">
                        <i class="bi bi-check-lg me-1"></i> Create Supplier
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Create Brand Modal --}}
    <div wire:ignore.self class="modal fade" id="createBrandModal" tabindex="-1">
        <div class="modal-dialog modal-sm">
            <div class="modal-content" style="border-radius:10px; border:none; box-shadow:0 20px 60px rgba(0,0,0,.15);">
                <div class="modal-header py-3 px-4" style="border-bottom:1px solid var(--border); background:#f8fafc;">
                    <h6 class="modal-title fw-700" style="font-size:13px;">
                        <i class="bi bi-tag-fill me-2" style="color:var(--primary);"></i>New Brand
                    </h6>
                    <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body px-4 py-3">
                    <label class="form-label fw-600 mb-1" style="font-size:11px; text-transform:uppercase; letter-spacing:.04em; color:var(--text-muted);">Brand Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control form-control-sm" wire:model="newBrandName" placeholder="Enter brand name"
                           @keydown.enter.prevent="$wire.createBrand()">
                </div>
                <div class="modal-footer py-2 px-4" style="border-top:1px solid var(--border);">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-sm btn-primary" wire:click="createBrand">
                        <i class="bi bi-check-lg me-1"></i> Create
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Create Category Modal --}}
    <div wire:ignore.self class="modal fade" id="createCategoryModal" tabindex="-1">
        <div class="modal-dialog modal-sm">
            <div class="modal-content" style="border-radius:10px; border:none; box-shadow:0 20px 60px rgba(0,0,0,.15);">
                <div class="modal-header py-3 px-4" style="border-bottom:1px solid var(--border); background:#f8fafc;">
                    <h6 class="modal-title fw-700" style="font-size:13px;">
                        <i class="bi bi-folder-fill me-2" style="color:var(--primary);"></i>New Category
                    </h6>
                    <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body px-4 py-3">
                    <label class="form-label fw-600 mb-1" style="font-size:11px; text-transform:uppercase; letter-spacing:.04em; color:var(--text-muted);">Category Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control form-control-sm" wire:model="newCategoryName" placeholder="Enter category name"
                           @keydown.enter.prevent="$wire.createCategory()">
                </div>
                <div class="modal-footer py-2 px-4" style="border-top:1px solid var(--border);">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-sm btn-primary" wire:click="createCategory">
                        <i class="bi bi-check-lg me-1"></i> Create
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Create Model Modal --}}
    <div wire:ignore.self class="modal fade" id="createModelModal" tabindex="-1">
        <div class="modal-dialog modal-sm">
            <div class="modal-content" style="border-radius:10px; border:none; box-shadow:0 20px 60px rgba(0,0,0,.15);">
                <div class="modal-header py-3 px-4" style="border-bottom:1px solid var(--border); background:#f8fafc;">
                    <h6 class="modal-title fw-700" style="font-size:13px;">
                        <i class="bi bi-gear-fill me-2" style="color:var(--primary);"></i>New Model
                    </h6>
                    <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body px-4 py-3">
                    <label class="form-label fw-600 mb-1" style="font-size:11px; text-transform:uppercase; letter-spacing:.04em; color:var(--text-muted);">Model Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control form-control-sm" wire:model="newModelName" placeholder="Enter model name"
                           @keydown.enter.prevent="$wire.createModel()">
                </div>
                <div class="modal-footer py-2 px-4" style="border-top:1px solid var(--border);">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-sm btn-primary" wire:click="createModel">
                        <i class="bi bi-check-lg me-1"></i> Create
                    </button>
                </div>
            </div>
        </div>
    </div>

    </div>{{-- /container-fluid --}}

</div>{{-- /x-data --}}

@push('styles')
<style>
    .search-result-item:hover,
    .search-result-item:focus {
        background: #fff5f5;
        border-left: 3px solid var(--primary);
        padding-left: calc(0.75rem - 3px) !important;
        outline: none;
    }
    .cart-item:hover {
        background: #fafafa;
    }
    .fw-600 { font-weight: 600 !important; }
    .fw-700 { font-weight: 700 !important; }
    .card {
        border-radius: 8px;
        border: 1px solid var(--border) !important;
        box-shadow: 0 1px 4px rgba(0,0,0,.06) !important;
    }
    .form-control, .form-select {
        font-size: 12px !important;
        border-radius: 6px;
        border-color: var(--border);
    }
    .form-control:focus, .form-select:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(225,29,72,.1);
    }
    .input-group-text {
        font-size: 12px;
        border-color: var(--border);
    }
    .modal-content {
        border-radius: 10px !important;
        border: none !important;
        box-shadow: 0 20px 60px rgba(0,0,0,.15) !important;
    }
</style>
@endpush

@push('scripts')
<script>
    function purchaseCreateApp() {
        return {
            init() {
                // Focus search input on page load
                this.$nextTick(() => {
                    this.$refs.searchInput?.focus();
                });

                // Re-focus search after item added
                Livewire.on('item-added-to-cart', () => {
                    this.$nextTick(() => {
                        this.$refs.searchInput?.focus();
                    });
                });

                // Focus qty input after product selected
                Livewire.on('product-selected', () => {
                    this.$nextTick(() => {
                        this.$refs.qtyInput?.focus();
                        this.$refs.qtyInput?.select();
                    });
                });
            },

            handleKeyboard(e) {
                // F2 = Focus search
                if (e.key === 'F2') {
                    e.preventDefault();
                    this.$refs.searchInput?.focus();
                }

                // F9 = Save order
                if (e.key === 'F9') {
                    e.preventDefault();
                    this.$wire.savePurchaseOrder();
                }

                // Ctrl+Enter = Add to cart
                if (e.ctrlKey && e.key === 'Enter') {
                    e.preventDefault();
                    this.$wire.addToCart();
                }

                // Escape = Clear search results
                if (e.key === 'Escape') {
                    this.$wire.set('products', []);
                }
            },

            focusSearchResult(index) {
                const items = this.$refs.searchResults?.querySelectorAll('.search-result-item');
                if (!items || items.length === 0) return;
                const clampedIndex = Math.max(0, Math.min(index, items.length - 1));
                items[clampedIndex]?.focus();
            },

            selectResult(index) {
                const items = this.$refs.searchResults?.querySelectorAll('.search-result-item');
                if (items && items[index]) {
                    items[index].click();
                }
            }
        };
    }
</script>
@endpush
