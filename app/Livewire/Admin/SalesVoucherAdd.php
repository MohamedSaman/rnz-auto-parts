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
        $this->loadCustomers();
        $this->loadSalesmen();
        $this->addEmptyRow();
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
                'available_stock' => $stock->available_stock ?? 0,
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

        // Auto-add new empty row if this is the last row
        if ($index === count($this->items) - 1) {
            $this->addEmptyRow();
        }
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
        // key format: "0.quantity", "1.rate", etc.
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
        return collect($this->items)
            ->where('product_id', '!=', null)
            ->sum(function ($item) {
                return ($item['quantity'] ?? 0) * ($item['rate'] ?? 0);
            });
    }

    public function getTotalDiscountProperty()
    {
        return collect($this->items)
            ->where('product_id', '!=', null)
            ->sum(function ($item) {
                return ($item['discount'] ?? 0) * ($item['quantity'] ?? 0);
            });
    }

    public function getTotalTaxProperty()
    {
        return collect($this->items)
            ->where('product_id', '!=', null)
            ->sum(function ($item) {
                return $item['tax_amount'] ?? 0;
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

    // --- Save Voucher ---
    public function saveVoucher()
    {
        // Validate
        $validItems = collect($this->items)->where('product_id', '!=', null)->values()->toArray();

        if (empty($validItems)) {
            session()->flash('error', 'Please add at least one item to the voucher.');
            return;
        }

        if (!$this->customerId) {
            session()->flash('error', 'Please select a customer (Party Account).');
            return;
        }

        // Validate stock availability
        foreach ($validItems as $item) {
            if ($item['quantity'] > $item['available_stock'] && $item['available_stock'] > 0) {
                session()->flash('error', "Insufficient stock for {$item['product_name']}. Available: {$item['available_stock']}");
                return;
            }
            if ($item['quantity'] <= 0) {
                session()->flash('error', "Quantity must be greater than zero for {$item['product_name']}.");
                return;
            }
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

            $sale = VoucherService::createSalesVoucher($voucherData, $itemsData);

            $this->savedSale = $sale;
            $this->showSavedModal = true;

            session()->flash('success', 'Sales Voucher ' . $sale->invoice_number . ' saved successfully!');

            // Reset form for next voucher
            $this->resetVoucherForm();
        } catch (\Exception $e) {
            Log::error('Save voucher failed: ' . $e->getMessage());
            session()->flash('error', 'Failed to save voucher: ' . $e->getMessage());
        }
    }

    public function resetVoucherForm()
    {
        $this->voucherDate = now()->toDateString();
        $this->voucherNumber = $this->generateNextVoucherNumber();
        $this->customerId = '';
        $this->customerSearch = '';
        $this->billingType = 'cash';
        $this->salesmanId = '';
        $this->notes = '';
        $this->items = [];
        $this->addEmptyRow();
    }

    public function closeSavedModal()
    {
        $this->showSavedModal = false;
        $this->savedSale = null;
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
