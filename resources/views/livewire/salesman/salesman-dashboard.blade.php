<div class="container-fluid py-3">
    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold text-dark mb-2">
                <i class="bi bi-speedometer2 text-primary me-2"></i> Salesman Dashboard
            </h3>
            <p class="text-muted mb-0">Welcome back, {{ auth()->user()->name }}!</p>
        </div>
    </div>

    {{-- Sales Stats Cards --}}
    <div class="row g-4 mb-4">
        <div class="col-md-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <p class="text-muted small mb-1">Total Orders</p>
                            <h3 class="fw-bold text-dark mb-0">{{ $totalSales }}</h3>
                        </div>
                        <div class="bg-primary bg-opacity-10 rounded-circle p-3">
                            <i class="bi bi-cart-fill text-primary fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <p class="text-muted small mb-1">Pending Approval</p>
                            <h3 class="fw-bold text-warning mb-0">{{ $pendingSales }}</h3>
                        </div>
                        <div class="bg-warning bg-opacity-10 rounded-circle p-3">
                            <i class="bi bi-hourglass-split text-warning fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <p class="text-muted small mb-1">Approved</p>
                            <h3 class="fw-bold text-success mb-0">{{ $approvedSales }}</h3>
                        </div>
                        <div class="bg-success bg-opacity-10 rounded-circle p-3">
                            <i class="bi bi-check-circle-fill text-success fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <p class="text-muted small mb-1">Rejected</p>
                            <h3 class="fw-bold text-danger mb-0">{{ $rejectedSales }}</h3>
                        </div>
                        <div class="bg-danger bg-opacity-10 rounded-circle p-3">
                            <i class="bi bi-x-circle-fill text-danger fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Delivery Stats Cards --}}
    <div class="row g-4 mb-4">
        <div class="col-12">
            <h5 class="fw-bold text-dark mb-3">
                <i class="bi bi-truck text-info me-2"></i> Delivery Status
            </h5>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100 bg-warning bg-opacity-10">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <p class="text-muted small mb-1">Pending Delivery</p>
                            <h3 class="fw-bold text-warning mb-0">{{ $pendingDeliveries }}</h3>
                        </div>
                        <div class="bg-warning bg-opacity-25 rounded-circle p-3">
                            <i class="bi bi-box-seam text-warning fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100 bg-info bg-opacity-10">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <p class="text-muted small mb-1">In Transit</p>
                            <h3 class="fw-bold text-info mb-0">{{ $inTransitDeliveries }}</h3>
                        </div>
                        <div class="bg-info bg-opacity-25 rounded-circle p-3">
                            <i class="bi bi-truck text-info fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100 bg-success bg-opacity-10">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <p class="text-muted small mb-1">Delivered</p>
                            <h3 class="fw-bold text-success mb-0">{{ $completedDeliveries }}</h3>
                        </div>
                        <div class="bg-success bg-opacity-25 rounded-circle p-3">
                            <i class="bi bi-check2-all text-success fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Customer Dues Stats --}}
    <div class="row g-4 mb-4">
        <div class="col-12">
            <h5 class="fw-bold text-dark mb-3">
                <i class="bi bi-wallet2 text-danger me-2"></i> Customer Dues
            </h5>
        </div>
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100 bg-danger bg-opacity-10">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <p class="text-muted small mb-1">Total Due Amount</p>
                            <h3 class="fw-bold text-danger mb-0">Rs. {{ number_format($totalDueAmount, 2) }}</h3>
                        </div>
                        <div class="bg-danger bg-opacity-25 rounded-circle p-3">
                            <i class="bi bi-cash-stack text-danger fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <p class="text-muted small mb-1">Customers with Dues</p>
                            <h3 class="fw-bold text-primary mb-0">{{ $customersWithDues }}</h3>
                        </div>
                        <div class="bg-primary bg-opacity-10 rounded-circle p-3">
                            <i class="bi bi-people text-primary fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Recent Sales --}}
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3">
            <h5 class="fw-bold text-dark mb-0">
                <i class="bi bi-clock-history text-primary me-2"></i> Recent Orders
            </h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Invoice</th>
                            <th>Customer</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentSales as $sale)
                        <tr>
                            <td class="ps-4">
                                <span class="fw-medium">{{ $sale->invoice_number }}</span>
                            </td>
                            <td>{{ $sale->customer->name ?? 'N/A' }}</td>
                            <td class="fw-semibold">Rs. {{ number_format($sale->total_amount, 2) }}</td>
                            <td>
                                @if($sale->status === 'pending')
                                    <span class="badge bg-warning">Pending</span>
                                @elseif($sale->status === 'confirm')
                                    <span class="badge bg-success">Approved</span>
                                @else
                                    <span class="badge bg-danger">Rejected</span>
                                @endif
                            </td>
                            <td class="text-muted">{{ $sale->created_at->format('M d, Y') }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="text-center py-4 text-muted">
                                <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                No sales yet. Start creating orders!
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
