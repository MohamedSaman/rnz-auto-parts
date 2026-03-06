<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Livewire\Attributes\Title;
use App\Livewire\Concerns\WithDynamicLayout;
use App\Models\Customer;
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

    public function mount($saleId = null)
    {
        $this->loadCustomers();
        $this->loadSalesmen();

        if ($saleId) {
            $this->loadVoucher($saleId);
        }
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

        $query = Sale::with(['customer', 'items'])
            ->where('sale_type', 'admin')
            ->where('status', '!=', 'cancelled');

        switch ($this->searchType) {
            case 'voucher_number':
                $query->where(function ($q) {
                    $q->where('invoice_number', 'like', "%{$this->searchQuery}%")
                        ->orWhere('sale_id', 'like', "%{$this->searchQuery}%");
                });
                break;
            case 'date':
                $query->whereDate('created_at', $this->searchQuery);
                break;
            case 'customer':
                $query->whereHas('customer', function ($q) {
                    $q->where('name', 'like', "%{$this->searchQuery}%")
                        ->orWhere('business_name', 'like', "%{$this->searchQuery}%")
                        ->orWhere('phone', 'like', "%{$this->searchQuery}%");
                });
                break;
        }

        $this->searchResults = $query->orderBy('created_at', 'desc')
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
            $taxPct = (float) ($item->tax_percentage ?? 0);
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
                'tax_percentage' => $taxPct,
                'tax_amount' => round($taxAmount, 2),
                'amount' => round($amount, 2),
                'available_stock' => $availableStock + $qty, // Add back the qty that will be restored
                'variant_id' => $item->variant_id,
                'variant_value' => $item->variant_value,
            ];
        }

        // Add empty row
        $this->addEmptyRow();

        $this->isLoaded = true;
        $this->showSearchResults = false;
        $this->searchQuery = '';
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
            'rate' => $product['selling_price'],
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

            if (in_array($field, ['quantity', 'rate', 'discount', 'tax_percentage'])) {
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
        $taxPct = max(0, (float) ($this->items[$index]['tax_percentage'] ?? 0));

        $lineTotal = $qty * $rate;
        $lineDiscount = $discount * $qty;
        $taxableAmount = $lineTotal - $lineDiscount;
        $taxAmount = $taxableAmount * ($taxPct / 100);
        $amount = $taxableAmount + $taxAmount;

        $this->items[$index]['tax_amount'] = round($taxAmount, 2);
        $this->items[$index]['amount'] = round($amount, 2);
    }

    // --- Totals ---
    public function getSubtotalProperty()
    {
        return collect($this->items)->where('product_id', '!=', null)
            ->sum(fn($i) => ($i['quantity'] ?? 0) * ($i['rate'] ?? 0));
    }

    public function getTotalDiscountProperty()
    {
        return collect($this->items)->where('product_id', '!=', null)
            ->sum(fn($i) => ($i['discount'] ?? 0) * ($i['quantity'] ?? 0));
    }

    public function getTotalTaxProperty()
    {
        return collect($this->items)->where('product_id', '!=', null)
            ->sum(fn($i) => $i['tax_amount'] ?? 0);
    }

    public function getGrandTotalProperty()
    {
        return $this->subtotal - $this->totalDiscount + $this->totalTax;
    }

    public function getItemCountProperty()
    {
        return collect($this->items)->where('product_id', '!=', null)->count();
    }

    // --- Update Voucher ---
    public function updateVoucher()
    {
        if (!$this->loadedSaleId) {
            session()->flash('error', 'No voucher loaded.');
            return;
        }

        $validItems = collect($this->items)->where('product_id', '!=', null)->values()->toArray();

        if (empty($validItems)) {
            session()->flash('error', 'Please add at least one item.');
            return;
        }

        if (!$this->customerId) {
            session()->flash('error', 'Please select a customer.');
            return;
        }

        try {
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
                    'tax_percentage' => (float) ($item['tax_percentage'] ?? 0),
                    'tax_amount' => (float) ($item['tax_amount'] ?? 0),
                ];
            })->toArray();

            $sale = VoucherService::modifySalesVoucher($this->loadedSaleId, $voucherData, $itemsData);

            $this->savedSale = $sale;
            $this->showSavedModal = true;

            session()->flash('success', 'Voucher ' . $sale->invoice_number . ' updated successfully!');
        } catch (\Exception $e) {
            Log::error('Update voucher failed: ' . $e->getMessage());
            session()->flash('error', 'Failed to update voucher: ' . $e->getMessage());
        }
    }

    public function closeSavedModal()
    {
        $this->showSavedModal = false;
        $this->savedSale = null;
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
    }

    public function render()
    {
        return view('livewire.admin.sales-voucher-modify', [
            'filteredCustomers' => $this->filteredCustomers,
        ])->layout($this->layout, ['erpContext' => 'voucher']);
    }
}
