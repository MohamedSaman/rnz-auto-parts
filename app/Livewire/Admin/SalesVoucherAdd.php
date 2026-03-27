<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Livewire\Attributes\Title;
use App\Livewire\Concerns\WithDynamicLayout;
use App\Models\Customer;
use App\Models\ProductDetail;
use App\Models\ProductStock;
use App\Models\ProductPrice;
use App\Models\Sale;
use App\Models\Payment;
use App\Models\Cheque;
use App\Models\User;
use App\Services\VoucherService;
use App\Services\InventoryService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

#[Title('Add Sales Voucher')]
class SalesVoucherAdd extends Component
{
    use WithDynamicLayout;

    // Voucher Header
    public $voucherDate;
    public $voucherNumber;
    public $customerId = '';
    public $customerSearch = '';
    public $billingType = 'cash'; // cash or credit
    public $priceType = 'retail'; // retail, wholesale, distributor
    public $salesmanId = '';
    public $notes = '';

    // Item Grid
    public $items = [];

    // Lookup Data
    public $customers = [];
    public $salesmen = [];
    public $productSearch = '';
    public $productSearchResults = [];

    // UI State
    public $showCustomerDropdown = false;
    public $showProductDropdown = false;
    public $activeItemIndex = null;
    public $showSavedModal = false;
    public $savedSale = null;
    public $showChequeModal = false;

    // Cheque entry state
    public $cheques = [];
    public $tempChequeNumber = '';
    public $tempChequeBankName = '';
    public $tempChequeDate = '';
    public $tempChequeAmount = 0;

    // Customer creation modal
    public $showCustomerModal = false;
    public $newCustomerName = '';
    public $newCustomerPhone = '';
    public $newCustomerEmail = '';
    public $newCustomerAddress = '';
    public $newCustomerType = 'retail';
    public $newBusinessName = '';

    public function mount()
    {
        $this->voucherDate = now()->toDateString();
        $this->voucherNumber = $this->generateNextVoucherNumber();
        $this->tempChequeDate = now()->format('Y-m-d');
        $this->loadCustomers();
        $this->loadSalesmen();
        $this->addEmptyRow();
    }

    private function getProductPriceByType(array $product): float
    {
        $priceType = $this->priceType;

        if ($priceType === 'retail') {
            return (float) ($product['retail_price'] ?? $product['distributor_price'] ?? $product['wholesale_price'] ?? 0);
        }

        if ($priceType === 'wholesale') {
            return (float) ($product['wholesale_price'] ?? $product['distributor_price'] ?? $product['retail_price'] ?? 0);
        }

        return (float) ($product['distributor_price'] ?? $product['wholesale_price'] ?? $product['retail_price'] ?? 0);
    }

    public function generateNextVoucherNumber(): string
    {
        $prefix = 'SV-';
        $date = now()->format('Ymd');
        $last = Sale::where('sale_id', 'like', "SALE-{$date}%")
            ->orderBy('id', 'desc')
            ->first();

        $nextNum = 1;
        if ($last) {
            $parts = explode('-', $last->sale_id);
            $nextNum = intval(end($parts)) + 1;
        }

        return $prefix . $date . '-' . str_pad($nextNum, 4, '0', STR_PAD_LEFT);
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

    // --- Customer Search ---
    public function updatedCustomerSearch($value)
    {
        $this->showCustomerDropdown = strlen($value) > 0;
    }

    public function getFilteredCustomersProperty()
    {
        if (empty($this->customerSearch)) {
            return $this->customers;
        }
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
        $this->dispatch('customer-selected');
    }

    public function getSelectedCustomerProperty()
    {
        if (!$this->customerId) return null;
        return collect($this->customers)->firstWhere('id', $this->customerId);
    }

    // --- Product Search for Item Grid ---
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
            ->with(['price', 'stock', 'brand', 'category'])
            ->limit(15)
            ->get();

        $this->productSearchResults = $results->map(function ($product) {
            $price = $product->price;
            $stock = $product->stock;

            return [
                'id' => $product->id,
                'name' => $product->name,
                'code' => $product->code ?? '',
                'barcode' => $product->barcode ?? '',
                'brand' => $product->brand->name ?? '',
                'category' => $product->category->name ?? '',
                'selling_price' => $price->selling_price ?? 0,
                'wholesale_price' => $price->wholesale_price ?? 0,
                'retail_price' => $price->retail_price ?? 0,
                'distributor_price' => $price->distributor_price ?? 0,
                'available_stock' => $stock->available_stock ?? 0,
                'variant_id' => null,
                'variant_value' => null,
            ];
        })->toArray();

        $this->showProductDropdown = count($this->productSearchResults) > 0;
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
                'retail_price' => $product->price->retail_price ?? 0,
                'wholesale_price' => $product->price->wholesale_price ?? 0,
                'distributor_price' => $product->price->distributor_price ?? 0,
            ];

            $this->items[$index]['rate'] = $this->getProductPriceByType($prices);
            $this->calculateRowTotal($index);
        }
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
            'discount' => '',
            'tax_percentage' => 0,
            'tax_amount' => 0,
        ]);

        $this->calculateRowTotal($index);
        $this->showProductDropdown = false;
        $this->productSearchResults = [];

        // Auto-add new empty row if this is the last row
        if ($index === count($this->items) - 1) {
            $this->addEmptyRow();
        }

        $this->dispatch('product-selected', index: (int) $index);
    }

    // --- Item Grid Management ---
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
            'discount' => '',
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
        // key format: "0.quantity", "1.rate", etc.
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
        return collect($this->items)
            ->where('product_id', '!=', null)
            ->sum(function ($item) {
                return (float) ($item['quantity'] ?? 0) * (float) ($item['rate'] ?? 0);
            });
    }

    public function getTotalDiscountProperty()
    {
        return collect($this->items)
            ->where('product_id', '!=', null)
            ->sum(function ($item) {
                return (float) ($item['discount'] ?? 0) * (float) ($item['quantity'] ?? 0);
            });
    }

    public function getTotalTaxProperty()
    {
        return collect($this->items)
            ->where('product_id', '!=', null)
            ->sum(function ($item) {
                return (float) ($item['tax_amount'] ?? 0);
            });
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

        $existsInDb = Cheque::where('cheque_number', $this->tempChequeNumber)->exists();
        if ($existsInDb) {
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
        $validItems = collect($this->items)->where('product_id', '!=', null)->values()->toArray();

        if (empty($validItems)) {
            session()->flash('error', 'Please add at least one item to the voucher.');
            return null;
        }

        if (!$this->customerId) {
            session()->flash('error', 'Please select a customer (Party Account).');
            return null;
        }

        foreach ($validItems as $item) {
            if ($item['quantity'] > $item['available_stock'] && $item['available_stock'] > 0) {
                session()->flash('error', "Insufficient stock for {$item['product_name']}. Available: {$item['available_stock']}");
                return null;
            }
            if ($item['quantity'] <= 0) {
                session()->flash('error', "Quantity must be greater than zero for {$item['product_name']}.");
                return null;
            }
        }

        return $validItems;
    }

    private function createChequePaymentRecords(Sale $sale): void
    {
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
            'notes' => 'Cheque payment captured during sales voucher creation.',
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

    private function persistVoucher(array $validItems)
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

        $sale = VoucherService::createSalesVoucher($voucherData, $itemsData);

        if ($this->billingType === 'cheque') {
            $this->createChequePaymentRecords($sale);
        }

        $this->savedSale = $sale;
        $this->showSavedModal = true;

        session()->flash('success', 'Sales Voucher ' . $sale->invoice_number . ' saved successfully!');

        $this->resetVoucherForm();
    }

    // --- Save Voucher ---
    public function saveVoucher()
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
            $this->persistVoucher($validItems);
        } catch (\Exception $e) {
            Log::error('Save voucher failed: ' . $e->getMessage());
            session()->flash('error', 'Failed to save voucher: ' . $e->getMessage());
        }
    }

    public function completeChequeVoucherSave()
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
            $this->persistVoucher($validItems);
            $this->showChequeModal = false;
            $this->resetChequeEntries();
        } catch (\Exception $e) {
            Log::error('Cheque voucher save failed: ' . $e->getMessage());
            session()->flash('error', 'Failed to save cheque voucher: ' . $e->getMessage());
        }
    }

    public function resetVoucherForm()
    {
        $this->voucherDate = now()->toDateString();
        $this->voucherNumber = $this->generateNextVoucherNumber();
        $this->customerId = '';
        $this->customerSearch = '';
        $this->billingType = 'cash';
        $this->priceType = 'retail';
        $this->salesmanId = '';
        $this->notes = '';
        $this->items = [];
        $this->addEmptyRow();
        $this->showChequeModal = false;
        $this->resetChequeEntries();
    }

    public function closeSavedModal()
    {
        $this->showSavedModal = false;
        $this->savedSale = null;

        return $this->redirectRoute('admin.sales-voucher-add', navigate: true);
    }

    // --- Customer Modal ---
    public function openCustomerModal()
    {
        $this->resetCustomerForm();
        $this->showCustomerModal = true;
    }

    public function closeCustomerModal()
    {
        $this->showCustomerModal = false;
        $this->resetCustomerForm();
    }

    public function resetCustomerForm()
    {
        $this->newCustomerName = '';
        $this->newCustomerPhone = '';
        $this->newCustomerEmail = '';
        $this->newCustomerAddress = '';
        $this->newCustomerType = 'retail';
        $this->newBusinessName = '';
    }

    public function createCustomer()
    {
        $this->validate([
            'newCustomerName' => 'required|string|max:255',
            'newCustomerPhone' => 'nullable|string|max:20',
            'newCustomerAddress' => 'nullable|string',
        ]);

        $customer = Customer::create([
            'name' => $this->newCustomerName,
            'phone' => $this->newCustomerPhone ?: null,
            'email' => $this->newCustomerEmail ?: null,
            'address' => $this->newCustomerAddress ?: null,
            'type' => $this->newCustomerType,
            'business_name' => $this->newBusinessName ?: null,
            'user_id' => Auth::id(),
        ]);

        $this->loadCustomers();
        $this->selectCustomer($customer->id);
        $this->closeCustomerModal();
    }

    // --- Print ---
    public function printVoucher()
    {
        if ($this->savedSale) {
            return redirect()->route('admin.print.sale', $this->savedSale->id);
        }
    }

    public function render()
    {
        return view('livewire.admin.sales-voucher-add', [
            'filteredCustomers' => $this->filteredCustomers,
        ])->layout($this->layout, ['erpContext' => 'voucher']);
    }
}
