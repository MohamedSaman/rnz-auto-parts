<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Models\ProductSupplier;
use App\Models\ProductDetail;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\ProductBatch;
use App\Models\ProductPrice;
use App\Models\ProductStock;
use App\Models\BrandList;
use App\Models\CategoryList;
use App\Models\ProductModel;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

#[Layout('components.layouts.blank')]
#[Title('Create Purchase')]

class PurchaseCreate extends Component
{
    // ── Header fields ──────────────────────────────────────────
    public $suppliers = [];
    public $supplier_id = '';
    public $invoiceNumber = '';
    public $purchaseDate = '';
    public $transportCost = 0;
    public $paymentType = 'cash';
    public $editOrderId = null;

    // ── Product search ─────────────────────────────────────────
    public $searchProduct = '';
    public $products = [];
    public $selectedSearchProduct = null; // populated when user selects from results

    // ── Cart (order items) ─────────────────────────────────────
    public $cart = [];
    public $grandTotal = 0;

    // ── Create product form ────────────────────────────────────
    public $newProductName = '';
    public $newProductBrand = '';
    public $newProductCategory = '';
    public $newProductModel = '';
    public $newProductCostPrice = 0;
    public $newProductWholesalePrice = 0;
    public $newProductDistributorPrice = 0;
    public $newProductRetailPrice = 0;
    public $newProductFastMoving = false;
    public $newProductMinStock = 5;
    public $newProductStoreLocation = '';
    public $newProductRackNumber = '';
    public $newProductQty = 1;
    public $newProductFreeQty = 0;
    public $newProductCalculatedTotal = 0;
    public $isExistingProduct = false;
    public $existingProductId = null;

    // ── Dropdowns for modals ───────────────────────────────────
    public $brands = [];
    public $categories = [];
    public $models = [];

    // ── Supplier modal ─────────────────────────────────────────
    public $newSupplierName = '';
    public $newSupplierBusinessName = '';
    public $newSupplierContact = '';
    public $newSupplierAddress = '';
    public $newSupplierEmail = '';
    public $newSupplierPhone = '';

    // ── Brand modal ────────────────────────────────────────────
    public $newBrandName = '';

    // ── Category modal ─────────────────────────────────────────
    public $newCategoryName = '';

    // ── Model modal ────────────────────────────────────────────
    public $newModelName = '';

    public function mount()
    {
        $this->suppliers = ProductSupplier::orderBy('name')->get();
        $this->brands = BrandList::orderBy('brand_name')->get();
        $this->categories = CategoryList::orderBy('category_name')->get();
        $this->models = ProductModel::orderBy('model_name')->get();
        $this->purchaseDate = now()->format('Y-m-d');
        $this->invoiceNumber = $this->generateInvoiceNumber();

        // Check if editing an existing order
        $editOrderId = request()->query('edit');
        if ($editOrderId) {
            $order = PurchaseOrder::with(['items.product'])->find($editOrderId);
            if ($order) {
                $this->editOrderId = $order->id;
                $this->supplier_id = $order->supplier_id;
                $this->invoiceNumber = $order->invoice_number ?? $this->invoiceNumber;
                $this->purchaseDate = $order->order_date;
                $this->transportCost = floatval($order->transport_cost ?? 0);
                $this->paymentType = $order->payment_type ?? 'cash';

                // Load cart items from the order
                $this->cart = [];
                foreach ($order->items as $item) {
                    $this->cart[] = [
                        'product_id' => $item->product_id,
                        'variant_id' => $item->variant_id,
                        'variant_value' => $item->variant_value,
                        'name' => $item->display_name ?? ($item->product->name ?? ''),
                        'code' => $item->product->code ?? '',
                        'quantity' => intval($item->quantity),
                        'free_qty' => intval($item->free_qty ?? 0),
                        'supplier_price' => floatval($item->unit_price),
                        'total_price' => floatval($item->quantity) * floatval($item->unit_price),
                    ];
                }

                $this->calculateGrandTotal();
            }
        }
    }

    /**
     * Generate next invoice number: INV-0001, INV-0002 ...
     */
    private function generateInvoiceNumber(): string
    {
        $last = PurchaseOrder::whereNotNull('invoice_number')
            ->orderByDesc('invoice_number')
            ->value('invoice_number');

        if ($last && preg_match('/INV-(\d+)/', $last, $m)) {
            $next = intval($m[1]) + 1;
        } else {
            $next = 1;
        }

        return 'INV-' . str_pad($next, 4, '0', STR_PAD_LEFT);
    }

    // ─── Product Search ────────────────────────────────────────

    public function updatedSearchProduct()
    {
        $this->isExistingProduct = false;
        $this->existingProductId = null;
        $this->selectedSearchProduct = null;
        $this->resetCreateProductForm();

        if (strlen($this->searchProduct) >= 2) {
            $matches = ProductDetail::where('name', 'like', '%' . $this->searchProduct . '%')
                ->orWhere('code', 'like', '%' . $this->searchProduct . '%')
                ->with(['stock', 'price', 'variant', 'brand', 'category', 'productModel'])
                ->limit(10)
                ->get();

            $results = [];
            foreach ($matches as $p) {
                if (!empty($p->variant_id) && $p->variant && is_array($p->variant->variant_values) && count($p->variant->variant_values) > 0) {
                    foreach ($p->variant->variant_values as $val) {
                        $supplierPrice = ProductPrice::where('product_id', $p->id)
                            ->where('variant_value', $val)
                            ->value('supplier_price');

                        if (!$supplierPrice) {
                            $latestBatch = ProductBatch::where('product_id', $p->id)
                                ->where('status', 'active')
                                ->orderBy('received_date', 'desc')
                                ->orderBy('id', 'desc')
                                ->first();
                            $supplierPrice = ($latestBatch && floatval($latestBatch->supplier_price) > 0)
                                ? floatval($latestBatch->supplier_price)
                                : ($p->price->supplier_price ?? 0);
                        }

                        $available = ProductStock::where('product_id', $p->id)
                            ->where('variant_value', $val)
                            ->value('available_stock');

                        $results[] = [
                            'type' => 'variant',
                            'product_id' => $p->id,
                            'name' => $p->name,
                            'code' => $p->code,
                            'image' => $p->image,
                            'variant_value' => (string) $val,
                            'variant_name' => $p->variant->variant_name ?? null,
                            'variant_id' => $p->variant_id,
                            'supplier_price' => $supplierPrice ?: 0,
                            'available_stock' => $available ?: 0,
                            'brand_id' => $p->brand_id,
                            'category_id' => $p->category_id,
                            'model_id' => $p->model_id,
                            'wholesale_price' => ProductPrice::where('product_id', $p->id)->where('variant_value', $val)->value('wholesale_price') ?? 0,
                            'distributor_price' => ProductPrice::where('product_id', $p->id)->where('variant_value', $val)->value('distributor_price') ?? 0,
                            'retail_price' => ProductPrice::where('product_id', $p->id)->where('variant_value', $val)->value('retail_price') ?? 0,
                            'fast_moving' => $p->fast_moving ?? false,
                            'store_location' => $p->store_location ?? '',
                            'rack_number' => $p->rack_number ?? '',
                            'min_stock' => $p->stock->low_stock ?? 5,
                        ];
                    }
                } else {
                    $supplierPrice = $p->price->supplier_price ?? 0;
                    $latestBatch = ProductBatch::where('product_id', $p->id)
                        ->where('status', 'active')
                        ->orderBy('received_date', 'desc')
                        ->orderBy('id', 'desc')
                        ->first();
                    if ($latestBatch && floatval($latestBatch->supplier_price) > 0) {
                        $supplierPrice = floatval($latestBatch->supplier_price);
                    }

                    $results[] = [
                        'type' => 'product',
                        'product_id' => $p->id,
                        'name' => $p->name,
                        'code' => $p->code,
                        'image' => $p->image,
                        'supplier_price' => $supplierPrice,
                        'available_stock' => $p->stock->available_stock ?? 0,
                        'brand_id' => $p->brand_id,
                        'category_id' => $p->category_id,
                        'model_id' => $p->model_id,
                        'wholesale_price' => $p->price->wholesale_price ?? 0,
                        'distributor_price' => $p->price->distributor_price ?? 0,
                        'retail_price' => $p->price->retail_price ?? 0,
                        'fast_moving' => $p->fast_moving ?? false,
                        'store_location' => $p->store_location ?? '',
                        'rack_number' => $p->rack_number ?? '',
                        'min_stock' => $p->stock->low_stock ?? 5,
                    ];
                }
            }
            $this->products = $results;
        } else {
            $this->products = [];
        }
    }

    /**
     * When user selects an existing product from search results, populate the create form.
     */
    public function selectSearchProduct($index)
    {
        if (!isset($this->products[$index])) return;

        $p = $this->products[$index];
        $this->selectedSearchProduct = $p;
        $this->isExistingProduct = true;
        $this->existingProductId = $p['product_id'];

        // Populate create product fields with existing product data
        $this->newProductName = $p['name'];
        $this->newProductBrand = $p['brand_id'] ?? '';
        $this->newProductCategory = $p['category_id'] ?? '';
        $this->newProductModel = $p['model_id'] ?? '';
        $this->newProductCostPrice = $p['supplier_price'] ?? 0;
        $this->newProductWholesalePrice = $p['wholesale_price'] ?? 0;
        $this->newProductDistributorPrice = $p['distributor_price'] ?? 0;
        $this->newProductRetailPrice = $p['retail_price'] ?? 0;
        $this->newProductFastMoving = $p['fast_moving'] ?? false;
        $this->newProductMinStock = $p['min_stock'] ?? 5;
        $this->newProductStoreLocation = $p['store_location'] ?? '';
        $this->newProductRackNumber = $p['rack_number'] ?? '';
        $this->newProductQty = 1;
        $this->newProductFreeQty = 0;

        // Clear search results
        $this->products = [];
        $this->searchProduct = '';

        $this->calculateProductTotal();
        $this->dispatch('product-selected');
    }

    /**
     * Add existing product (with variant) directly to cart.
     */
    public function selectProductVariant($productId, $variantValue)
    {
        $product = ProductDetail::find($productId);
        if (!$product) return;

        // Find in search results
        $pData = null;
        foreach ($this->products as $p) {
            if ($p['product_id'] == $productId && isset($p['variant_value']) && $p['variant_value'] === (string)$variantValue) {
                $pData = $p;
                break;
            }
        }

        if (!$pData) return;

        // Populate the create product section
        $this->selectedSearchProduct = $pData;
        $this->isExistingProduct = true;
        $this->existingProductId = $productId;
        $this->newProductName = $product->name . ' - ' . ($product->variant->variant_name ?? 'Variant') . ': ' . $variantValue;
        $this->newProductBrand = $pData['brand_id'] ?? '';
        $this->newProductCategory = $pData['category_id'] ?? '';
        $this->newProductModel = $pData['model_id'] ?? '';
        $this->newProductCostPrice = $pData['supplier_price'] ?? 0;
        $this->newProductWholesalePrice = $pData['wholesale_price'] ?? 0;
        $this->newProductDistributorPrice = $pData['distributor_price'] ?? 0;
        $this->newProductRetailPrice = $pData['retail_price'] ?? 0;
        $this->newProductFastMoving = $pData['fast_moving'] ?? false;
        $this->newProductMinStock = $pData['min_stock'] ?? 5;
        $this->newProductStoreLocation = $pData['store_location'] ?? '';
        $this->newProductRackNumber = $pData['rack_number'] ?? '';
        $this->newProductQty = 1;
        $this->newProductFreeQty = 0;

        $this->products = [];
        $this->searchProduct = '';
        $this->calculateProductTotal();
        $this->dispatch('product-selected');
    }

    // ─── Add to Cart ───────────────────────────────────────────

    /**
     * Add product to cart. If new product, create it first.
     */
    public function addToCart()
    {
        // Validate
        if (empty($this->newProductName)) {
            $this->js("Swal.fire('Error', 'Please enter a product name.', 'error');");
            return;
        }

        if (floatval($this->newProductCostPrice) < 0) {
            $this->js("Swal.fire('Error', 'Cost price cannot be negative.', 'error');");
            return;
        }

        if (intval($this->newProductQty) < 1) {
            $this->js("Swal.fire('Error', 'Quantity must be at least 1.', 'error');");
            return;
        }

        $productId = null;
        $variantId = null;
        $variantValue = null;
        $displayName = $this->newProductName;

        if ($this->isExistingProduct && $this->existingProductId) {
            // Existing product - just use its ID
            $productId = $this->existingProductId;

            if ($this->selectedSearchProduct && isset($this->selectedSearchProduct['variant_id'])) {
                $variantId = $this->selectedSearchProduct['variant_id'] ?? null;
                $variantValue = $this->selectedSearchProduct['variant_value'] ?? null;
            }

            // Update product extra fields if changed
            $product = ProductDetail::find($productId);
            if ($product) {
                $product->update([
                    'fast_moving' => $this->newProductFastMoving,
                    'store_location' => $this->newProductStoreLocation ?: null,
                    'rack_number' => $this->newProductRackNumber ?: null,
                ]);
                $displayName = $this->selectedSearchProduct['type'] === 'variant'
                    ? $this->newProductName
                    : $product->name;
            }
        } else {
            // New product — create it
            if (empty($this->newProductBrand)) {
                $this->js("Swal.fire('Error', 'Please select a brand.', 'error');");
                return;
            }
            if (empty($this->newProductCategory)) {
                $this->js("Swal.fire('Error', 'Please select a category.', 'error');");
                return;
            }

            try {
                DB::beginTransaction();

                $productCode = 'PROD-' . strtoupper(Str::random(8));

                $product = ProductDetail::create([
                    'code' => $productCode,
                    'name' => $this->newProductName,
                    'model_id' => $this->newProductModel ?: null,
                    'status' => 'active',
                    'fast_moving' => $this->newProductFastMoving,
                    'store_location' => $this->newProductStoreLocation ?: null,
                    'rack_number' => $this->newProductRackNumber ?: null,
                    'brand_id' => $this->newProductBrand,
                    'category_id' => $this->newProductCategory,
                    'supplier_id' => $this->supplier_id ?: null,
                ]);

                ProductPrice::create([
                    'product_id' => $product->id,
                    'pricing_mode' => 'single',
                    'supplier_price' => floatval($this->newProductCostPrice),
                    'selling_price' => floatval($this->newProductRetailPrice),
                    'retail_price' => floatval($this->newProductRetailPrice),
                    'wholesale_price' => floatval($this->newProductWholesalePrice),
                    'distributor_price' => floatval($this->newProductDistributorPrice),
                ]);

                ProductStock::create([
                    'product_id' => $product->id,
                    'available_stock' => 0,
                    'damage_stock' => 0,
                    'total_stock' => 0,
                    'sold_count' => 0,
                    'restocked_quantity' => 0,
                    'low_stock' => intval($this->newProductMinStock),
                ]);

                DB::commit();

                $productId = $product->id;
                $displayName = $product->name;

                Log::info("New product created from Purchase Create: {$product->name} (ID: {$product->id})");
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error("Error creating product: " . $e->getMessage());
                $this->js("Swal.fire('Error', 'Failed to create product: " . addslashes($e->getMessage()) . "', 'error');");
                return;
            }
        }

        $qty = intval($this->newProductQty);
        $freeQty = intval($this->newProductFreeQty);
        $totalQty = $qty + $freeQty;
        $costPrice = floatval($this->newProductCostPrice);
        $totalCost = $costPrice * $qty; // Free qty not charged

        // Check if product already in cart
        $existingIndex = null;
        foreach ($this->cart as $idx => $item) {
            if (
                $item['product_id'] == $productId
                && ($item['variant_value'] ?? null) === $variantValue
            ) {
                $existingIndex = $idx;
                break;
            }
        }

        if ($existingIndex !== null) {
            $this->cart[$existingIndex]['quantity'] += $qty;
            $this->cart[$existingIndex]['free_qty'] += $freeQty;
            $this->cart[$existingIndex]['total_price'] =
                floatval($this->cart[$existingIndex]['quantity']) * floatval($this->cart[$existingIndex]['supplier_price']);

            // Move to top
            $item = $this->cart[$existingIndex];
            unset($this->cart[$existingIndex]);
            $this->cart = array_values($this->cart);
            array_unshift($this->cart, $item);
        } else {
            array_unshift($this->cart, [
                'product_id' => $productId,
                'variant_id' => $variantId,
                'variant_value' => $variantValue,
                'name' => $displayName,
                'quantity' => $qty,
                'free_qty' => $freeQty,
                'supplier_price' => $costPrice,
                'wholesale_price' => floatval($this->newProductWholesalePrice),
                'distributor_price' => floatval($this->newProductDistributorPrice),
                'retail_price' => floatval($this->newProductRetailPrice),
                'total_price' => $totalCost,
            ]);
        }

        $this->calculateGrandTotal();
        $this->resetCreateProductForm();
        $this->isExistingProduct = false;
        $this->existingProductId = null;
        $this->selectedSearchProduct = null;

        $this->dispatch('item-added-to-cart');
    }

    // ─── Cart manipulation ─────────────────────────────────────

    public function updateCartItemQty($index)
    {
        if (isset($this->cart[$index])) {
            // Ensure quantity is at least 1
            $this->cart[$index]['quantity'] = max(1, intval($this->cart[$index]['quantity']));
            // Recalculate total price for this item
            $this->cart[$index]['total_price'] = floatval($this->cart[$index]['quantity']) * floatval($this->cart[$index]['supplier_price']);
            $this->calculateGrandTotal();
        }
    }

    public function updateCartItemPrice($index)
    {
        if (isset($this->cart[$index])) {
            // Recalculate total price based on updated quantity and price
            $qty = floatval($this->cart[$index]['quantity']);
            $price = floatval($this->cart[$index]['supplier_price']);
            $this->cart[$index]['total_price'] = $qty * $price;
            $this->calculateGrandTotal();
        }
    }

    public function removeCartItem($index)
    {
        if (isset($this->cart[$index])) {
            unset($this->cart[$index]);
            $this->cart = array_values($this->cart);
            $this->calculateGrandTotal();
        }
    }

    public function calculateGrandTotal()
    {
        $this->grandTotal = floatval(collect($this->cart)->sum('total_price'));
    }

    // ─── Save Purchase Order ───────────────────────────────────

    public function savePurchaseOrder()
    {
        // Validate
        if (!$this->supplier_id) {
            $this->js("Swal.fire('Error', 'Please select a supplier!', 'error');");
            return;
        }
        if (!$this->purchaseDate) {
            $this->js("Swal.fire('Error', 'Please select a purchase date!', 'error');");
            return;
        }
        if (empty($this->cart)) {
            $this->js("Swal.fire('Error', 'Please add at least one product to the cart!', 'error');");
            return;
        }

        foreach ($this->cart as $item) {
            if (!isset($item['product_id']) || $item['quantity'] < 1) {
                $this->js("Swal.fire('Error', 'Invalid item data!', 'error');");
                return;
            }
        }

        try {
            DB::beginTransaction();

            $orderDateTime = \Carbon\Carbon::createFromFormat('Y-m-d', $this->purchaseDate)->startOfDay();
            $totalAmount = floatval(collect($this->cart)->sum('total_price'));

            // Check if we're editing an existing order
            if ($this->editOrderId) {
                // UPDATE EXISTING ORDER
                $order = PurchaseOrder::find($this->editOrderId);
                if (!$order) {
                    throw new \Exception('Order not found');
                }

                $order->update([
                    'invoice_number' => $this->invoiceNumber,
                    'supplier_id' => $this->supplier_id,
                    'order_date' => $this->purchaseDate,
                    'total_amount' => $totalAmount,
                    'transport_cost' => floatval($this->transportCost),
                    'payment_type' => $this->paymentType,
                    'updated_at' => now(),
                ]);

                // Delete old items and create new ones
                $order->items()->delete();

                foreach ($this->cart as $item) {
                    PurchaseOrderItem::create([
                        'order_id' => $order->id,
                        'product_id' => $item['product_id'],
                        'variant_id' => $item['variant_id'] ?? null,
                        'variant_value' => $item['variant_value'] ?? null,
                        'quantity' => intval($item['quantity']),
                        'free_qty' => intval($item['free_qty'] ?? 0),
                        'unit_price' => floatval($item['supplier_price']),
                        'discount' => 0,
                        'status' => 'pending',
                        'created_at' => $orderDateTime,
                        'updated_at' => $orderDateTime,
                    ]);
                }

                DB::commit();

                $redirectRoute = auth()->user()->role === 'staff'
                    ? route('staff.purchase-order-list')
                    : route('admin.purchase-order-list');

                $this->js("
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: 'Purchase Order {$order->order_code} ({$this->invoiceNumber}) updated successfully!',
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => {
                        window.location.href = '{$redirectRoute}';
                    });
                ");

                Log::info("Purchase Order updated: {$order->order_code} / {$this->invoiceNumber}");
            } else {
                // CREATE NEW ORDER
                // Generate order_code (keeping old pattern for backward compat)
                $year = \Carbon\Carbon::createFromFormat('Y-m-d', $this->purchaseDate)->format('Ymd');
                $lastOrder = PurchaseOrder::where('order_code', 'like', 'ORD-' . $year . '-%')
                    ->orderByDesc('order_code')
                    ->first();

                if ($lastOrder && preg_match('/ORD-' . $year . '-(\d+)/', $lastOrder->order_code, $matches)) {
                    $nextNumber = intval($matches[1]) + 1;
                } else {
                    $nextNumber = 1;
                }
                $orderCode = 'ORD-' . $year . '-' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);

                $order = PurchaseOrder::create([
                    'order_code' => $orderCode,
                    'invoice_number' => $this->invoiceNumber,
                    'supplier_id' => $this->supplier_id,
                    'order_date' => $this->purchaseDate,
                    'status' => 'pending',
                    'total_amount' => $totalAmount,
                    'transport_cost' => floatval($this->transportCost),
                    'payment_type' => $this->paymentType,
                    'created_at' => $orderDateTime,
                    'updated_at' => $orderDateTime,
                ]);

                foreach ($this->cart as $item) {
                    PurchaseOrderItem::create([
                        'order_id' => $order->id,
                        'product_id' => $item['product_id'],
                        'variant_id' => $item['variant_id'] ?? null,
                        'variant_value' => $item['variant_value'] ?? null,
                        'quantity' => intval($item['quantity']),
                        'free_qty' => intval($item['free_qty'] ?? 0),
                        'unit_price' => floatval($item['supplier_price']),
                        'discount' => 0,
                        'status' => 'pending',
                        'created_at' => $orderDateTime,
                        'updated_at' => $orderDateTime,
                    ]);
                }

                DB::commit();

                $redirectRoute = auth()->user()->role === 'staff'
                    ? route('staff.purchase-order-list')
                    : route('admin.purchase-order-list');

                $this->js("
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: 'Purchase Order {$orderCode} ({$this->invoiceNumber}) created successfully!',
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => {
                        window.location.href = '{$redirectRoute}';
                    });
                ");

                Log::info("Purchase Order created: {$orderCode} / {$this->invoiceNumber}");
            }
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error creating purchase order: " . $e->getMessage());
            $this->js("Swal.fire('Error', 'Failed to create purchase order: " . addslashes($e->getMessage()) . "', 'error');");
        }
    }

    // ─── Modal actions (create supplier, brand, category, model) ──

    public function createSupplier()
    {
        if (empty($this->newSupplierName)) {
            $this->js("Swal.fire('Error', 'Please enter a supplier name.', 'error');");
            return;
        }

        try {
            $supplier = ProductSupplier::create([
                'name' => $this->newSupplierName,
                'businessname' => $this->newSupplierBusinessName,
                'contact' => $this->newSupplierContact,
                'address' => $this->newSupplierAddress,
                'email' => $this->newSupplierEmail,
                'phone' => $this->newSupplierPhone,
                'status' => 'active',
            ]);

            $this->suppliers = ProductSupplier::orderBy('name')->get();
            $this->supplier_id = $supplier->id;

            $this->reset(['newSupplierName', 'newSupplierBusinessName', 'newSupplierContact', 'newSupplierAddress', 'newSupplierEmail', 'newSupplierPhone']);

            $this->js("
                bootstrap.Modal.getInstance(document.getElementById('createSupplierModal'))?.hide();
                Swal.fire({icon:'success', title:'Supplier Created!', timer:1500, showConfirmButton:false});
            ");
        } catch (\Exception $e) {
            $this->js("Swal.fire('Error', '" . addslashes($e->getMessage()) . "', 'error');");
        }
    }

    public function createBrand()
    {
        if (empty($this->newBrandName)) {
            $this->js("Swal.fire('Error', 'Please enter a brand name.', 'error');");
            return;
        }

        try {
            $brand = BrandList::create(['brand_name' => $this->newBrandName]);
            $this->brands = BrandList::orderBy('brand_name')->get();
            $this->newProductBrand = $brand->id;
            $this->reset('newBrandName');

            $this->js("
                bootstrap.Modal.getInstance(document.getElementById('createBrandModal'))?.hide();
                Swal.fire({icon:'success', title:'Brand Created!', timer:1500, showConfirmButton:false});
            ");
        } catch (\Exception $e) {
            $this->js("Swal.fire('Error', '" . addslashes($e->getMessage()) . "', 'error');");
        }
    }

    public function createCategory()
    {
        if (empty($this->newCategoryName)) {
            $this->js("Swal.fire('Error', 'Please enter a category name.', 'error');");
            return;
        }

        try {
            $category = CategoryList::create(['category_name' => $this->newCategoryName]);
            $this->categories = CategoryList::orderBy('category_name')->get();
            $this->newProductCategory = $category->id;
            $this->reset('newCategoryName');

            $this->js("
                bootstrap.Modal.getInstance(document.getElementById('createCategoryModal'))?.hide();
                Swal.fire({icon:'success', title:'Category Created!', timer:1500, showConfirmButton:false});
            ");
        } catch (\Exception $e) {
            $this->js("Swal.fire('Error', '" . addslashes($e->getMessage()) . "', 'error');");
        }
    }

    public function createModel()
    {
        if (empty($this->newModelName)) {
            $this->js("Swal.fire('Error', 'Please enter a model name.', 'error');");
            return;
        }

        try {
            $model = ProductModel::create(['model_name' => $this->newModelName, 'status' => 'active']);
            $this->models = ProductModel::orderBy('model_name')->get();
            $this->newProductModel = $model->id;
            $this->reset('newModelName');

            $this->js("
                bootstrap.Modal.getInstance(document.getElementById('createModelModal'))?.hide();
                Swal.fire({icon:'success', title:'Model Created!', timer:1500, showConfirmButton:false});
            ");
        } catch (\Exception $e) {
            $this->js("Swal.fire('Error', '" . addslashes($e->getMessage()) . "', 'error');");
        }
    }

    // ─── Helpers ───────────────────────────────────────────────

    public function calculateProductTotal()
    {
        $this->newProductCalculatedTotal = floatval($this->newProductCostPrice) * max(1, intval($this->newProductQty));
    }

    private function resetCreateProductForm()
    {
        $this->newProductName = '';
        $this->newProductBrand = '';
        $this->newProductCategory = '';
        $this->newProductModel = '';
        $this->newProductCostPrice = 0;
        $this->newProductWholesalePrice = 0;
        $this->newProductDistributorPrice = 0;
        $this->newProductRetailPrice = 0;
        $this->newProductFastMoving = false;
        $this->newProductMinStock = 5;
        $this->newProductStoreLocation = '';
        $this->newProductRackNumber = '';
        $this->newProductQty = 1;
        $this->newProductFreeQty = 0;
        $this->newProductCalculatedTotal = 0;
    }

    public function render()
    {
        return view('livewire.admin.purchase-create');
    }
}
