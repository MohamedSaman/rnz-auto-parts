<div class="container-fluid py-3">
    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-5">
        <div>
            <h3 class="fw-bold text-dark mb-2">
                <i class="bi bi-people-fill text-success me-2"></i> Manage Staff
            </h3>
            <p class="text-muted mb-0">Manage all staff information efficiently</p>
        </div>
        <div>
                <button class="btn btn-primary" wire:click="createStaff">
                    <i class="bi bi-plus-lg me-2"></i> Create Staff
                </button>
            </div>
    </div>

    @if (session()->has('error'))
    <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
        <i class="bi bi-exclamation-circle-fill me-2"></i> {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    @endif

    @if (session()->has('success'))
    <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    @endif

    {{-- Staff List --}}
    <div class="card h-100">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <h5 class="fw-bold text-dark mb-1">
                    <i class="bi bi-journal-text text-primary me-2"></i> Staff List
                </h5>
            </div>
            <div class="d-flex align-items-center gap-2">
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
                            <th class="ps-4">#</th>
                            <th>Staff Name</th>
                            <th>Contact Number</th>
                            <th>Email</th>
                            <th>Staff Type</th>
                            <th>Status</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @if ($staffs->count() > 0)
                            @foreach ($staffs as $staff)
                            @php
                                $userDetail = \App\Models\UserDetail::where('user_id', $staff->id)->first();
                            @endphp
                            <tr>
                                <td class="ps-4">{{ $loop->iteration }}</td>
                                <td>
                                    <span class="fw-medium text-dark">{{ $staff->name ?? '-' }}</span>
                                </td>
                                <td>{{ $staff->contact ?? '-' }}</td>
                                <td>{{ $staff->email ?? '-' }}</td>
                                <td>
                                    @if($staff->staff_type === 'salesman')
                                        <span class="badge bg-primary">Salesman</span>
                                    @elseif($staff->staff_type === 'delivery_man')
                                        <span class="badge bg-info">Delivery Man</span>
                                    @elseif($staff->staff_type === 'shop_staff')
                                        <span class="badge bg-secondary">Shop Staff</span>
                                    @else
                                        <span class="badge bg-light text-dark">Not Assigned</span>
                                    @endif
                                </td>
                                <td>
                                    @if($userDetail && $userDetail->status == 'active')
                                    <span class="badge bg-success">Active</span>
                                    @else
                                    <span class="badge bg-secondary">Inactive</span>
                                    @endif
                                </td>
                               <td class="text-end pe-4">
    <div class="dropdown">
        <button class="btn btn-outline-secondary dropdown-toggle"
                type="button"
                data-bs-toggle="dropdown"
                aria-expanded="false">
            <i class="bi bi-gear-fill"></i> Actions
        </button>

        <ul class="dropdown-menu dropdown-menu-end shadow-sm">
            <!-- View Staff -->
            <li>
                <button class="dropdown-item"
                        wire:click="viewDetails({{ $staff->id }})"
                        wire:loading.attr="disabled"
                        title="View Staff Details">
                    <span wire:loading wire:target="viewDetails({{ $staff->id }})">
                        <i class="spinner-border spinner-border-sm me-2"></i> Loading...
                    </span>
                    <span wire:loading.remove wire:target="viewDetails({{ $staff->id }})">
                        <i class="bi bi-eye text-info me-2"></i> View
                    </span>
                </button>
            </li>

            <!-- Edit Staff -->
            <li>
                <button class="dropdown-item"
                        wire:click="editStaff({{ $staff->id }})"
                        wire:loading.attr="disabled"
                        title="Edit Staff">
                    <span wire:loading wire:target="editStaff({{ $staff->id }})">
                        <i class="spinner-border spinner-border-sm me-2"></i> Loading...
                    </span>
                    <span wire:loading.remove wire:target="editStaff({{ $staff->id }})">
                        <i class="bi bi-pencil text-primary me-2"></i> Edit
                    </span>
                </button>
            </li>

            <!-- Delete Staff -->
            <li>
                <button class="dropdown-item"
                        wire:click="confirmDelete({{ $staff->id }})"
                        wire:loading.attr="disabled"
                        title="Delete Staff">
                    <span wire:loading wire:target="confirmDelete({{ $staff->id }})">
                        <i class="spinner-border spinner-border-sm me-2"></i> Loading...
                    </span>
                    <span wire:loading.remove wire:target="confirmDelete({{ $staff->id }})">
                        <i class="bi bi-trash text-danger me-2"></i> Delete
                    </span>
                </button>
            </li>
        </ul>
    </div>
</td>

                            </tr>
                            @endforeach
                        @else
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">
                                    <i class="bi bi-people display-4 d-block mb-2"></i>
                                    No staff found
                                </td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-light">
                <div class="d-flex justify-content-center">
                    {{ $staffs->links('livewire.custom-pagination') }}
                </div>
            </div>
        </div>
    </div>

    {{-- View Details Modal --}}
    @if($showViewModal)
    <div class="modal fade show d-block" tabindex="-1" aria-labelledby="viewDetailsModalLabel" aria-hidden="false" style="background-color: rgba(0,0,0,0.5);">
        <div class="modal-dialog modal-dialog-centered" style="max-width: 1100px;">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #4361ee 0%, #3f37c9 100%); border-radius: 12px 12px 0 0;">
                    <div class="d-flex align-items-center">
                        <div class="bg-white bg-opacity-25 rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 45px; height: 45px;">
                            <i class="bi bi-person-badge text-white fs-5"></i>
                        </div>
                        <div>
                            <h5 class="modal-title fw-bold text-white mb-0">{{ $viewUserDetail['name'] ?? '-' }}</h5>
                            <small class="text-white-50">{{ ucfirst(str_replace('_', ' ', $viewUserDetail['staff_type'] ?? 'staff')) }}</small>
                        </div>
                    </div>
                    <button type="button" class="btn-close" wire:click="closeModal"></button>
                </div>

                <div class="px-4 pt-3 pb-2" style="background-color: #f8f9fc;">
                    <div class="row g-2">
                        <div class="col-6 col-md-3">
                            <div class="p-2 rounded text-center" style="background-color: #e8f4fd; border: 1px solid #cce5ff;">
                                <small class="text-muted d-block">Sales Count</small>
                                <strong class="text-primary">{{ count($viewStaffSales) }}</strong>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="p-2 rounded text-center" style="background-color: #d4edda; border: 1px solid #28a745;">
                                <small class="text-muted d-block">Total Sales Amount</small>
                                <strong class="text-success">{{ number_format(collect($viewStaffSales)->sum('total_amount'), 2) }}</strong>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="p-2 rounded text-center" style="background-color: #fff3cd; border: 1px solid #ffc107;">
                                <small class="text-muted d-block">Collected Payments</small>
                                <strong class="text-warning">{{ number_format(collect($viewStaffPayments)->sum('amount'), 2) }}</strong>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="p-2 rounded text-center" style="background-color: #f8d7da; border: 1px solid #dc3545;">
                                <small class="text-muted d-block">Sales Due</small>
                                <strong class="text-danger">{{ number_format(collect($viewStaffDues)->sum('due_amount'), 2) }}</strong>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="px-4 pt-3" style="background-color: #f8f9fc;">
                    <ul class="nav nav-tabs border-0" style="gap: 4px;">
                        <li class="nav-item">
                            <button class="nav-link customer-tab {{ $activeTab === 'overview' ? 'active' : '' }}" wire:click="setActiveTab('overview')">
                                <i class="bi bi-info-circle me-1"></i>Overview
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link customer-tab {{ $activeTab === 'sales' ? 'active' : '' }}" wire:click="setActiveTab('sales')">
                                <i class="bi bi-receipt me-1"></i>Sales List
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link customer-tab {{ $activeTab === 'payments' ? 'active' : '' }}" wire:click="setActiveTab('payments')">
                                <i class="bi bi-cash-stack me-1"></i>Payment List
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link customer-tab {{ $activeTab === 'dues' ? 'active' : '' }}" wire:click="setActiveTab('dues')">
                                <i class="bi bi-exclamation-circle me-1"></i>Sales Due
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link customer-tab {{ $activeTab === 'ledger' ? 'active' : '' }}" wire:click="setActiveTab('ledger')">
                                <i class="bi bi-journal-text me-1"></i>Ledger
                            </button>
                        </li>
                    </ul>
                </div>

                <div class="modal-body" style="max-height: 55vh; overflow-y: auto;">
                    @if($activeTab === 'overview')
                    <div class="row g-3">
                        <div class="col-md-4 d-flex flex-column align-items-center justify-content-start p-3 border-end">
                            <img src="{{ $viewUserDetail['user_image'] ?? 'https://media.istockphoto.com/id/1300845620/vector/user-icon-flat-isolated-on-white-background-user-symbol-vector-illustration.jpg?s=612x612&w=0&k=20&c=yBeyba0hUkh14_jgv1OKqIH0CCSWU_4ckRkAoy2p73o=' }}"
                                alt="User Image" class="img-fluid rounded-circle shadow mb-3"
                                style="width: 140px; height: 140px; object-fit: cover;">
                            <span class="fw-bold fs-5">{{ $viewUserDetail['name'] ?? '-' }}</span>
                            <span class="text-muted">{{ ucfirst(str_replace('_', ' ', $viewUserDetail['staff_type'] ?? 'staff')) }}</span>
                        </div>
                        <div class="col-md-8 p-3">
                            <div class="mb-3 pb-2 border-bottom">
                                <h6 class="fw-bold text-primary mb-2"><i class="bi bi-person-lines-fill me-1"></i> Personal Info</h6>
                                <div class="row mb-1">
                                    <div class="col-5 text-muted">Contact:</div><div class="col-7">{{ $viewUserDetail['contact'] ?? '-' }}</div>
                                    <div class="col-5 text-muted">Email:</div><div class="col-7">{{ $viewUserDetail['email'] ?? '-' }}</div>
                                    <div class="col-5 text-muted">Date of Birth:</div><div class="col-7">{{ $viewUserDetail['dob'] ?? '-' }}</div>
                                    <div class="col-5 text-muted">Age:</div><div class="col-7">{{ $viewUserDetail['age'] ?? '-' }}</div>
                                    <div class="col-5 text-muted">NIC Number:</div><div class="col-7">{{ $viewUserDetail['nic_num'] ?? '-' }}</div>
                                    <div class="col-5 text-muted">Gender:</div><div class="col-7">{{ $viewUserDetail['gender'] ?? '-' }}</div>
                                </div>
                            </div>
                            <div class="mb-3 pb-2 border-bottom">
                                <h6 class="fw-bold text-primary mb-2"><i class="bi bi-building me-1"></i> Work Info</h6>
                                <div class="row mb-1">
                                    <div class="col-5 text-muted">Work Role:</div><div class="col-7">{{ $viewUserDetail['work_role'] ?? '-' }}</div>
                                    <div class="col-5 text-muted">Department:</div><div class="col-7">{{ $viewUserDetail['department'] ?? '-' }}</div>
                                    <div class="col-5 text-muted">Join Date:</div><div class="col-7">{{ $viewUserDetail['join_date'] ?? '-' }}</div>
                                    <div class="col-5 text-muted">Fingerprint ID:</div><div class="col-7">{{ $viewUserDetail['fingerprint_id'] ?? '-' }}</div>
                                    <div class="col-5 text-muted">Allowance:</div><div class="col-7">{{ is_array($viewUserDetail['allowance'] ?? null) ? implode(', ', $viewUserDetail['allowance']) : ($viewUserDetail['allowance'] ?? '-') }}</div>
                                    <div class="col-5 text-muted">Basic Salary:</div><div class="col-7">{{ $viewUserDetail['basic_salary'] ?? '-' }}</div>
                                    <div class="col-5 text-muted">Status:</div><div class="col-7">{{ $viewUserDetail['status'] ?? '-' }}</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    @elseif($activeTab === 'sales')
                    <div class="table-responsive">
                        <table class="table table-hover table-sm mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>Invoice</th>
                                    <th>Customer</th>
                                    <th class="text-center">Items</th>
                                    <th class="text-end">Total</th>
                                    <th class="text-end">Paid</th>
                                    <th class="text-end">Due</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($viewStaffSales as $i => $sale)
                                <tr>
                                    <td>{{ $i + 1 }}</td>
                                    <td>{{ $sale['invoice_number'] ?? '-' }}</td>
                                    <td>{{ $sale['customer'] ?? '-' }}</td>
                                    <td class="text-center">{{ $sale['items_count'] ?? 0 }}</td>
                                    <td class="text-end">{{ number_format($sale['total_amount'] ?? 0, 2) }}</td>
                                    <td class="text-end text-success">{{ number_format($sale['paid_amount'] ?? 0, 2) }}</td>
                                    <td class="text-end text-danger">{{ number_format($sale['due_amount'] ?? 0, 2) }}</td>
                                    <td><span class="badge bg-secondary">{{ ucfirst($sale['status'] ?? 'pending') }}</span></td>
                                </tr>
                                @empty
                                <tr><td colspan="8" class="text-center text-muted py-3">No sales found for this staff.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @elseif($activeTab === 'payments')
                    <div class="table-responsive">
                        <table class="table table-hover table-sm mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>Date</th>
                                    <th>Invoice</th>
                                    <th>Method</th>
                                    <th>Reference</th>
                                    <th class="text-end">Amount</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($viewStaffPayments as $i => $payment)
                                <tr>
                                    <td>{{ $i + 1 }}</td>
                                    <td>{{ $payment['payment_date'] ?? '-' }}</td>
                                    <td>{{ $payment['invoice_number'] ?? '-' }}</td>
                                    <td>{{ ucfirst($payment['payment_method'] ?? '-') }}</td>
                                    <td>{{ $payment['payment_reference'] ?? '-' }}</td>
                                    <td class="text-end">{{ number_format($payment['amount'] ?? 0, 2) }}</td>
                                    <td><span class="badge bg-secondary">{{ ucfirst($payment['status'] ?? 'pending') }}</span></td>
                                </tr>
                                @empty
                                <tr><td colspan="7" class="text-center text-muted py-3">No payments found for this staff sales.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @elseif($activeTab === 'dues')
                    <div class="table-responsive">
                        <table class="table table-hover table-sm mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>Invoice</th>
                                    <th>Customer</th>
                                    <th class="text-end">Total</th>
                                    <th class="text-end">Paid</th>
                                    <th class="text-end">Due</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($viewStaffDues as $i => $due)
                                <tr>
                                    <td>{{ $i + 1 }}</td>
                                    <td>{{ $due['invoice_number'] ?? '-' }}</td>
                                    <td>{{ $due['customer'] ?? '-' }}</td>
                                    <td class="text-end">{{ number_format($due['total_amount'] ?? 0, 2) }}</td>
                                    <td class="text-end text-success">{{ number_format($due['paid_amount'] ?? 0, 2) }}</td>
                                    <td class="text-end fw-semibold text-danger">{{ number_format($due['due_amount'] ?? 0, 2) }}</td>
                                    <td><span class="badge bg-warning text-dark">{{ ucfirst($due['status'] ?? 'pending') }}</span></td>
                                </tr>
                                @empty
                                <tr><td colspan="7" class="text-center text-muted py-3">No due sales found.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @elseif($activeTab === 'ledger')
                    <div class="table-responsive">
                        <table class="table table-hover table-sm mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>Date</th>
                                    <th>Description</th>
                                    <th>Reference</th>
                                    <th class="text-end">Debit</th>
                                    <th class="text-end">Credit</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php $running = 0; @endphp
                                @forelse($viewStaffLedger as $i => $entry)
                                @php $running += ($entry['debit'] ?? 0) - ($entry['credit'] ?? 0); @endphp
                                <tr>
                                    <td>{{ $i + 1 }}</td>
                                    <td>{{ $entry['date'] ?? '-' }}</td>
                                    <td>{{ $entry['description'] ?? '-' }}</td>
                                    <td>{{ $entry['reference'] ?? '-' }}</td>
                                    <td class="text-end text-danger">{{ number_format($entry['debit'] ?? 0, 2) }}</td>
                                    <td class="text-end text-success">{{ number_format($entry['credit'] ?? 0, 2) }}</td>
                                </tr>
                                @empty
                                <tr><td colspan="6" class="text-center text-muted py-3">No ledger entries found.</td></tr>
                                @endforelse
                            </tbody>
                            @if(count($viewStaffLedger) > 0)
                            <tfoot class="table-light">
                                <tr>
                                    <th colspan="4" class="text-end">Balance:</th>
                                    <th colspan="2" class="text-end {{ $running >= 0 ? 'text-danger' : 'text-success' }}">{{ number_format(abs($running), 2) }}</th>
                                </tr>
                            </tfoot>
                            @endif
                        </table>
                    </div>
                    @endif
                </div>

                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" wire:click="closeModal">
                        <i class="bi bi-x-lg me-1"></i> Close
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- Create Staff Modal --}}
    @if($showCreateModal)
    <div class="modal fade show d-block" tabindex="-1" aria-labelledby="createStaffModalLabel" aria-hidden="false" style="background-color: rgba(0,0,0,0.5);">
        <div class="modal-dialog modal-dialog-centered modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">
                        <i class="bi bi-plus-circle text-white me-2"></i> Create Staff
                    </h5>
                    <button type="button" class="btn-close" wire:click="closeModal"></button>
                </div>
                <div class="modal-body">
                    <form wire:submit.prevent="saveStaff">
                        <!-- Hidden fields with default values -->
                        <input type="hidden" wire:model="work_type" value="monthly">
                        <input type="hidden" wire:model="status" value="active">
                        
                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Staff Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control @error('name') is-invalid @enderror" 
                                           wire:model="name" placeholder="Enter staff name" required>
                                    @error('name') <span class="text-danger small">{{ $message }}</span> @enderror
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Contact Number <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control @error('contactNumber') is-invalid @enderror" 
                                           wire:model="contactNumber" placeholder="Enter contact number" required>
                                    @error('contactNumber') <span class="text-danger small">{{ $message }}</span> @enderror
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Email <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control @error('email') is-invalid @enderror" 
                                           wire:model="email" placeholder="Enter email" required>
                                    @error('email') <span class="text-danger small">{{ $message }}</span> @enderror
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Gender</label>
                                    <select class="form-select @error('gender') is-invalid @enderror" wire:model="gender">
                                        <option value="">Select Gender</option>
                                        <option value="male">Male</option>
                                        <option value="female">Female</option>
                                        <option value="other">Other</option>
                                    </select>
                                    @error('gender') <span class="text-danger small">{{ $message }}</span> @enderror
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Staff Type <span class="text-danger">*</span></label>
                                    <select class="form-select @error('staff_type') is-invalid @enderror" wire:model="staff_type" required>
                                        <option value="">Select Staff Type</option>
                                        <option value="salesman">Salesman</option>
                                        <option value="delivery_man">Delivery Man</option>
                                        <option value="shop_staff">Shop Staff</option>
                                    </select>
                                    @error('staff_type') <span class="text-danger small">{{ $message }}</span> @enderror
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Basic Salary</label>
                                    <input type="number" step="0.01" class="form-control @error('basic_salary') is-invalid @enderror" 
                                           wire:model="basic_salary" placeholder="Enter basic salary">
                                    @error('basic_salary') <span class="text-danger small">{{ $message }}</span> @enderror
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Address</label>
                                    <textarea class="form-control @error('address') is-invalid @enderror" 
                                              wire:model="address" placeholder="Enter address" rows="3"></textarea>
                                    @error('address') <span class="text-danger small">{{ $message }}</span> @enderror
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Password <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="password" id="createPassword" class="form-control @error('password') is-invalid @enderror" 
                                               wire:model="password" placeholder="Enter password" required>
                                        <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility('createPassword')">
                                            <i class="bi bi-eye" id="createPasswordToggleIcon"></i>
                                        </button>
                                    </div>
                                    @error('password') <span class="text-danger small">{{ $message }}</span> @enderror
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Confirm Password <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="password" id="createConfirmPassword" class="form-control @error('confirmPassword') is-invalid @enderror" 
                                               wire:model="confirmPassword" placeholder="Confirm password" required>
                                        <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility('createConfirmPassword')">
                                            <i class="bi bi-eye" id="createConfirmPasswordToggleIcon"></i>
                                        </button>
                                    </div>
                                    @error('confirmPassword') <span class="text-danger small">{{ $message }}</span> @enderror
                                </div>
                            </div>
                        </div>
                        <div class="d-grid mt-3">
                            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                                <i class="bi bi-check2-circle me-1"></i>
                                <span wire:loading.remove>Save Staff</span>
                                <span wire:loading>Saving...</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    @endif

  {{-- Edit Staff Modal --}}
@if($showEditModal)
<div class="modal fade show d-block" tabindex="-1" aria-labelledby="editStaffModalLabel" aria-hidden="false" style="background-color: rgba(0,0,0,0.5);">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-pencil-square text-white me-2"></i> Edit Staff
                </h5>
                <button type="button" class="btn-close" wire:click="closeModal"></button>
            </div>
            <div class="modal-body">
                <form wire:submit.prevent="updateStaff">
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Staff Name</label>
                                <input type="text" class="form-control @error('editName') is-invalid @enderror" 
                                       wire:model="editName" required>
                                @error('editName') <span class="text-danger small">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-12 col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Contact Number</label>
                                <input type="text" class="form-control @error('editContactNumber') is-invalid @enderror" 
                                       wire:model="editContactNumber" required>
                                @error('editContactNumber') <span class="text-danger small">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-12 col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Email</label>
                                <input type="email" class="form-control @error('editEmail') is-invalid @enderror" 
                                       wire:model="editEmail" required>
                                @error('editEmail') <span class="text-danger small">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-12 col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Status</label>
                                <select class="form-select @error('editStatus') is-invalid @enderror" wire:model="editStatus" required>
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                                @error('editStatus') <span class="text-danger small">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-12 col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Staff Type</label>
                                <select class="form-select @error('editStaffType') is-invalid @enderror" wire:model="editStaffType">
                                    <option value="">No Staff Type</option>
                                    <option value="salesman">Salesman</option>
                                    <option value="delivery_man">Delivery Man</option>
                                    <option value="shop_staff">Shop Staff</option>
                                </select>
                                @error('editStaffType') <span class="text-danger small">{{ $message }}</span> @enderror
                                <small class="text-muted">Determines staff access and features</small>
                            </div>
                        </div>
                        <div class="col-12 col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Password (leave blank to keep current)</label>
                                <div class="input-group">
                                    <input type="password" class="form-control @error('editPassword') is-invalid @enderror" 
                                           wire:model="editPassword" placeholder="Enter new password">
                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility('editPassword')">
                                        <i class="bi bi-eye" id="editPasswordToggleIcon"></i>
                                    </button>
                                </div>
                                @error('editPassword') <span class="text-danger small">{{ $message }}</span> @enderror
                            </div>
                        </div>
                    </div>
                    @if(!empty($editPassword))
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <div class="mb-4">
                                <label class="form-label fw-semibold">Confirm Password</label>
                                <div class="input-group">
                                    <input type="password" class="form-control @error('editConfirmPassword') is-invalid @enderror" 
                                           wire:model="editConfirmPassword" placeholder="Confirm new password">
                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility('editConfirmPassword')">
                                        <i class="bi bi-eye" id="editConfirmPasswordToggleIcon"></i>
                                    </button>
                                </div>
                                @error('editConfirmPassword') <span class="text-danger small">{{ $message }}</span> @enderror
                            </div>
                        </div>
                    </div>
                    @endif
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                            <i class="bi bi-check2-circle me-1"></i>
                            <span wire:loading.remove>Update Staff</span>
                            <span wire:loading>Updating...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endif

    {{-- Delete Confirmation Modal --}}
    @if($showDeleteModal)
    <div class="modal fade show d-block" tabindex="-1" aria-labelledby="deleteConfirmationModalLabel" aria-hidden="false" style="background-color: rgba(0,0,0,0.5);">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold text-white">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i> Confirm Delete
                    </h5>
                    <button type="button" class="btn-close" wire:click="cancelDelete"></button>
                </div>
                <div class="modal-body text-center">
                    <i class="bi bi-person-x text-danger fs-1 mb-3 d-block"></i>
                    <h5 class="fw-bold mb-3">Are you sure?</h5>
                    <p class="text-muted">You are about to delete this staff member. This action cannot be undone.</p>
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn btn-secondary" wire:click="cancelDelete">Cancel</button>
                    <button type="button" class="btn btn-danger" wire:click="deleteStaff" wire:loading.attr="disabled">
                        <i class="bi bi-trash me-1"></i>
                        <span wire:loading.remove>Delete Staff</span>
                        <span wire:loading>Deleting...</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>

@push('styles')
<style>
    .card {
        border: none;
        border-radius: 12px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        transition: all 0.3s ease;
    }

    .card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 16px rgba(0, 0, 0, 0.12);
    }

    .card-header {
        background-color: white;
        border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        border-radius: 12px 12px 0 0 !important;
        padding: 1.25rem 1.5rem;
    }


    .table td {
        vertical-align: middle;
        padding: 1rem 0.75rem;
    }

    .btn-link {
        text-decoration: none;
        transition: all 0.2s ease;
    }

    .btn-link:hover {
        transform: scale(1.1);
    }

    .modal-content {
        border: none;
        border-radius: 12px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
    }

    .form-control,
    .form-select {
        border-radius: 8px;
        padding: 0.75rem 1rem;
        border: 1px solid #e2e8f0;
    }

    .form-control:focus,
    .form-select:focus {
        box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.15);
        border-color: #4361ee;
    }

    .btn {
        border-radius: 8px;
        font-weight: 500;
        padding: 0.75rem 1.5rem;
        transition: all 0.3s ease;
    }

    .btn-primary {
        background-color: #4361ee;
        border-color: #4361ee;
    }

    .btn-primary:hover {
        background-color: #3f37c9;
        border-color: #3f37c9;
        transform: translateY(-2px);
    }

    .btn-danger {
        background-color: #e63946;
        border-color: #e63946;
    }

    .btn-danger:hover {
        background-color: #d00000;
        border-color: #d00000;
        transform: translateY(-2px);
    }

    .alert {
        border-radius: 8px;
        border: none;
    }

    .badge {
        font-size: 0.75rem;
        padding: 0.35rem 0.65rem;
        border-radius: 6px;
    }

    .customer-tab {
        border: none !important;
        background: transparent;
        color: #6c757d;
        font-weight: 500;
        padding: 0.6rem 1rem;
        border-radius: 8px 8px 0 0 !important;
        transition: all 0.2s ease;
        font-size: 0.875rem;
    }

    .customer-tab:hover {
        color: #4361ee;
        background: rgba(67, 97, 238, 0.08);
    }

    .customer-tab.active {
        color: #4361ee !important;
        background: white !important;
        border-bottom: 3px solid #4361ee !important;
        font-weight: 600;
    }

    .nav-tabs {
        border-bottom: 2px solid #e9ecef;
    }
</style>
@endpush

@push('scripts')
<script>
    // Password toggle visibility function
    function togglePasswordVisibility(inputId) {
        const passwordInput = document.getElementById(inputId);
        const toggleIcon = document.getElementById(inputId + 'ToggleIcon');
        
        if (passwordInput.type === "password") {
            passwordInput.type = "text";
            toggleIcon.classList.remove("bi-eye");
            toggleIcon.classList.add("bi-eye-slash");
        } else {
            passwordInput.type = "password";
            toggleIcon.classList.remove("bi-eye-slash");
            toggleIcon.classList.add("bi-eye");
        }
    }
</script>
@endpush