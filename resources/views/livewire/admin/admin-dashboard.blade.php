<div>
    @push('styles')
    <style>
        /* Refined Dashboard Styles */
        .stat-card {
            position: relative;
            overflow: hidden;
        }

        .stat-card .icon-bg {
            position: absolute;
            right: -20px;
            bottom: -20px;
            font-size: 8rem;
            opacity: 0.05;
            transform: rotate(-15deg);
            pointer-events: none;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-main);
            letter-spacing: -0.02em;
        }

        .stat-label {
            color: var(--text-muted);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.05em;
        }

        .chart-card {
            background: white;
            border-radius: var(--radius-lg);
            border: 1px solid var(--border);
            box-shadow: var(--shadow-sm);
        }

        .chart-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border);
        }

        .widget-container {
            background: white;
            border-radius: var(--radius-lg);
            border: 1px solid var(--border);
            box-shadow: var(--shadow-sm);
            padding: 1.5rem;
        }

        .inventory-item {
            padding: 12px;
            border-radius: 12px;
            transition: background 0.2s;
            border: 1px solid transparent;
        }

        .inventory-item:hover {
            background: var(--border-light);
            border-color: var(--border);
        }

        .status-badge {
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .in-stock { background: #ecfdf5; color: #065f46; }
        .low-stock { background: #fffbeb; color: #92400e; }
        .out-of-stock { background: #fef2f2; color: #991b1b; }

        .progress {
            height: 6px;
            border-radius: 3px;
            background: var(--border-light);
        }
    </style>
    @endpush

    <!-- Overview Content -->
    <div class="container-fluid p-0">
        <!-- Header Section -->
        <div class="d-flex justify-content-between align-items-center mb-2">
            <div>
                <h3 class="fw-bold text-dark mb-2">
                    <i class="bi bi-speedometer2 text-success me-2"></i> Overview
                </h3>
                <p class="text-muted mb-0">Get a complete view of your product performance and stock activity.</p>
            </div>
        </div>
        <!-- Stats Cards Row - Updated to 4 cards -->
        <div class="row mb-4">
            <!-- Card 1: Total Sales and Revenue -->
            <div class="col-sm-6 col-lg-3 mb-3">
                <div class="stat-card">
                    <i class="bi bi-cash-stack icon-bg"></i>
                    <div class="stat-label mb-2">Total Sales & Revenue</div>
                    <div class="stat-value mb-3">Rs.{{ number_format($totalSales, 0) }}</div>
                    
                    <div class="progress mb-2">
                        <div class="progress-bar bg-success" style="width: {{ $revenuePercentage }}%;"></div>
                    </div>
                    
                    <div class="d-flex justify-content-between align-items-center">
                        <small class="text-muted">Revenue: {{ $revenuePercentage }}%</small>
                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1">
                            Rs.{{ number_format($totalRevenue, 0) }}
                        </span>
                    </div>
                </div>
            </div>
            
            <!-- Card 2: Total Payment and Due Payment -->
            <div class="col-sm-6 col-lg-3 mb-3">
                <div class="stat-card">
                    <i class="bi bi-wallet2 icon-bg"></i>
                    <div class="stat-label mb-2">Payment & Due</div>
                    <div class="stat-value mb-3">Rs.{{ number_format($totalPaidAmount, 0) }}</div>
                    
                    <div class="progress mb-2">
                        <div class="progress-bar bg-primary" style="width: {{ $revenuePercentage }}%;"></div>
                    </div>
                    
                    <div class="d-flex justify-content-between align-items-center">
                        <small class="text-muted">Due: {{ $duePercentage }}%</small>
                        <span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25 px-2 py-1">
                            Rs.{{ number_format($totalDueAmount, 0) }}
                        </span>
                    </div>
                </div>
            </div>
            
            <!-- Card 3: Total Stocks and Available Stocks -->
            <div class="col-sm-6 col-lg-3 mb-3">
                <div class="stat-card">
                    <i class="bi bi-box-seam icon-bg"></i>
                    <div class="stat-label mb-2">Stocks & Available</div>
                    <div class="stat-value mb-3">{{ number_format($totalStock) }} <span class="fs-6 text-muted fw-normal">units</span></div>
                    
                    <div class="progress mb-2">
                        <div class="progress-bar bg-info" style="width: {{ $availablePercentage }}%;"></div>
                    </div>
                    
                    <div class="d-flex justify-content-between align-items-center">
                        <small class="text-muted">Available: {{ $availablePercentage }}%</small>
                        <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25 px-2 py-1">
                            {{ number_format($availableStock) }} units
                        </span>
                    </div>
                </div>
            </div>
            
            <!-- Card 4: Staff Sale and Due Amount -->
            <div class="col-sm-6 col-lg-3 mb-3">
                <div class="stat-card">
                    <i class="bi bi-people icon-bg"></i>
                    <div class="stat-label mb-2">Staff Sales & Due</div>
                    <div class="stat-value mb-3">Rs.{{ number_format($totalStaffSalesValue, 0) }}</div>
                    
                    @php
                        $staffDuePercentage = $totalStaffSalesValue > 0 ? round(($totalStaffDueAmount / $totalStaffSalesValue) * 100, 1) : 0;
                        $staffPaidPercentage = 100 - $staffDuePercentage;
                    @endphp
                    
                    <div class="progress mb-2">
                        <div class="progress-bar bg-danger" style="width: {{ $staffDuePercentage }}%;"></div>
                    </div>
                    
                    <div class="d-flex justify-content-between align-items-center">
                        <small class="text-muted">Due: {{ $staffDuePercentage }}%</small>
                        <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-2 py-1">
                            Rs.{{ number_format($totalStaffDueAmount, 0) }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Equal Size Cards Section -->
        <div class="row">
            <!-- Sales Overview By Daily Trend Card -->
            <div class="col-lg-6 col-md-12 mb-4">
                <div class="chart-card">
                    <div class="chart-header d-flex justify-content-between align-items-center flex-wrap">
                        <div class="mb-mobile-2">
                            <h6 class="mb-1">Daily Sales Trend</h6>
                            <p class="text-muted mb-0 small">Sales performance over the last 7 days</p>
                        </div>
                       
                    </div>
                    <!-- Add scrollable wrapper for the chart -->
                    <div class="chart-scroll-container">
                        <div class="chart-container" style="min-width: 300px;">
                            <canvas id="salesChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Inventory Status Card -->
            <div class="col-lg-6 col-md-12 mb-4">
                <div class="widget-container">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h6 class="fw-bold text-dark mb-1">Inventory Status</h6>
                            <p class="text-muted small mb-0">Current stock levels and alerts</p>
                        </div>
                        <a href="{{ route('admin.Product-stock-details') }}" class="btn btn-sm btn-outline-primary border-0 bg-transparent text-primary">
                            <i class="bi bi-arrow-right-circle fs-5"></i>
                        </a>
                    </div>

                    <!-- Scrollable container -->
                    <div class="inventory-container custom-scrollbar" style="max-height: 320px; overflow-y: auto;">
                        @forelse($ProductInventory as $Product)
                        @php
                        $stockPercentage = $Product->total_stock > 0 ?
                        round(($Product->available_stock / $Product->total_stock) * 100, 2) : 0;

                        if ($Product->available_stock == 0) {
                            $statusClass = 'out-of-stock';
                            $statusText = 'Out of Stock';
                            $progressClass = 'bg-danger';
                        } elseif ($stockPercentage <= 25) { 
                            $statusClass='low-stock'; 
                            $statusText='Low Stock';
                            $progressClass='bg-warning'; 
                        } else { 
                            $statusClass='in-stock'; 
                            $statusText='In Stock';
                            $progressClass='bg-success'; 
                        } 
                        @endphp 
                        <div class="inventory-item mb-2">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <div class="fw-bold text-dark">{{ $Product->name }}</div>
                                    <div class="text-muted small">SKU: {{ $Product->code }}</div>
                                </div>
                                <span class="status-badge {{ $statusClass }}">{{ $statusText }}</span>
                            </div>
                            <div class="d-flex align-items-center gap-3">
                                <div class="progress flex-grow-1">
                                    <div class="progress-bar {{ $progressClass }}" style="width: {{ $stockPercentage }}%;"></div>
                                </div>
                                <small class="text-muted fw-500" style="min-width: 45px;">{{ $Product->available_stock }}/{{ $Product->total_stock }}</small>
                            </div>
                        </div>
                        @empty
                        <div class="text-center py-5">
                            <i class="bi bi-box-seam text-muted fs-1 mb-3 d-block"></i>
                            <p class="text-muted">No Product inventory data available.</p>
                        </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        // Prepare data from PHP
        const dailyLabels = @json(collect($dailySales)->pluck('date'));
        const dailyTotals = @json(collect($dailySales)->pluck('total_sales'));

        // Chart instance
        let salesChartInstance = null;

        document.addEventListener('DOMContentLoaded', function() {
            // Initialize daily sales chart
            initializeDailySalesChart();
        });

        function initializeDailySalesChart() {
            const ctx = document.getElementById('salesChart');
            if (!ctx) return;
            
            salesChartInstance = new Chart(ctx.getContext('2d'), {
                type: 'line',
                data: {
                    labels: dailyLabels,
                    datasets: [{
                        label: 'Daily Sales (Rs.)',
                        backgroundColor: 'rgba(245, 131, 32, 0.1)',
                        borderColor: '#f58320',
                        borderWidth: 3,
                        pointBackgroundColor: '#f58320',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointRadius: 5,
                        pointHoverRadius: 7,
                        data: dailyTotals,
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    layout: {
                        padding: {
                            top: 20
                        }
                    },
                    plugins: {
                        legend: { 
                            display: true,
                            position: 'top',
                            labels: {
                                font: { size: 13, weight: '500' },
                                padding: 15,
                                usePointStyle: true
                            }
                        },
                        tooltip: { 
                            backgroundColor: '#1f2937',
                            padding: 12,
                            titleFont: { size: 14, weight: 'bold' },
                            bodyFont: { size: 13 },
                            displayColors: false,
                            callbacks: {
                                label: function(context) {
                                    return 'Rs. ' + context.parsed.y.toLocaleString();
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: { 
                                color: '#f3f4f6',
                                drawBorder: false 
                            },
                            ticks: {
                                font: { size: 12 },
                                color: '#6b7280',
                                callback: function(value) {
                                    if (value >= 1000) return 'Rs.' + (value / 1000) + 'k';
                                    return 'Rs.' + value;
                                }
                            }
                        },
                        x: {
                            grid: { display: false },
                            ticks: {
                                font: { size: 12, weight: '500' },
                                color: '#6b7280'
                            }
                        }
                    }
                }
            });
        }

        // Handle window resize for chart
        window.addEventListener('resize', function() {
            if (salesChartInstance) {
                salesChartInstance.update();
            }
        });
    </script>
    @endpush
</div>