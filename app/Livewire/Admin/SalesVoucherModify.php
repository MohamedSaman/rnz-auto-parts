<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Livewire\Attributes\Title;
use App\Livewire\Concerns\WithDynamicLayout;
use App\Models\Cheque;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\ProductDetail;
use App\Models\Sale;
use App\Models\User;
use App\Services\VoucherService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

#[Title('Modify Sales Voucher')]
class SalesVoucherModify extends Component
{
    use WithDynamicLayout;

    // Search
    public $searchQuery = '';
    public $searchType = 'voucher_number'; // voucher_number, date, customer
    public $searchResults = [];
    public $showSearchResults = false;

    // Loaded Voucher
    public $loadedSaleId = null;
    public $voucherDate;
    public $voucherNumber;
    public $customerId = '';
    public $customerSearch = '';
    public $billingType = 'cash';
    public $priceType = 'retail'; // retail, wholesale, distributor
    public $salesmanId = '';
    public $notes = '';
    public $items = [];

    // Lookup Data
    public $customers = [];
    public $salesmen = [];
    public $productSearchResults = [];
    public $activeItemIndex = null;
    public $showProductDropdown = false;
    public $showCustomerDropdown = false;

    // UI
    public $isLoaded = false;
    public $showSavedModal = false;
    public $savedSale = null;
    public $showChequeModal = false;

    // Cheque entry state
    public $cheques = [];
    public $tempChequeNumber = '';
    public $tempChequeBankName = '';
    public $tempChequeDate = '';
    public $tempChequeAmount = 0;

    public function mount($saleId = null)
    {
        $this->loadCustomers();
        $this->loadSalesmen();
        $this->tempChequeDate = now()->format('Y-m-d');

        $saleId = $saleId ?: request()->query('saleId') ?: request()->query('load');

        if ($saleId) {
            $this->loadVoucher($saleId);
        }
    }

    private function buildVoucherSearchQuery()
    {
        $query = Sale::with(['customer', 'items'])
            ->where('status', '!=', 'cancelled');

        if (!empty($this->searchQuery)) {
            $search = $this->searchQuery;

            switch ($this->searchType) {
                case 'voucher_number':
                    $query->where(function ($q) use ($search) {
                        $q->where('invoice_number', 'like', "%{$search}%")
                            ->orWhere('sale_id', 'like', "%{$search}%");
                    });
                    break;
                case 'date':
                    $query->whereDate('created_at', $search);
                    break;
                case 'customer':
                    $query->whereHas('customer', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%")
                            ->orWhere('business_name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    });
                    break;
            }
        }

        return $query;
    }

    public function getRecentVouchersProperty()
    {
        return $this->buildVoucherSearchQuery()
            ->orderBy('created_at', 'desc')
            ->limit(25)
            ->get();
    }

    private function getProductPriceByType(array $product): float
    {
        if ($this->priceType === 'retail') {
            return (float) ($product['retail_price'] ?? $product['distributor_price'] ?? $product['wholesale_price'] ?? 0);
        }

        if ($this->priceType === 'wholesale') {
            return (float) ($product['wholesale_price'] ?? $product['distributor_price'] ?? $product['retail_price'] ?? 0);
        }

        return (float) ($product['distributor_price'] ?? $product['wholesale_price'] ?? $product['retail_price'] ?? 0);
    }

    public function loadCustomers()
    {
        $this->customers = Customer::orderBy('name')->get()->toArray();
    }

    public function loadSalesmen()
    {
        $this->salesmen = User::where('role', 'staff')
            ->where(function ($q) {
                $q->where('staff_type', 'salesman')
                    ->orWhere('staff_type', 'shop_staff');
            })
            ->orderBy('name')
            ->get(['id', 'name'])
            ->toArray();
    }

    // --- Search Vouchers ---
    public function searchVouchers()
    {
        if (empty($this->searchQuery)) {
            $this->searchResults = [];
            return;
        }

        $this->searchResults = $this->buildVoucherSearchQuery()->orderBy('created_at', 'desc')
            ->limit(20)
            ->get()
            ->toArray();

        $this->showSearchResults = true;
    }

    public function updatedSearchQuery()
    {
        if (strlen($this->searchQuery) >= 2) {
            $this->searchVouchers();
        } else {
            $this->searchResults = [];
            $this->showSearchResults = false;
        }
    }

    // --- Load Voucher for Editing ---
    public function loadVoucher($saleId)
    {
        $sale = Sale::with(['items', 'customer'])->find($saleId);

        if (!$sale) {
            session()->flash('error', 'Voucher not found.');
            return;
        }

        $this->loadedSaleId = $sale->id;
        $this->voucherDate = $sale->voucher_date ?? $sale->created_at->toDateString();
        $this->voucherNumber = $sale->invoice_number;
        $this->customerId = $sale->customer_id;
        $this->billingType = $sale->billing_type ?? ($sale->payment_status === 'paid' ? 'cash' : 'credit');

        // Backward compatibility: old records may be cheque paid but billing_type stored as cash/credit.
        $hasChequePayment = Payment::where('sale_id', $sale->id)
            ->where('payment_method', 'cheque')
            ->exists();
        if ($hasChequePayment) {
            $this->billingType = 'cheque';
        }
        $this->salesmanId = $sale->salesman_id ?? '';
        $this->notes = $sale->notes ?? '';

        // Set customer search text
        $customer = collect($this->customers)->firstWhere('id', $sale->customer_id);
        $this->customerSearch = $customer ? ($customer['business_name'] ?? $customer['name']) . ' - ' . ($customer['phone'] ?? '') : '';

        // Load items
        $this->items = [];
        foreach ($sale->items as $item) {
            $product = ProductDetail::with(['stock', 'price'])->find($item->product_id);
            $availableStock = $product && $product->stock ? $product->stock->available_stock : 0;

            $qty = (int) $item->quantity;
            $rate = (float) $item->unit_price;
            $disc = (float) $item->discount_per_unit;
            $taxPct = 0;
            $lineTotal = $qty * $rate;
            $lineDiscount = $disc * $qty;
            $taxableAmount = $lineTotal - $lineDiscount;
            $taxAmount = $taxableAmount * ($taxPct / 100);
            $amount = $taxableAmount + $taxAmount;

            $this->items[] = [
                'product_id' => $item->product_id,
                'product_search' => ($item->product_name ?? '') . ' [' . ($item->product_code ?? '') . ']',
                'product_name' => $item->product_name ?? '',
                'product_code' => $item->product_code ?? '',
                'sku' => $item->product_code ?? '',
                'quantity' => $qty,
                'rate' => $rate,
                'discount' => $disc,
                'tax_percentage' => 0,
                'tax_amount' => 0,
                'amount' => round($amount, 2),
                'available_stock' => $availableStock + $qty, // Add back the qty that will be restored
                'variant_id' => $item->variant_id,
                'variant_value' => $item->variant_value,
            ];
        }

        // Add empty row
        $this->addEmptyRow();

        if ($this->billingType === 'cheque') {
            $this->loadChequeEntries($sale->id);
        } else {
            $this->resetChequeEntries();
        }

        $this->isLoaded = true;
        $this->showSearchResults = false;
        $this->searchQuery = '';
    }

    private function loadChequeEntries(int $saleId): void
    {
        $payment = Payment::with('cheques')
            ->where('sale_id', $saleId)
            ->where('payment_method', 'cheque')
            ->latest('id')
            ->first();

        $this->cheques = [];

        if ($payment) {
            $this->cheques = $payment->cheques->map(function ($cheque) {
                return [
                    'number' => $cheque->cheque_number,
                    'bank_name' => $cheque->bank_name,
                    'date' => optional($cheque->cheque_date)->format('Y-m-d') ?? (string) $cheque->cheque_date,
                    'amount' => (float) $cheque->cheque_amount,
                ];
            })->toArray();
        }

        $this->tempChequeDate = now()->format('Y-m-d');
        $this->tempChequeAmount = max(0, $this->remainingChequeAmount);
    }

    // --- Customer methods (same as Add) ---
    public function updatedCustomerSearch($value)
    {
        $this->showCustomerDropdown = strlen($value) > 0;
    }

    public function getFilteredCustomersProperty()
    {
        if (empty($this->customerSearch)) return $this->customers;
        $search = strtolower($this->customerSearch);
        return collect($this->customers)->filter(function ($c) use ($search) {
            return str_contains(strtolower($c['name']), $search)
                || str_contains(strtolower($c['phone'] ?? ''), $search)
                || str_contains(strtolower($c['business_name'] ?? ''), $search);
        })->values()->toArray();
    }

    public function selectCustomer($id)
    {
        $this->customerId = $id;
        $customer = collect($this->customers)->firstWhere('id', $id);
        $this->customerSearch = $customer ? ($customer['business_name'] ?? $customer['name']) . ' - ' . ($customer['phone'] ?? '') : '';
        $this->showCustomerDropdown = false;
    }

    public function getSelectedCustomerProperty()
    {
        if (!$this->customerId) return null;
        return collect($this->customers)->firstWhere('id', $this->customerId);
    }

    // --- Product Search ---
    public function searchProducts($index)
    {
        $this->activeItemIndex = $index;
        $search = $this->items[$index]['product_search'] ?? '';

        if (strlen($search) < 2) {
            $this->productSearchResults = [];
            $this->showProductDropdown = false;
            return;
        }

        $results = ProductDetail::where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%")
                ->orWhere('barcode', 'like', "%{$search}%");
        })
            ->with(['price', 'stock'])
            ->limit(15)
            ->get();

        $this->productSearchResults = $results->map(function ($product) {
            return [
                'id' => $product->id,
                'name' => $product->name,
                'code' => $product->code ?? '',
                'barcode' => $product->barcode ?? '',
                'selling_price' => $product->price->selling_price ?? 0,
                'retail_price' => $product->price->retail_price ?? 0,
                'wholesale_price' => $product->price->wholesale_price ?? 0,
                'distributor_price' => $product->price->distributor_price ?? 0,
                'available_stock' => $product->stock->available_stock ?? 0,
                'variant_id' => null,
                'variant_value' => null,
            ];
        })->toArray();

        $this->showProductDropdown = count($this->productSearchResults) > 0;
    }

    public function selectProduct($index, $productId)
    {
        $product = collect($this->productSearchResults)->firstWhere('id', $productId);
        if (!$product) return;

        $this->items[$index] = array_merge($this->items[$index], [
            'product_id' => $product['id'],
            'product_search' => $product['name'] . ' [' . $product['code'] . ']',
            'product_name' => $product['name'],
            'product_code' => $product['code'],
            'sku' => $product['barcode'] ?? $product['code'],
            'rate' => $this->getProductPriceByType($product),
            'available_stock' => $product['available_stock'],
            'variant_id' => $product['variant_id'],
            'variant_value' => $product['variant_value'],
            'quantity' => 1,
            'discount' => 0,
            'tax_percentage' => 0,
            'tax_amount' => 0,
        ]);

        $this->calculateRowTotal($index);
        $this->showProductDropdown = false;
        $this->productSearchResults = [];

        if ($index === count($this->items) - 1) {
            $this->addEmptyRow();
        }
    }

    public function updatedPriceType()
    {
        foreach ($this->items as $index => $item) {
            if (empty($item['product_id'])) {
                continue;
            }

            $product = ProductDetail::with('price')->find($item['product_id']);
            if (!$product) {
                continue;
            }

            $prices = [
                'selling_price' => $product->price->selling_price ?? 0,
                'retail_price' => $product->price->retail_price ?? 0,
                'wholesale_price' => $product->price->wholesale_price ?? 0,
                'distributor_price' => $product->price->distributor_price ?? 0,
            ];

            $this->items[$index]['rate'] = $this->getProductPriceByType($prices);
            $this->calculateRowTotal($index);
        }
    }

    // --- Item Grid ---
    public function addEmptyRow()
    {
        $this->items[] = [
            'product_id' => null,
            'product_search' => '',
            'product_name' => '',
            'product_code' => '',
            'sku' => '',
            'quantity' => 1,
            'rate' => 0,
            'discount' => 0,
            'tax_percentage' => 0,
            'tax_amount' => 0,
            'amount' => 0,
            'available_stock' => 0,
            'variant_id' => null,
            'variant_value' => null,
        ];
    }

    public function removeRow($index)
    {
        if (count($this->items) <= 1) return;
        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    public function updatedItems($value, $key)
    {
        $parts = explode('.', $key);
        if (count($parts) === 2) {
            $index = (int) $parts[0];
            $field = $parts[1];

            if (in_array($field, ['quantity', 'rate', 'discount'])) {
                $this->calculateRowTotal($index);
            }
            if ($field === 'product_search') {
                $this->searchProducts($index);
            }
        }
    }

    public function calculateRowTotal($index)
    {
        if (!isset($this->items[$index])) return;

        $qty = max(0, (float) ($this->items[$index]['quantity'] ?? 0));
        $rate = max(0, (float) ($this->items[$index]['rate'] ?? 0));
        $discount = max(0, (float) ($this->items[$index]['discount'] ?? 0));
        $taxPct = 0;

        $lineTotal = $qty * $rate;
        $lineDiscount = $discount * $qty;
        $taxableAmount = $lineTotal - $lineDiscount;
        $taxAmount = $taxableAmount * ($taxPct / 100);
        $amount = $taxableAmount + $taxAmount;

        $this->items[$index]['tax_percentage'] = 0;
        $this->items[$index]['tax_amount'] = 0;
        $this->items[$index]['amount'] = round($amount, 2);
    }

    // --- Totals ---
    public function getSubtotalProperty()
    {
        return collect($this->items)->where('product_id', '!=', null)
            ->sum(fn($i) => (float) ($i['quantity'] ?? 0) * (float) ($i['rate'] ?? 0));
    }

    public function getTotalDiscountProperty()
    {
        return collect($this->items)->where('product_id', '!=', null)
            ->sum(fn($i) => (float) ($i['discount'] ?? 0) * (float) ($i['quantity'] ?? 0));
    }

    public function getTotalTaxProperty()
    {
        return collect($this->items)->where('product_id', '!=', null)
            ->sum(fn($i) => (float) ($i['tax_amount'] ?? 0));
    }

    public function getGrandTotalProperty()
    {
        return $this->subtotal - $this->totalDiscount + $this->totalTax;
    }

    public function getItemCountProperty()
    {
        return collect($this->items)->where('product_id', '!=', null)->count();
    }

    public function getTotalChequeAmountProperty()
    {
        return (float) collect($this->cheques)->sum('amount');
    }

    public function getRemainingChequeAmountProperty()
    {
        return round((float) $this->grandTotal - (float) $this->totalChequeAmount, 2);
    }

    public function updatedBillingType($value)
    {
        if ($value !== 'cheque') {
            $this->showChequeModal = false;
            $this->resetChequeEntries();
        } elseif ($this->loadedSaleId) {
            $this->loadChequeEntries($this->loadedSaleId);
        }
    }

    public function addCheque()
    {
        if (!$this->tempChequeNumber || !$this->tempChequeBankName || !$this->tempChequeDate) {
            session()->flash('error', 'Please fill cheque number, bank, and cheque date.');
            return;
        }

        $amount = (float) $this->tempChequeAmount;
        if ($amount <= 0) {
            session()->flash('error', 'Cheque amount must be greater than zero.');
            return;
        }

        $existsInLocal = collect($this->cheques)->contains(function ($chq) {
            return strcasecmp($chq['number'], $this->tempChequeNumber) === 0;
        });

        if ($existsInLocal) {
            session()->flash('error', 'Cheque number already added in this voucher.');
            return;
        }

        $duplicateQuery = Cheque::where('cheque_number', $this->tempChequeNumber);
        if ($this->loadedSaleId) {
            $duplicateQuery->whereHas('payment', function ($q) {
                $q->where('sale_id', '!=', $this->loadedSaleId);
            });
        }

        if ($duplicateQuery->exists()) {
            session()->flash('error', 'Cheque number already exists.');
            return;
        }

        if ($amount > ($this->remainingChequeAmount + 0.01)) {
            session()->flash('error', 'Cheque amount cannot exceed remaining amount.');
            return;
        }

        $this->cheques[] = [
            'number' => $this->tempChequeNumber,
            'bank_name' => $this->tempChequeBankName,
            'date' => $this->tempChequeDate,
            'amount' => round($amount, 2),
        ];

        $this->tempChequeNumber = '';
        $this->tempChequeBankName = '';
        $this->tempChequeDate = now()->format('Y-m-d');
        $this->tempChequeAmount = max(0, $this->remainingChequeAmount);
    }

    public function removeCheque($index)
    {
        unset($this->cheques[$index]);
        $this->cheques = array_values($this->cheques);
        $this->tempChequeAmount = max(0, $this->remainingChequeAmount);
    }

    public function closeChequeModal()
    {
        $this->showChequeModal = false;
    }

    private function resetChequeEntries()
    {
        $this->cheques = [];
        $this->tempChequeNumber = '';
        $this->tempChequeBankName = '';
        $this->tempChequeDate = now()->format('Y-m-d');
        $this->tempChequeAmount = 0;
    }

    private function validateVoucherItemsAndCustomer(): ?array
    {
        if (!$this->loadedSaleId) {
            session()->flash('error', 'No voucher loaded.');
            return null;
        }

        $validItems = collect($this->items)->where('product_id', '!=', null)->values()->toArray();

        if (empty($validItems)) {
            session()->flash('error', 'Please add at least one item.');
            return null;
        }

        if (!$this->customerId) {
            session()->flash('error', 'Please select a customer.');
            return null;
        }

        return $validItems;
    }

    private function syncChequePaymentRecords(Sale $sale): void
    {
        $existingPayments = Payment::where('sale_id', $sale->id)
            ->where('payment_method', 'cheque')
            ->get();

        foreach ($existingPayments as $payment) {
            Cheque::where('payment_id', $payment->id)->delete();
            $payment->delete();
        }

        if ($this->billingType !== 'cheque') {
            return;
        }

        $reference = 'SV-CHQ-' . $sale->invoice_number;
        $bankNames = collect($this->cheques)->pluck('bank_name')->unique()->implode(', ');

        $payment = Payment::create([
            'sale_id' => $sale->id,
            'customer_id' => $sale->customer_id,
            'amount' => (float) $sale->total_amount,
            'payment_method' => 'cheque',
            'payment_reference' => $reference,
            'bank_name' => $bankNames ?: null,
            'is_completed' => false,
            'payment_date' => now(),
            'status' => 'pending',
            'created_by' => Auth::id(),
            'notes' => 'Cheque payment updated during sales voucher modification.',
        ]);

        foreach ($this->cheques as $cheque) {
            Cheque::create([
                'cheque_number' => $cheque['number'],
                'cheque_date' => $cheque['date'],
                'bank_name' => $cheque['bank_name'],
                'cheque_amount' => $cheque['amount'],
                'status' => 'pending',
                'customer_id' => $sale->customer_id,
                'payment_id' => $payment->id,
            ]);
        }
    }

    private function persistVoucherUpdate(array $validItems)
    {
        $voucherData = [
            'date' => $this->voucherDate,
            'customer_id' => $this->customerId,
            'billing_type' => $this->billingType,
            'salesman_id' => $this->salesmanId ?: null,
            'notes' => $this->notes,
        ];

        $itemsData = collect($validItems)->map(function ($item) {
            return [
                'product_id' => $item['product_id'],
                'variant_id' => $item['variant_id'] ?? null,
                'variant_value' => $item['variant_value'] ?? null,
                'quantity' => (int) $item['quantity'],
                'rate' => (float) $item['rate'],
                'discount' => (float) ($item['discount'] ?? 0),
                'tax_percentage' => 0,
                'tax_amount' => 0,
            ];
        })->toArray();

        $sale = VoucherService::modifySalesVoucher($this->loadedSaleId, $voucherData, $itemsData);

        $this->syncChequePaymentRecords($sale);

        $this->savedSale = $sale;
        $this->showSavedModal = true;

        session()->flash('success', 'Voucher ' . $sale->invoice_number . ' updated successfully!');
    }

    // --- Update Voucher ---
    public function updateVoucher()
    {
        $validItems = $this->validateVoucherItemsAndCustomer();
        if ($validItems === null) return;

        if ($this->billingType === 'cheque') {
            $this->showChequeModal = true;
            $this->tempChequeDate = now()->format('Y-m-d');
            $this->tempChequeAmount = max(0, $this->remainingChequeAmount);
            return;
        }

        try {
            $this->persistVoucherUpdate($validItems);
        } catch (\Exception $e) {
            Log::error('Update voucher failed: ' . $e->getMessage());
            session()->flash('error', 'Failed to update voucher: ' . $e->getMessage());
        }
    }

    public function completeChequeVoucherUpdate()
    {
        $validItems = $this->validateVoucherItemsAndCustomer();
        if ($validItems === null) return;

        if (empty($this->cheques)) {
            session()->flash('error', 'Please add at least one cheque.');
            return;
        }

        $remaining = round($this->remainingChequeAmount, 2);
        if (abs($remaining) > 0.01) {
            session()->flash('error', 'Total cheque amount must exactly match grand total.');
            return;
        }

        try {
            $this->persistVoucherUpdate($validItems);
            $this->showChequeModal = false;
        } catch (\Exception $e) {
            Log::error('Cheque voucher update failed: ' . $e->getMessage());
            session()->flash('error', 'Failed to update cheque voucher: ' . $e->getMessage());
        }
    }

    public function closeSavedModal()
    {
        $this->showSavedModal = false;
        $this->savedSale = null;

        return $this->redirectRoute('admin.sales-voucher-modify', navigate: true);
    }

    public function clearVoucher()
    {
        $this->loadedSaleId = null;
        $this->isLoaded = false;
        $this->items = [];
        $this->voucherDate = '';
        $this->voucherNumber = '';
        $this->customerId = '';
        $this->customerSearch = '';
        $this->billingType = 'cash';
        $this->salesmanId = '';
        $this->notes = '';
        $this->searchQuery = '';
        $this->searchResults = [];
        $this->priceType = 'retail';
        $this->showChequeModal = false;
        $this->resetChequeEntries();
    }

    public function render()
    {
        return view('livewire.admin.sales-voucher-modify', [
            'filteredCustomers' => $this->filteredCustomers,
            'recentVouchers' => $this->recentVouchers,
        ])->layout($this->layout, ['erpContext' => 'voucher']);
    }
}
