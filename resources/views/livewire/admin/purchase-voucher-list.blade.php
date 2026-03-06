<div>
    {{-- ═══ BUSY-Style Header Bar ═══ --}}
    <div class="d-flex align-items-center justify-content-between px-3 py-2" style="background:linear-gradient(135deg, #0f172a 0%, #1e293b 100%); border-bottom:2px solid var(--primary);">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-journal-text text-white" style="font-size:1.2rem;"></i>
            <h5 class="fw-bold text-white mb-0" style="font-size:15px;">Purchase Voucher List</h5>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('admin.purchase-create') }}" class="btn btn-sm btn-outline-light" style="font-size:11px;">
                <i class="bi bi-plus-circle me-1"></i> Add New <kbd class="ms-1" style="font-size:9px;">Alt+A</kbd>
            </a>
            <a href="{{ route('admin.purchase-voucher-modify') }}" class="btn btn-sm btn-outline-light" style="font-size:11px;">
                <i class="bi bi-pencil-square me-1"></i> Modify <kbd class="ms-1" style="font-size:9px;">Alt+M</kbd>
            </a>
        </div>
    </div>

    <div class="container-fluid px-3 py-2">

        {{-- ═══ Filter Bar ═══ --}}
        <div class="card mb-2" style="border-left:3px solid #0ea5e9;">
            <div class="card-body py-2 px-3">
                <div class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label mb-1 fw-600" style="font-size:10px; text-transform:uppercase; letter-spacing:.04em; color:var(--text-muted);">
                            <i class="bi bi-search me-1"></i>Search
                        </label>
                        <input type="text" class="form-control form-control-sm" wire:model.live.debounce.400ms="search"
                               placeholder="Voucher no, invoice, supplier...">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label mb-1 fw-600" style="font-size:10px; text-transform:uppercase; letter-spacing:.04em; color:var(--text-muted);">
                            <i class="bi bi-calendar me-1"></i>From
                        </label>
                        <input type="date" class="form-control form-control-sm" wire:model.live="dateFrom">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label mb-1 fw-600" style="font-size:10px; text-transform:uppercase; letter-spacing:.04em; color:var(--text-muted);">
                            <i class="bi bi-calendar me-1"></i>To
                        </label>
                        <input type="date" class="form-control form-control-sm" wire:model.live="dateTo">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label mb-1 fw-600" style="font-size:10px; text-transform:uppercase; letter-spacing:.04em; color:var(--text-muted);">Supplier</label>
                        <select class="form-select form-select-sm" wire:model.live="supplierFilter">
                            <option value="">All Suppliers</option>
                            @foreach($suppliers as $s)
                                <option value="{{ $s->id }}">{{ $s->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-1">
                        <label class="form-label mb-1 fw-600" style="font-size:10px; text-transform:uppercase; letter-spacing:.04em; color:var(--text-muted);">Type</label>
                        <select class="form-select form-select-sm" wire:model.live="paymentTypeFilter">
                            <option value="">All</option>
                            <option value="cash">Cash</option>
                            <option value="credit">Credit</option>
                        </select>
                    </div>
                    <div class="col-md-1">
                        <label class="form-label mb-1 fw-600" style="font-size:10px; text-transform:uppercase; letter-spacing:.04em; color:var(--text-muted);">Status</label>
                        <select class="form-select form-select-sm" wire:model.live="statusFilter">
                            <option value="">All</option>
                            <option value="pending">Pending</option>
                            <option value="complete">Complete</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    <div class="col-md-1">
                        <button class="btn btn-outline-secondary btn-sm w-100" wire:click="clearFilters" title="Reset Filters">
                            <i class="bi bi-arrow-counterclockwise"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        {{-- ═══ Summary Cards ═══ --}}
        @php
            $totalAmount = $vouchers->sum('total_amount');
            $cashCount = $vouchers->where('payment_type', 'cash')->count();
            $creditCount = $vouchers->where('payment_type', 'credit')->count();
        @endphp
        <div class="row g-2 mb-2">
            <div class="col-md-3">
                <div class="card h-100" style="border-left:3px solid var(--primary);">
                    <div class="card-body py-2 px-3">
                        <p class="text-muted mb-0" style="font-size:10px; text-transform:uppercase;">Total Vouchers</p>
                        <h5 class="fw-800 mb-0" style="color:var(--primary);">{{ $vouchers->total() }}</h5>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card h-100" style="border-left:3px solid var(--success);">
                    <div class="card-body py-2 px-3">
                        <p class="text-muted mb-0" style="font-size:10px; text-transform:uppercase;">Total Amount</p>
                        <h5 class="fw-800 mb-0" style="color:var(--success);">Rs {{ number_format($totalAmount, 2) }}</h5>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card h-100" style="border-left:3px solid var(--info);">
                    <div class="card-body py-2 px-3">
                        <p class="text-muted mb-0" style="font-size:10px; text-transform:uppercase;">Cash Purchases</p>
                        <h5 class="fw-800 mb-0" style="color:var(--info);">{{ $cashCount }}</h5>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card h-100" style="border-left:3px solid var(--warning);">
                    <div class="card-body py-2 px-3">
                        <p class="text-muted mb-0" style="font-size:10px; text-transform:uppercase;">Credit Purchases</p>
                        <h5 class="fw-800 mb-0" style="color:var(--warning);">{{ $creditCount }}</h5>
                    </div>
                </div>
            </div>
        </div>

        {{-- ═══ Data Table ═══ --}}
        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0" style="font-size:12px;">
                        <thead style="background:#f1f5f9;">
                            <tr>
                                <th style="width:30px;" class="text-center">#</th>
                                <th>Voucher No.</th>
                                <th>Invoice</th>
                                <th>Date</th>
                                <th>Supplier</th>
                                <th class="text-center">Items</th>
                                <th class="text-end">Total (Rs)</th>
                                <th class="text-center">Type</th>
                                <th class="text-center">Status</th>
                                <th class="text-center" style="width:130px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($vouchers as $i => $voucher)
                                <tr>
                                    <td class="text-center text-muted">{{ $vouchers->firstItem() + $i }}</td>
                                    <td>
                                        <span class="fw-700" style="font-family:monospace; color:var(--primary);">{{ $voucher->order_code }}</span>
                                    </td>
                                    <td>
                                        <span style="font-family:monospace;">{{ $voucher->invoice_number ?: '-' }}</span>
                                    </td>
                                    <td>{{ \Carbon\Carbon::parse($voucher->order_date)->format('d M Y') }}</td>
                                    <td>
                                        <span class="fw-600">{{ $voucher->supplier->name ?? 'N/A' }}</span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-secondary" style="font-size:10px;">{{ $voucher->items_count ?? $voucher->items->count() }}</span>
                                    </td>
                                    <td class="text-end fw-700" style="color:var(--success);">
                                        {{ number_format($voucher->total_amount, 2) }}
                                    </td>
                                    <td class="text-center">
                                        <span class="badge {{ $voucher->payment_type === 'cash' ? 'bg-info' : 'bg-warning text-dark' }}" style="font-size:10px;">
                                            {{ ucfirst($voucher->payment_type ?? 'N/A') }}
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge {{ $voucher->status === 'complete' ? 'bg-success' : ($voucher->status === 'pending' ? 'bg-warning text-dark' : 'bg-secondary') }}" style="font-size:10px;">
                                            {{ ucfirst($voucher->status) }}
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-outline-primary px-2" title="Modify" wire:click="modifyVoucher({{ $voucher->id }})" style="font-size:11px;">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button class="btn btn-outline-info px-2" title="Print" wire:click="printVoucher({{ $voucher->id }})" style="font-size:11px;">
                                                <i class="bi bi-printer"></i>
                                            </button>
                                            <button class="btn btn-outline-danger px-2" title="Delete"
                                                    style="font-size:11px;"
                                                    onclick="Swal.fire({title:'Delete this purchase voucher?',text:'Stock and accounting entries will be reversed.',icon:'warning',showCancelButton:true,confirmButtonColor:'#e11d48',confirmButtonText:'Yes, delete'}).then(r=>{if(r.isConfirmed)@this.deleteVoucher({{ $voucher->id }})})">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="text-center py-4 text-muted">
                                        <i class="bi bi-inbox" style="font-size:2rem;"></i>
                                        <p class="mt-1 mb-0" style="font-size:12px;">No purchase vouchers found</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if($vouchers->hasPages())
                <div class="card-footer py-2 px-3" style="background:#f8fafc;">
                    {{ $vouchers->links() }}
                </div>
            @endif
        </div>
    </div>

    {{-- Status Bar --}}
    <div class="px-3 py-1 d-flex justify-content-between align-items-center" style="background:#f1f5f9; border-top:1px solid var(--border); font-size:11px; color:var(--text-muted);">
        <div>
            <kbd>Alt+A</kbd> Add &nbsp;|&nbsp;
            <kbd>Alt+M</kbd> Modify &nbsp;|&nbsp;
            <kbd>Alt+L</kbd> List
        </div>
        <div>Purchase Voucher — List View</div>
    </div>

    <style>
        kbd { background: #374151; color: #fff; padding: 1px 4px; border-radius: 3px; font-size: 10px; }
    </style>
</div>
