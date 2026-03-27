<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Livewire\Attributes\Title;
use App\Livewire\Concerns\WithDynamicLayout;
use App\Models\ProductSupplier;
use App\Models\ProductDetail;
use App\Models\ProductBatch;
use App\Models\ProductPrice;
use App\Models\ProductStock;
use App\Models\PurchaseOrder;
use App\Services\VoucherService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

#[Title('Modify Purchase Voucher')]
class PurchaseVoucherModify extends Component
{
    use WithDynamicLayout;

    // Search
    public $searchQuery = '';
    public $searchType = 'voucher_number'; // voucher_number, date, supplier
    public $searchResults = [];
    public $showSearchResults = false;

    // Loaded Voucher
    public $loadedOrderId = null;
    public $voucherDate;
    public $voucherNumber;
    public $invoiceNumber;
    public $supplierId = '';
    public $supplierSearch = '';
    public $billingType = 'cash';
    public $transportCost = 0;
    public $notes = '';
    public $items = [];

    // Lookup Data
    public $suppliers = [];
    public $productSearchResults = [];
    public $activeItemIndex = null;
    public $showProductDropdown = false;
    public $showSupplierDropdown = false;

    // UI
    public $isLoaded = false;
    public $showSavedModal = false;
    public $savedOrder = null;

    protected $listeners = ['refreshComponent' => '$refresh'];

    public function mount($load = null)
    {
        $this->suppliers = ProductSupplier::orderBy('name')->get();
        $this->voucherDate = now()->toDateString();
        $this->addEmptyRow();

        $load = $load ?: request()->query('load') ?: request()->query('orderId');
        if ($load) {
            $this->loadVoucher((int) $load);
        }
    }

    private function buildVoucherSearchQuery()
    {
        $query = PurchaseOrder::with(['supplier', 'items'])
            ->whereNotIn('status', ['cancelled']);

        if (!empty($this->searchQuery)) {
            $search = $this->searchQuery;

            if ($this->searchType === 'voucher_number') {
                $query->where(function ($q) use ($search) {
                    $q->where('order_code', 'like', '%' . $search . '%')
                        ->orWhere('invoice_number', 'like', '%' . $search . '%');
                });
            } elseif ($this->searchType === 'date') {
                $query->whereDate('order_date', $search);
            } elseif ($this->searchType === 'supplier') {
                $query->whereHas('supplier', function ($q) use ($search) {
                    $q->where('name', 'like', '%' . $search . '%');
                });
            }
        }

        return $query;
    }

    public function getRecentVouchersProperty()
    {
        return $this->buildVoucherSearchQuery()
            ->orderByDesc('order_date')
            ->orderByDesc('id')
            ->limit(25)
            ->get();
    }

    // ═══ Search & Load ═══

    public function searchVouchers()
    {
        $this->searchResults = $this->buildVoucherSearchQuery()->orderByDesc('order_date')->limit(20)->get();
        $this->showSearchResults = true;
    }

    public function loadVoucher(int $orderId)
    {
        $order = PurchaseOrder::with(['items.product', 'supplier'])->find($orderId);
        if (!$order) {
            $this->js("Swal.fire('Error', 'Voucher not found.', 'error');");
            return;
        }

        $this->loadedOrderId = $order->id;
        $this->voucherDate = $order->order_date;
        $this->voucherNumber = $order->order_code;
        $this->invoiceNumber = $order->invoice_number;
        $this->supplierId = $order->supplier_id;
        $this->supplierSearch = $order->supplier->name ?? '';
        $this->billingType = $order->payment_type ?? 'cash';
        $this->transportCost = floatval($order->transport_cost ?? 0);

        // Load items
        $this->items = [];
        foreach ($order->items as $oi) {
            $product = $oi->product;
            $this->items[] = [
                'product_id' => $oi->product_id,
                'variant_id' => $oi->variant_id,
                'variant_value' => $oi->variant_value,
                'search' => $product ? $product->name . ($oi->variant_value ? ' - ' . $oi->variant_value : '') : '',
                'name' => $product ? $product->name : '',
                'code' => $product ? $product->code : '',
                'quantity' => intval($oi->quantity),
                'free_qty' => intval($oi->free_qty ?? 0),
                'rate' => floatval($oi->unit_price),
                'discount' => floatval($oi->discount ?? 0),
                'tax_percentage' => 0,
                'tax_amount' => 0,
                'amount' => floatval($oi->quantity) * floatval($oi->unit_price),
                'available_stock' => InventoryService::getAvailableStock($oi->product_id, $oi->variant_id, $oi->variant_value) ?? 0,
            ];
        }

        // Add empty row at end
        $this->addEmptyRow();

        $this->isLoaded = true;
        $this->showSearchResults = false;

        $this->dispatch('voucher-loaded');
    }

    // ═══ Supplier Search ═══

    public function updatedSupplierSearch()
    {
        if (strlen($this->supplierSearch) >= 2) {
            $this->suppliers = ProductSupplier::where('name', 'like', '%' . $this->supplierSearch . '%')
                ->orWhere('business_name', 'like', '%' . $this->supplierSearch . '%')
                ->orderBy('name')
                ->limit(10)
                ->get();
            $this->showSupplierDropdown = true;
        } else {
            $this->showSupplierDropdown = false;
        }
    }

    public function selectSupplier($id)
    {
        $supplier = ProductSupplier::find($id);
        if ($supplier) {
            $this->supplierId = $supplier->id;
            $this->supplierSearch = $supplier->name;
        }
        $this->showSupplierDropdown = false;
    }

    // ═══ Item Grid ═══

    public function addEmptyRow()
    {
        $this->items[] = [
            'product_id' => null,
            'variant_id' => null,
            'variant_value' => null,
            'search' => '',
            'name' => '',
            'code' => '',
            'quantity' => 1,
            'free_qty' => 0,
            'rate' => 0,
            'discount' => 0,
            'tax_percentage' => 0,
            'tax_amount' => 0,
            'amount' => 0,
            'available_stock' => 0,
        ];
    }

    public function searchProducts($index)
    {
        $this->activeItemIndex = $index;
        $term = $this->items[$index]['search'] ?? '';

        if (strlen($term) < 2) {
            $this->productSearchResults = [];
            $this->showProductDropdown = false;
            return;
        }

        $matches = ProductDetail::where('name', 'like', '%' . $term . '%')
            ->orWhere('code', 'like', '%' . $term . '%')
            ->with(['stock', 'price', 'variant'])
            ->limit(10)
            ->get();

        $results = [];
        foreach ($matches as $p) {
            if (!empty($p->variant_id) && $p->variant && is_array($p->variant->variant_values) && count($p->variant->variant_values) > 0) {
                foreach ($p->variant->variant_values as $val) {
                    $supplierPrice = ProductPrice::where('product_id', $p->id)->where('variant_value', $val)->value('supplier_price');
                    if (!$supplierPrice) {
                        $latestBatch = ProductBatch::where('product_id', $p->id)->where('status', 'active')
                            ->orderBy('received_date', 'desc')->first();
                        $supplierPrice = ($latestBatch && floatval($latestBatch->supplier_price) > 0)
                            ? floatval($latestBatch->supplier_price)
                            : ($p->price->supplier_price ?? 0);
                    }
                    $available = ProductStock::where('product_id', $p->id)->where('variant_value', $val)->value('available_stock') ?? 0;
                    $results[] = [
                        'product_id' => $p->id,
                        'name' => $p->name,
                        'code' => $p->code,
                        'variant_id' => $p->variant_id,
                        'variant_value' => (string) $val,
                        'variant_name' => $p->variant->variant_name ?? null,
                        'supplier_price' => $supplierPrice ?: 0,
                        'available_stock' => $available,
                    ];
                }
            } else {
                $supplierPrice = $p->price->supplier_price ?? 0;
                $latestBatch = ProductBatch::where('product_id', $p->id)->where('status', 'active')
                    ->orderBy('received_date', 'desc')->first();
                if ($latestBatch && floatval($latestBatch->supplier_price) > 0) {
                    $supplierPrice = floatval($latestBatch->supplier_price);
                }
                $results[] = [
                    'product_id' => $p->id,
                    'name' => $p->name,
                    'code' => $p->code,
                    'variant_id' => null,
                    'variant_value' => null,
                    'variant_name' => null,
                    'supplier_price' => $supplierPrice,
                    'available_stock' => $p->stock->available_stock ?? 0,
                ];
            }
        }

        $this->productSearchResults = $results;
        $this->showProductDropdown = true;
    }

    public function selectProduct($index, $resultIndex)
    {
        if (!isset($this->productSearchResults[$resultIndex])) return;
        $p = $this->productSearchResults[$resultIndex];

        $displayName = $p['name'] . ($p['variant_value'] ? ' - ' . ($p['variant_name'] ?? 'Variant') . ': ' . $p['variant_value'] : '');

        $this->items[$index] = array_merge($this->items[$index], [
            'product_id' => $p['product_id'],
            'variant_id' => $p['variant_id'],
            'variant_value' => $p['variant_value'],
            'search' => $displayName,
            'name' => $p['name'],
            'code' => $p['code'],
            'rate' => floatval($p['supplier_price']),
            'available_stock' => $p['available_stock'],
        ]);

        $this->calculateLineTotal($index);

        // Add empty row if this was the last
        if ($index === count($this->items) - 1) {
            $this->addEmptyRow();
        }

        $this->productSearchResults = [];
        $this->showProductDropdown = false;
        $this->dispatch('focus-field', index: $index, field: 'qty');
    }

    public function calculateLineTotal($index)
    {
        if (!isset($this->items[$index])) return;
        $item = &$this->items[$index];
        $qty = max(0, intval($item['quantity']));
        $rate = max(0, floatval($item['rate']));
        $disc = floatval($item['discount'] ?? 0);
        $taxPct = floatval($item['tax_percentage'] ?? 0);

        $lineTotal = $qty * $rate;
        $discAmt = $disc * $qty;
        $taxAmt = ($lineTotal - $discAmt) * ($taxPct / 100);

        $this->items[$index]['tax_amount'] = round($taxAmt, 2);
        $this->items[$index]['amount'] = round($lineTotal - $discAmt + $taxAmt, 2);
    }

    public function removeItem($index)
    {
        if (isset($this->items[$index]) && count($this->items) > 1) {
            unset($this->items[$index]);
            $this->items = array_values($this->items);
        }
    }

    // ═══ Computed ═══

    public function getSubtotalProperty()
    {
        return collect($this->items)->where('product_id', '!=', null)->sum('amount');
    }

    public function getGrandTotalProperty()
    {
        return $this->subtotal + floatval($this->transportCost);
    }

    public function getItemCountProperty()
    {
        return collect($this->items)->where('product_id', '!=', null)->count();
    }

    public function getFilteredSuppliersProperty()
    {
        if (strlen($this->supplierSearch) >= 2) {
            return ProductSupplier::where('name', 'like', '%' . $this->supplierSearch . '%')
                ->orWhere('business_name', 'like', '%' . $this->supplierSearch . '%')
                ->orderBy('name')->limit(10)->get();
        }
        return collect();
    }

    // ═══ Save (Modify) ═══

    public function updateVoucher()
    {
        if (!$this->loadedOrderId) {
            $this->js("Swal.fire('Error', 'No voucher loaded.', 'error');");
            return;
        }

        if (!$this->supplierId) {
            $this->js("Swal.fire('Error', 'Please select a supplier.', 'error');");
            return;
        }

        $validItems = collect($this->items)->filter(fn($i) => !empty($i['product_id']) && intval($i['quantity']) > 0)->values()->toArray();

        if (empty($validItems)) {
            $this->js("Swal.fire('Error', 'Please add at least one item.', 'error');");
            return;
        }

        try {
            $voucherData = [
                'date' => $this->voucherDate,
                'supplier_id' => $this->supplierId,
                'billing_type' => $this->billingType,
                'transport_cost' => $this->transportCost,
                'invoice_number' => $this->invoiceNumber,
            ];

            $items = array_map(function ($i) {
                return [
                    'product_id' => $i['product_id'],
                    'variant_id' => $i['variant_id'] ?? null,
                    'variant_value' => $i['variant_value'] ?? null,
                    'quantity' => intval($i['quantity']),
                    'free_qty' => intval($i['free_qty'] ?? 0),
                    'rate' => floatval($i['rate']),
                    'discount' => floatval($i['discount'] ?? 0),
                    'tax_amount' => floatval($i['tax_amount'] ?? 0),
                    'tax_percentage' => floatval($i['tax_percentage'] ?? 0),
                ];
            }, $validItems);

            $order = VoucherService::modifyPurchaseVoucher($this->loadedOrderId, $voucherData, $items);
            $this->savedOrder = $order;
            $this->showSavedModal = true;

            $this->js("Swal.fire({ icon:'success', title:'Updated!', text:'Purchase Voucher {$order->order_code} updated successfully.', timer:2000, showConfirmButton:false });");
        } catch (\Exception $e) {
            Log::error('Purchase voucher update error: ' . $e->getMessage());
            $this->js("Swal.fire('Error', 'Failed to update: " . addslashes($e->getMessage()) . "', 'error');");
        }
    }

    // ═══ Reset ═══

    public function resetForm()
    {
        $this->loadedOrderId = null;
        $this->voucherDate = now()->toDateString();
        $this->voucherNumber = '';
        $this->invoiceNumber = '';
        $this->supplierId = '';
        $this->supplierSearch = '';
        $this->billingType = 'cash';
        $this->transportCost = 0;
        $this->notes = '';
        $this->items = [];
        $this->addEmptyRow();
        $this->isLoaded = false;
        $this->showSavedModal = false;
        $this->searchQuery = '';
        $this->searchResults = [];
    }

    public function render()
    {
        return view('livewire.admin.purchase-voucher-modify', [
            'filteredSuppliers' => $this->filteredSuppliers,
            'recentVouchers' => $this->recentVouchers,
        ])->layout($this->layout, ['erpContext' => 'voucher']);
    }
}
