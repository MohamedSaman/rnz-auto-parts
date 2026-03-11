<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Title;
use App\Livewire\Concerns\WithDynamicLayout;
use App\Models\Account;
use App\Models\Voucher;
use App\Models\VoucherEntry;
use App\Services\AccountingService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Barryvdh\DomPDF\Facade\Pdf;

#[Title('Journal Voucher')]
class JournalVoucher extends Component
{
    use WithDynamicLayout, WithPagination;

    // ── Active tab ──
    public $activeTab = 'list'; // 'list' or 'create' or 'view'

    // ── Filter / list properties ──
    public $search = '';
    public $dateFrom = '';
    public $dateTo = '';
    public $perPage = 25;

    // ── Create / Edit form ──
    public $voucherDate;
    public $narration = '';
    public $entries = [];
    public $editingVoucherId = null;

    // ── View modal ──
    public $viewVoucher = null;

    // ── Delete ──
    public $deleteId = null;

    public function mount()
    {
        $this->dateFrom = now()->startOfMonth()->toDateString();
        $this->dateTo = now()->toDateString();
        $this->voucherDate = now()->toDateString();
        $this->addEmptyEntry();
        $this->addEmptyEntry();
    }

    // ── Helpers ──

    public function addEmptyEntry()
    {
        $this->entries[] = [
            'account_id' => '',
            'debit' => '',
            'credit' => '',
            'narration' => '',
        ];
    }

    public function removeEntry($index)
    {
        if (count($this->entries) > 2) {
            unset($this->entries[$index]);
            $this->entries = array_values($this->entries);
        }
    }

    public function getTotalDebitProperty()
    {
        return collect($this->entries)->sum(fn($e) => (float) ($e['debit'] ?? 0));
    }

    public function getTotalCreditProperty()
    {
        return collect($this->entries)->sum(fn($e) => (float) ($e['credit'] ?? 0));
    }

    public function getDifferenceProperty()
    {
        return round($this->totalDebit - $this->totalCredit, 2);
    }

    // ── CRUD ──

    public function switchTab($tab)
    {
        $this->activeTab = $tab;
        if ($tab === 'create') {
            $this->resetForm();
        }
    }

    public function resetForm()
    {
        $this->voucherDate = now()->toDateString();
        $this->narration = '';
        $this->entries = [];
        $this->editingVoucherId = null;
        $this->addEmptyEntry();
        $this->addEmptyEntry();
        $this->resetValidation();
    }

    public function saveVoucher()
    {
        $this->validate([
            'voucherDate' => 'required|date',
            'narration' => 'nullable|string|max:500',
            'entries' => 'required|array|min:2',
            'entries.*.account_id' => 'required|exists:accounts,id',
        ], [
            'entries.*.account_id.required' => 'Select an account for each row.',
            'entries.*.account_id.exists' => 'Invalid account selected.',
        ]);

        // Filter out empty rows
        $validEntries = collect($this->entries)->filter(function ($e) {
            return !empty($e['account_id']) && ((float) ($e['debit'] ?? 0) > 0 || (float) ($e['credit'] ?? 0) > 0);
        })->values()->toArray();

        if (count($validEntries) < 2) {
            $this->addError('entries', 'At least 2 entries with amounts are required.');
            return;
        }

        // Validate balance
        $totalDebit = collect($validEntries)->sum(fn($e) => (float) ($e['debit'] ?? 0));
        $totalCredit = collect($validEntries)->sum(fn($e) => (float) ($e['credit'] ?? 0));

        if (round($totalDebit, 2) !== round($totalCredit, 2)) {
            $this->addError('entries', 'Total Debit (' . number_format($totalDebit, 2) . ') must equal Total Credit (' . number_format($totalCredit, 2) . ').');
            return;
        }

        if ($totalDebit == 0) {
            $this->addError('entries', 'Voucher amount cannot be zero.');
            return;
        }

        try {
            DB::beginTransaction();

            if ($this->editingVoucherId) {
                // Reverse old voucher
                $oldVoucher = Voucher::findOrFail($this->editingVoucherId);
                AccountingService::reverseVoucher($oldVoucher, 'Modified Journal Voucher');
            }

            // Create new voucher
            $formattedEntries = collect($validEntries)->map(function ($e) {
                return [
                    'account_id' => (int) $e['account_id'],
                    'debit' => round((float) ($e['debit'] ?? 0), 2),
                    'credit' => round((float) ($e['credit'] ?? 0), 2),
                    'narration' => $e['narration'] ?? null,
                ];
            })->toArray();

            AccountingService::createVoucher(
                Voucher::TYPE_JOURNAL,
                Carbon::parse($this->voucherDate),
                $formattedEntries,
                $this->narration ?: null,
                null,
                null,
                null
            );

            DB::commit();

            $action = $this->editingVoucherId ? 'updated' : 'created';
            $this->js("Swal.fire({icon:'success', title:'Success!', text:'Journal Voucher {$action} successfully!', timer:2000, showConfirmButton:false})");
            $this->resetForm();
            $this->activeTab = 'list';
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Journal Voucher save error: ' . $e->getMessage());
            $this->js("Swal.fire({icon:'error', title:'Error!', text:'" . addslashes($e->getMessage()) . "'})");
        }
    }

    public function editVoucher($id)
    {
        $voucher = Voucher::with('entries.account')->findOrFail($id);

        $this->editingVoucherId = $voucher->id;
        $this->voucherDate = $voucher->date->toDateString();
        $this->narration = $voucher->narration ?? '';
        $this->entries = $voucher->entries->map(function ($entry) {
            return [
                'account_id' => $entry->account_id,
                'debit' => (float) $entry->debit > 0 ? $entry->debit : '',
                'credit' => (float) $entry->credit > 0 ? $entry->credit : '',
                'narration' => $entry->narration ?? '',
            ];
        })->toArray();

        $this->activeTab = 'create';
    }

    public function viewVoucherDetails($id)
    {
        $this->viewVoucher = Voucher::with(['entries.account', 'creator'])->findOrFail($id);
        $this->js("\$('#journalViewModal').modal('show')");
    }

    public function confirmDelete($id)
    {
        $this->js("
            Swal.fire({
                title: 'Delete Journal Voucher?',
                text: 'This will reverse all accounting entries. This action cannot be undone.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#e11d48',
                cancelButtonColor: '#64748b',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    \$wire.deleteVoucher({$id});
                }
            });
        ");
    }

    public function deleteVoucher($id)
    {
        try {
            $voucher = Voucher::findOrFail($id);
            AccountingService::reverseVoucher($voucher, 'Deleted Journal Voucher');

            $this->js("Swal.fire({icon:'success', title:'Deleted!', text:'Journal Voucher reversed and deleted.', timer:2000, showConfirmButton:false})");
        } catch (\Exception $e) {
            Log::error('Journal Voucher delete error: ' . $e->getMessage());
            $this->js("Swal.fire({icon:'error', title:'Error!', text:'" . addslashes($e->getMessage()) . "'})");
        }
    }

    public function printVoucher($id)
    {
        $voucher = Voucher::with(['entries.account', 'creator'])->findOrFail($id);

        $pdf = Pdf::loadView('livewire.admin.journal-voucher-print', [
            'voucher' => $voucher,
        ])->setPaper('a4', 'portrait');

        return response()->streamDownload(
            fn() => print($pdf->output()),
            'JV-' . $voucher->voucher_no . '.pdf'
        );
    }

    // ── List Queries ──

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedDateFrom()
    {
        $this->resetPage();
    }

    public function updatedDateTo()
    {
        $this->resetPage();
    }

    public function clearFilters()
    {
        $this->search = '';
        $this->dateFrom = now()->startOfMonth()->toDateString();
        $this->dateTo = now()->toDateString();
        $this->resetPage();
    }

    public function getVouchersProperty()
    {
        $query = Voucher::with(['entries.account', 'creator'])
            ->where('voucher_type', Voucher::TYPE_JOURNAL)
            ->where('is_posted', true);

        if ($this->search) {
            $search = $this->search;
            $query->where(function ($q) use ($search) {
                $q->where('voucher_no', 'like', "%{$search}%")
                    ->orWhere('narration', 'like', "%{$search}%");
            });
        }

        if ($this->dateFrom) {
            $query->whereDate('date', '>=', $this->dateFrom);
        }
        if ($this->dateTo) {
            $query->whereDate('date', '<=', $this->dateTo);
        }

        return $query->orderBy('date', 'desc')->orderBy('id', 'desc')->paginate($this->perPage);
    }

    public function getAccountsProperty()
    {
        return Account::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'code', 'name']);
    }

    public function render()
    {
        return view('livewire.admin.journal-voucher', [
            'vouchers' => $this->vouchers,
            'accounts' => $this->accounts,
        ])->layout($this->layout);
    }
}
