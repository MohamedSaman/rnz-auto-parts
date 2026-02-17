<div class="container-fluid py-3">
    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-5">
        <div>
            <h3 class="fw-bold text-dark mb-2">
                <i class="bi bi-journal-check text-success me-2"></i> Cheque Management
            </h3>
            <p class="text-muted mb-0">View and manage all customer cheques</p>
        </div>

    </div>

    {{-- Statistics Cards --}}
    <div class="row mb-5">
        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card border-start border-warning border-4 shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs fw-bold text-warning text-uppercase mb-1">Pending Cheques</div>
                            <div class="h5 mb-0 fw-bold text-gray-800">{{ $pendingCount ?? 0 }}</div>
                        </div>
                        <div class="col-auto"><i class="bi bi-clock-history fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card border-start border-success border-4 shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs fw-bold text-success text-uppercase mb-1">Completed Cheques</div>
                            <div class="h5 mb-0 fw-bold text-gray-800">{{ $completeCount ?? 0 }}</div>
                        </div>
                        <div class="col-auto"><i class="bi bi-check2-circle fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card border-start border-danger border-4 shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs fw-bold text-danger text-uppercase mb-1">Overdue Cheques</div>
                            <div class="h5 mb-0 fw-bold text-gray-800">{{ $overdueCount ?? 0 }}</div>
                        </div>
                        <div class="col-auto"><i class="bi bi-exclamation-triangle fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Cheque Table --}}
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <h5 class="fw-bold mb-0">
                    <i class="bi bi-list-ul text-primary me-2"></i> Cheque List
                </h5>
                <span class="badge bg-primary">{{ $cheques->total() ?? 0 }} records</span>

            </div>
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3" style="width: 60%; margin: auto">
                <!-- 🔍 Search Bar -->
                <div class="search-bar flex-grow-1">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0">
                            <i class="bi bi-search text-muted"></i>
                        </span>
                        <input type="text" class="form-control border-start-0" wire:model.live="search"
                            placeholder="Search by cheque number or customer name...">
                    </div>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <label class="text-sm text-muted fw-medium">Filter</label>
                <select wire:model.live="statusFilter" class="form-select form-select-sm" style="width: 130px;">
                    <option value="all">All Statuses</option>
                    <option value="pending">Pending</option>
                    <option value="complete">Complete</option>
                    <option value="overdue">Overdue</option>
                    <option value="return">Return</option>
                </select>

                <label class="text-sm text-muted fw-medium">Show</label>
                <select wire:model.live="perPage" class="form-select form-select-sm" style="width: 80px;">
                    <option value="10">10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                    <option value="200">200</option>
                    <option value="500">500</option>
                </select>
                <span class="text-sm text-muted">entries</span>
            </div>
        </div>
        <div class="card-body p-0 overflow-auto">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Cheque No</th>
                            <th>Customer</th>
                            <th class="text-center">Bank</th>
                            <th class="text-center">Amount</th>
                            <th class="text-center">Date</th>
                            <th class="text-center">Status</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($cheques as $cheque)
                        <tr wire:key="cheque-{{ $cheque->id }}">
                            <td class="ps-4">{{ $cheque->cheque_number }}</td>
                            <td>{{ $cheque->customer->name ?? '-' }}</td>
                            <td class="text-center">{{ $cheque->bank_name }}</td>
                            <td class="text-center">Rs.{{ number_format($cheque->cheque_amount, 2) }}</td>
                            <td class="text-center">{{ $cheque->cheque_date ? date('M d, Y', strtotime($cheque->cheque_date)) : '-' }}</td>
                            <td class="text-center">
                                <span class="badge bg-{{ $cheque->status == 'pending' ? 'warning' : ($cheque->status == 'complete' ? 'success' : ($cheque->status == 'return' ? 'danger' : 'secondary')) }}">
                                    {{ ucfirst($cheque->status) }}
                                </span>
                            </td>
                            <td class="text-end pe-4">
                                @if($cheque->status == 'pending' || $cheque->status == 'overdue')
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle"
                                        type="button"
                                        data-bs-toggle="dropdown"
                                        aria-expanded="false">
                                        <i class="bi bi-gear-fill"></i> Actions
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <!-- Mark as Complete -->
                                        <li>
                                            <button class="dropdown-item"
                                                wire:click="confirmComplete({{ $cheque->id }})">
                                                <i class="bi bi-check2-circle text-success me-2"></i>
                                                Complete
                                            </button>
                                        </li>
                                        <!-- Return Cheque -->
                                        <li>
                                            <button class="dropdown-item"
                                                wire:click="confirmReturn({{ $cheque->id }})">
                                                <i class="bi bi-arrow-counterclockwise text-danger me-2"></i>
                                                Return
                                            </button>
                                        </li>
                                    </ul>
                                </div>
                                @else
                                <span class="text-muted">-</span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">
                                <i class="bi bi-x-circle display-4 d-block mb-2"></i>
                                No cheques found.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            @if ($cheques->hasPages())
            <div class="card-footer">
                <div class="d-flex justify-content-center">
                    {{ $cheques->links('livewire.custom-pagination') }}
                </div>
            </div>
            @endif
        </div>
    </div>
</div>