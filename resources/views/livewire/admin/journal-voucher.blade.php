<div>
    {{-- ═══ BUSY-Style Header Bar ═══ --}}
    <div class="d-flex align-items-center justify-content-between px-3 py-2" style="background:linear-gradient(135deg, #0f172a 0%, #1e293b 100%); border-bottom:2px solid var(--primary);">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-journal-bookmark text-white" style="font-size:1.2rem;"></i>
            <h5 class="fw-bold text-white mb-0" style="font-size:15px;">Journal Voucher</h5>
        </div>
        <div class="d-flex align-items-center gap-2">
            <button class="btn btn-sm {{ $activeTab === 'list' ? 'btn-light' : 'btn-outline-light' }}" wire:click="switchTab('list')" style="font-size:11px;">
                <i class="bi bi-list-ul me-1"></i> List
            </button>
            <button class="btn btn-sm {{ $activeTab === 'create' ? 'btn-light' : 'btn-outline-light' }}" wire:click="switchTab('create')" style="font-size:11px;">
                <i class="bi bi-plus-circle me-1"></i> {{ $editingVoucherId ? 'Edit' : 'Add New' }}
            </button>
        </div>
    </div>

    <div class="container-fluid px-3 py-2">

        {{-- ════════════════════════════════════════════════════════
             TAB: LIST
        ════════════════════════════════════════════════════════ --}}
        @if($activeTab === 'list')

            {{-- Filter Bar --}}
            <div class="card mb-2" style="border-left:3px solid #8b5cf6;">
                <div class="card-body py-2 px-3">
                    <div class="row g-2 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label mb-1 fw-600" style="font-size:10px; text-transform:uppercase; letter-spacing:.04em; color:var(--text-muted);">
                                <i class="bi bi-search me-1"></i>Search
                            </label>
                            <input type="text" class="form-control form-control-sm" wire:model.live.debounce.400ms="search"
                                   placeholder="Voucher no, narration...">
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
                            <label class="form-label mb-1 fw-600" style="font-size:10px; text-transform:uppercase; letter-spacing:.04em; color:var(--text-muted);">Per Page</label>
                            <select class="form-select form-select-sm" wire:model.live="perPage">
                                <option value="10">10</option>
                                <option value="25">25</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button class="btn btn-outline-secondary btn-sm w-100" wire:click="clearFilters" title="Reset Filters">
                                <i class="bi bi-arrow-counterclockwise me-1"></i> Reset
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Summary Cards --}}
            @php
                $totalAmount = $vouchers->sum('total_amount');
                $voucherCount = $vouchers->total();
            @endphp
            <div class="row g-2 mb-2">
                <div class="col-md-4">
                    <div class="card h-100" style="border-left:3px solid #8b5cf6;">
                        <div class="card-body py-2 px-3">
                            <p class="text-muted mb-0" style="font-size:10px; text-transform:uppercase;">Total Vouchers</p>
                            <h5 class="fw-800 mb-0" style="color:#8b5cf6;">{{ $voucherCount }}</h5>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100" style="border-left:3px solid var(--success);">
                        <div class="card-body py-2 px-3">
                            <p class="text-muted mb-0" style="font-size:10px; text-transform:uppercase;">Total Debit/Credit</p>
                            <h5 class="fw-800 mb-0" style="color:var(--success);">Rs {{ number_format($totalAmount, 2) }}</h5>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100" style="border-left:3px solid #0ea5e9;">
                        <div class="card-body py-2 px-3">
                            <p class="text-muted mb-0" style="font-size:10px; text-transform:uppercase;">Period</p>
                            <h6 class="fw-700 mb-0" style="color:#0ea5e9; font-size:13px;">{{ \Carbon\Carbon::parse($dateFrom)->format('d M') }} – {{ \Carbon\Carbon::parse($dateTo)->format('d M Y') }}</h6>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Voucher Table --}}
            <div class="card">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-sm mb-0" style="font-size:12px;">
                            <thead>
                                <tr style="background:linear-gradient(135deg, #0f172a, #1e293b); color:white;">
                                    <th class="px-3 py-2">#</th>
                                    <th class="px-3 py-2">Voucher No</th>
                                    <th class="px-3 py-2">Date</th>
                                    <th class="px-3 py-2">Narration</th>
                                    <th class="px-3 py-2 text-end">Debit (Rs)</th>
                                    <th class="px-3 py-2 text-end">Credit (Rs)</th>
                                    <th class="px-3 py-2">Entries</th>
                                    <th class="px-3 py-2">Created By</th>
                                    <th class="px-3 py-2 text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($vouchers as $index => $v)
                                    <tr class="align-middle">
                                        <td class="px-3">{{ $vouchers->firstItem() + $index }}</td>
                                        <td class="px-3 fw-600" style="color:#8b5cf6;">{{ $v->voucher_no }}</td>
                                        <td class="px-3">{{ $v->date->format('d M Y') }}</td>
                                        <td class="px-3 text-truncate" style="max-width:200px;">{{ $v->narration ?? '—' }}</td>
                                        <td class="px-3 text-end fw-600">{{ number_format($v->getTotalDebit(), 2) }}</td>
                                        <td class="px-3 text-end fw-600">{{ number_format($v->getTotalCredit(), 2) }}</td>
                                        <td class="px-3">
                                            <span class="badge bg-secondary">{{ $v->entries->count() }} rows</span>
                                        </td>
                                        <td class="px-3">{{ $v->creator->name ?? '—' }}</td>
                                        <td class="px-3 text-center">
                                            <div class="d-flex gap-1 justify-content-center">
                                                <button class="btn btn-sm btn-outline-info" wire:click="viewVoucherDetails({{ $v->id }})" title="View">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-primary" wire:click="editVoucher({{ $v->id }})" title="Edit">
                                                    <i class="bi bi-pencil-square"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger" wire:click="confirmDelete({{ $v->id }})" title="Delete">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="text-center py-4 text-muted">
                                            <i class="bi bi-journal-x" style="font-size:2rem;"></i>
                                            <p class="mb-0 mt-2">No journal vouchers found.</p>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                @if($vouchers->hasPages())
                    <div class="card-footer bg-white py-2 px-3">
                        {{ $vouchers->links() }}
                    </div>
                @endif
            </div>

        @endif

        {{-- ════════════════════════════════════════════════════════
             TAB: CREATE / EDIT
        ════════════════════════════════════════════════════════ --}}
        @if($activeTab === 'create')

            <div class="card">
                <div class="card-header bg-white d-flex align-items-center justify-content-between">
                    <h6 class="fw-bold mb-0">
                        <i class="bi bi-journal-plus me-1" style="color:#8b5cf6;"></i>
                        {{ $editingVoucherId ? 'Edit Journal Voucher' : 'New Journal Voucher' }}
                    </h6>
                    @if($editingVoucherId)
                        <span class="badge bg-warning text-dark">Editing: JV #{{ $editingVoucherId }}</span>
                    @endif
                </div>
                <div class="card-body">
                    <form wire:submit.prevent="saveVoucher">

                        {{-- Top Row: Date & Narration --}}
                        <div class="row g-3 mb-3">
                            <div class="col-md-3">
                                <label class="form-label fw-600" style="font-size:11px; text-transform:uppercase;">
                                    <i class="bi bi-calendar3 me-1"></i>Voucher Date <span class="text-danger">*</span>
                                </label>
                                <input type="date" class="form-control form-control-sm" wire:model="voucherDate">
                                @error('voucherDate') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>
                            <div class="col-md-9">
                                <label class="form-label fw-600" style="font-size:11px; text-transform:uppercase;">
                                    <i class="bi bi-chat-left-text me-1"></i>Narration / Description
                                </label>
                                <input type="text" class="form-control form-control-sm" wire:model="narration" placeholder="Enter voucher narration...">
                                @error('narration') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>
                        </div>

                        {{-- Entry Rows --}}
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm mb-0" style="font-size:12px;">
                                <thead>
                                    <tr style="background:#f1f5f9;">
                                        <th style="width:5%;" class="text-center">#</th>
                                        <th style="width:35%;">Account <span class="text-danger">*</span></th>
                                        <th style="width:18%;" class="text-end">Debit (Rs)</th>
                                        <th style="width:18%;" class="text-end">Credit (Rs)</th>
                                        <th style="width:18%;">Narration</th>
                                        <th style="width:6%;" class="text-center">
                                            <button type="button" class="btn btn-sm btn-outline-success" wire:click="addEmptyEntry" title="Add Row">
                                                <i class="bi bi-plus-lg"></i>
                                            </button>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($entries as $idx => $entry)
                                        <tr wire:key="entry-{{ $idx }}">
                                            <td class="text-center align-middle">{{ $idx + 1 }}</td>
                                            <td>
                                                <select class="form-select form-select-sm" wire:model="entries.{{ $idx }}.account_id">
                                                    <option value="">-- Select Account --</option>
                                                    @foreach($accounts as $acc)
                                                        <option value="{{ $acc->id }}">{{ $acc->code }} — {{ $acc->name }}</option>
                                                    @endforeach
                                                </select>
                                                @error("entries.{$idx}.account_id") <small class="text-danger">{{ $message }}</small> @enderror
                                            </td>
                                            <td>
                                                <input type="number" step="0.01" min="0" class="form-control form-control-sm text-end"
                                                       wire:model.blur="entries.{{ $idx }}.debit" placeholder="0.00"
                                                       {{ (float)($entry['credit'] ?? 0) > 0 ? 'disabled' : '' }}>
                                            </td>
                                            <td>
                                                <input type="number" step="0.01" min="0" class="form-control form-control-sm text-end"
                                                       wire:model.blur="entries.{{ $idx }}.credit" placeholder="0.00"
                                                       {{ (float)($entry['debit'] ?? 0) > 0 ? 'disabled' : '' }}>
                                            </td>
                                            <td>
                                                <input type="text" class="form-control form-control-sm"
                                                       wire:model="entries.{{ $idx }}.narration" placeholder="Line note">
                                            </td>
                                            <td class="text-center align-middle">
                                                @if(count($entries) > 2)
                                                    <button type="button" class="btn btn-sm btn-outline-danger" wire:click="removeEntry({{ $idx }})" title="Remove">
                                                        <i class="bi bi-x-lg"></i>
                                                    </button>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    <tr style="background:#f8fafc; font-weight:700;">
                                        <td colspan="2" class="text-end pe-3">TOTAL:</td>
                                        <td class="text-end" style="color:{{ $this->difference == 0 && $this->totalDebit > 0 ? '#16a34a' : '#dc2626' }};">
                                            {{ number_format($this->totalDebit, 2) }}
                                        </td>
                                        <td class="text-end" style="color:{{ $this->difference == 0 && $this->totalCredit > 0 ? '#16a34a' : '#dc2626' }};">
                                            {{ number_format($this->totalCredit, 2) }}
                                        </td>
                                        <td colspan="2" class="text-center">
                                            @if($this->difference == 0 && $this->totalDebit > 0)
                                                <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i> Balanced</span>
                                            @elseif($this->totalDebit > 0 || $this->totalCredit > 0)
                                                <span class="badge bg-danger">
                                                    <i class="bi bi-exclamation-triangle me-1"></i>
                                                    Diff: Rs {{ number_format(abs($this->difference), 2) }}
                                                </span>
                                            @endif
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        @error('entries') <div class="alert alert-danger mt-2 py-2" style="font-size:12px;">{{ $message }}</div> @enderror

                        {{-- Buttons --}}
                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <button type="button" class="btn btn-outline-secondary btn-sm" wire:click="switchTab('list')">
                                <i class="bi bi-arrow-left me-1"></i> Back to List
                            </button>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-outline-warning btn-sm" wire:click="resetForm">
                                    <i class="bi bi-arrow-counterclockwise me-1"></i> Reset
                                </button>
                                <button type="submit" class="btn btn-sm text-white"
                                        style="background:linear-gradient(135deg, #8b5cf6 0%, #6d28d9 100%);"
                                        {{ $this->difference != 0 || $this->totalDebit == 0 ? 'disabled' : '' }}>
                                    <i class="bi bi-check-lg me-1"></i>
                                    {{ $editingVoucherId ? 'Update Voucher' : 'Save Voucher' }}
                                </button>
                            </div>
                        </div>

                    </form>
                </div>
            </div>

        @endif

    </div>

    {{-- ════════════════════════════════════════════════════════
         VIEW MODAL
    ════════════════════════════════════════════════════════ --}}
    <div wire:ignore.self class="modal fade" id="journalViewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content" style="border-radius:12px; overflow:hidden;">
                <div class="modal-header text-white" style="background:linear-gradient(135deg, #0f172a, #1e293b);">
                    <h6 class="modal-title fw-bold mb-0"><i class="bi bi-journal-bookmark me-2"></i>Journal Voucher Details</h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                @if($viewVoucher)
                    <div class="modal-body p-4">
                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <span class="text-muted" style="font-size:10px; text-transform:uppercase;">Voucher No</span>
                                <p class="fw-bold mb-0" style="color:#8b5cf6;">{{ $viewVoucher->voucher_no }}</p>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted" style="font-size:10px; text-transform:uppercase;">Date</span>
                                <p class="fw-bold mb-0">{{ $viewVoucher->date->format('d M Y') }}</p>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted" style="font-size:10px; text-transform:uppercase;">Created By</span>
                                <p class="fw-bold mb-0">{{ $viewVoucher->creator->name ?? '—' }}</p>
                            </div>
                        </div>

                        @if($viewVoucher->narration)
                            <div class="alert alert-light py-2 mb-3" style="font-size:12px; border-left:3px solid #8b5cf6;">
                                <strong>Narration:</strong> {{ $viewVoucher->narration }}
                            </div>
                        @endif

                        <div class="table-responsive">
                            <table class="table table-bordered table-sm mb-0" style="font-size:12px;">
                                <thead>
                                    <tr style="background:#f1f5f9;">
                                        <th>#</th>
                                        <th>Account</th>
                                        <th class="text-end">Debit (Rs)</th>
                                        <th class="text-end">Credit (Rs)</th>
                                        <th>Narration</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($viewVoucher->entries as $idx => $entry)
                                        <tr>
                                            <td>{{ $idx + 1 }}</td>
                                            <td class="fw-600">{{ $entry->account->code ?? '' }} — {{ $entry->account->name ?? 'Unknown' }}</td>
                                            <td class="text-end {{ (float) $entry->debit > 0 ? 'text-success fw-bold' : '' }}">
                                                {{ (float) $entry->debit > 0 ? number_format($entry->debit, 2) : '—' }}
                                            </td>
                                            <td class="text-end {{ (float) $entry->credit > 0 ? 'text-danger fw-bold' : '' }}">
                                                {{ (float) $entry->credit > 0 ? number_format($entry->credit, 2) : '—' }}
                                            </td>
                                            <td>{{ $entry->narration ?? '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    <tr style="background:#f8fafc; font-weight:700;">
                                        <td colspan="2" class="text-end">TOTAL:</td>
                                        <td class="text-end text-success">{{ number_format($viewVoucher->getTotalDebit(), 2) }}</td>
                                        <td class="text-end text-danger">{{ number_format($viewVoucher->getTotalCredit(), 2) }}</td>
                                        <td>
                                            @if($viewVoucher->isBalanced())
                                                <span class="badge bg-success"><i class="bi bi-check-circle"></i> Balanced</span>
                                            @else
                                                <span class="badge bg-danger"><i class="bi bi-exclamation-triangle"></i> Unbalanced</span>
                                            @endif
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
