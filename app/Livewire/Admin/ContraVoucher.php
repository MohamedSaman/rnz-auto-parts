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

#[Title('Contra Voucher')]
class ContraVoucher extends Component
{
    use WithDynamicLayout, WithPagination;

    // ── Active tab ──
    public $activeTab = 'list'; // 'list' or 'create'

    // ── Filter / list properties ──
    public $search = '';
    public $dateFrom = '';
    public $dateTo = '';
    public $directionFilter = '';
    public $perPage = 25;

    // ── Create / Edit form ──
    public $voucherDate;
    public $amount = '';
    public $direction = 'deposit'; // 'deposit' (Cash→Bank) or 'withdrawal' (Bank→Cash)
    public $narration = '';
    public $editingVoucherId = null;

    // ── View ──
    public $viewVoucher = null;

    public function mount()
    {
        $this->dateFrom = now()->startOfMonth()->toDateString();
        $this->dateTo = now()->toDateString();
        $this->voucherDate = now()->toDateString();
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
        $this->amount = '';
        $this->direction = 'deposit';
        $this->narration = '';
        $this->editingVoucherId = null;
        $this->resetValidation();
    }

    public function saveVoucher()
    {
        $this->validate([
            'voucherDate' => 'required|date',
            'amount' => 'required|numeric|min:0.01',
            'direction' => 'required|in:deposit,withdrawal',
            'narration' => 'nullable|string|max:500',
        ], [
            'amount.required' => 'Enter the transfer amount.',
            'amount.min' => 'Amount must be greater than zero.',
        ]);

        try {
            DB::beginTransaction();

            if ($this->editingVoucherId) {
                $oldVoucher = Voucher::findOrFail($this->editingVoucherId);
                AccountingService::reverseVoucher($oldVoucher, 'Modified Contra Voucher');
            }

            $cashId = AccountingService::getSystemAccountId('CASH');
            $bankId = AccountingService::getSystemAccountId('BANK');
            $amt = round((float) $this->amount, 2);

            if ($this->direction === 'deposit') {
                // Cash → Bank (deposit)
                $entries = [
                    ['account_id' => $bankId, 'debit' => $amt, 'credit' => 0, 'narration' => 'Cash deposited to bank'],
                    ['account_id' => $cashId, 'debit' => 0, 'credit' => $amt, 'narration' => 'Cash deposited to bank'],
                ];
                $desc = 'Bank Deposit';
            } else {
                // Bank → Cash (withdrawal)
                $entries = [
                    ['account_id' => $cashId, 'debit' => $amt, 'credit' => 0, 'narration' => 'Cash withdrawn from bank'],
                    ['account_id' => $bankId, 'debit' => 0, 'credit' => $amt, 'narration' => 'Cash withdrawn from bank'],
                ];
                $desc = 'Bank Withdrawal';
            }

            AccountingService::createVoucher(
                Voucher::TYPE_CONTRA,
                Carbon::parse($this->voucherDate),
                $entries,
                $this->narration ?: $desc,
                null,
                null,
                null
            );

            DB::commit();

            $action = $this->editingVoucherId ? 'updated' : 'created';
            $this->js("Swal.fire({icon:'success', title:'Success!', text:'Contra Voucher {$action} successfully!', timer:2000, showConfirmButton:false})");
            $this->resetForm();
            $this->activeTab = 'list';
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Contra Voucher save error: ' . $e->getMessage());
            $this->js("Swal.fire({icon:'error', title:'Error!', text:'" . addslashes($e->getMessage()) . "'})");
        }
    }

    public function editVoucher($id)
    {
        $voucher = Voucher::with('entries.account')->findOrFail($id);

        $this->editingVoucherId = $voucher->id;
        $this->voucherDate = $voucher->date->toDateString();
        $this->narration = $voucher->narration ?? '';
        $this->amount = $voucher->total_amount;

        // Detect direction from entries: if BANK is debited → deposit, else withdrawal
        $bankDebit = $voucher->entries->first(function ($e) {
            return $e->account && $e->account->code === 'BANK' && (float) $e->debit > 0;
        });
        $this->direction = $bankDebit ? 'deposit' : 'withdrawal';

        $this->activeTab = 'create';
    }

    public function viewVoucherDetails($id)
    {
        $this->viewVoucher = Voucher::with(['entries.account', 'creator'])->findOrFail($id);
        $this->js("\$('#contraViewModal').modal('show')");
    }

    public function confirmDelete($id)
    {
        $this->js("
            Swal.fire({
                title: 'Delete Contra Voucher?',
                text: 'This will reverse the cash/bank transfer. This action cannot be undone.',
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
            AccountingService::reverseVoucher($voucher, 'Deleted Contra Voucher');

            $this->js("Swal.fire({icon:'success', title:'Deleted!', text:'Contra Voucher reversed and deleted.', timer:2000, showConfirmButton:false})");
        } catch (\Exception $e) {
            Log::error('Contra Voucher delete error: ' . $e->getMessage());
            $this->js("Swal.fire({icon:'error', title:'Error!', text:'" . addslashes($e->getMessage()) . "'})");
        }
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

    public function updatedDirectionFilter()
    {
        $this->resetPage();
    }

    public function clearFilters()
    {
        $this->search = '';
        $this->dateFrom = now()->startOfMonth()->toDateString();
        $this->dateTo = now()->toDateString();
        $this->directionFilter = '';
        $this->resetPage();
    }

    public function getVouchersProperty()
    {
        $query = Voucher::with(['entries.account', 'creator'])
            ->where('voucher_type', Voucher::TYPE_CONTRA)
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

        // Direction filter: look at entries to determine deposit vs withdrawal
        if ($this->directionFilter === 'deposit') {
            $query->whereHas('entries', function ($q) {
                $q->whereHas('account', function ($q2) {
                    $q2->where('code', 'BANK');
                })->where('debit', '>', 0);
            });
        } elseif ($this->directionFilter === 'withdrawal') {
            $query->whereHas('entries', function ($q) {
                $q->whereHas('account', function ($q2) {
                    $q2->where('code', 'CASH');
                })->where('debit', '>', 0);
            });
        }

        return $query->orderBy('date', 'desc')->orderBy('id', 'desc')->paginate($this->perPage);
    }

    /**
     * Detect direction of a contra voucher from its entries.
     */
    public function getDirection(Voucher $voucher): string
    {
        $bankDebit = $voucher->entries->first(function ($e) {
            return $e->account && $e->account->code === 'BANK' && (float) $e->debit > 0;
        });
        return $bankDebit ? 'deposit' : 'withdrawal';
    }

    public function render()
    {
        return view('livewire.admin.contra-voucher', [
            'vouchers' => $this->vouchers,
        ])->layout($this->layout);
    }
}
