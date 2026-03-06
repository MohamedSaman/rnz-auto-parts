<?php

namespace App\Services;

use App\Models\ProductStock;
use App\Models\ProductBatch;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * InventoryService — Manages stock operations outside of FIFO batches.
 *
 * Provides stock deduction (fallback when batches are unavailable),
 * stock restoration for voucher modifications/deletions,
 * and stock increase for purchase vouchers.
 */
class InventoryService
{
    /**
     * Deduct stock directly from ProductStock (non-FIFO fallback).
     */
    public static function deductStockDirect(int $productId, int $quantity, ?int $variantId = null, ?string $variantValue = null): void
    {
        $stock = self::getProductStock($productId, $variantId, $variantValue);

        if ($stock) {
            $stock->available_stock = max(0, $stock->available_stock - $quantity);
            $stock->sold_count = ($stock->sold_count ?? 0) + $quantity;
            $stock->save();

            if (method_exists($stock, 'updateTotals')) {
                $stock->updateTotals();
            }
        }
    }

    /**
     * Restore stock to ProductStock and batches when a sale is reversed.
     */
    public static function restoreStock(int $productId, int $quantity, ?int $variantId = null, ?string $variantValue = null): void
    {
        $stock = self::getProductStock($productId, $variantId, $variantValue);

        if ($stock) {
            $stock->available_stock += $quantity;
            $stock->sold_count = max(0, ($stock->sold_count ?? 0) - $quantity);
            $stock->save();

            if (method_exists($stock, 'updateTotals')) {
                $stock->updateTotals();
            }
        }

        // Also try to restore batch quantities (best effort — restore to most recent batch)
        $batchQuery = ProductBatch::where('product_id', $productId)
            ->where('status', 'active');

        if ($variantId) {
            $batchQuery->where('variant_id', $variantId);
            if ($variantValue) {
                $batchQuery->where('variant_value', $variantValue);
            }
        } else {
            $batchQuery->where(function ($q) {
                $q->whereNull('variant_id')->orWhere('variant_id', 0);
            });
        }

        $latestBatch = $batchQuery->orderBy('received_date', 'desc')->first();

        if ($latestBatch) {
            $latestBatch->increment('remaining_quantity', $quantity);
        }
    }

    /**
     * Get available stock for a product (variant-aware).
     */
    public static function getAvailableStock(int $productId, ?int $variantId = null, ?string $variantValue = null): int
    {
        $stock = self::getProductStock($productId, $variantId, $variantValue);
        return $stock ? (int) $stock->available_stock : 0;
    }

    /**
     * Get the ProductStock record for a product/variant combination.
     */
    private static function getProductStock(int $productId, ?int $variantId = null, ?string $variantValue = null): ?ProductStock
    {
        $query = ProductStock::where('product_id', $productId);

        if ($variantId) {
            $query->where('variant_id', $variantId);
            if ($variantValue) {
                $query->where('variant_value', $variantValue);
            }
        } else {
            $query->where(function ($q) {
                $q->whereNull('variant_id')->orWhere('variant_id', 0);
            });
        }

        return $query->first();
    }

    // ╔══════════════════════════════════════════════════════════════╗
    // ║  PURCHASE STOCK OPERATIONS (Increase / Decrease)            ║
    // ╚══════════════════════════════════════════════════════════════╝

    /**
     * Increase stock when a purchase voucher is saved.
     * Creates a ProductBatch and increments ProductStock.
     */
    public static function increaseStock(int $productId, int $quantity, float $costPrice, int $purchaseOrderId, ?int $variantId = null, ?string $variantValue = null): void
    {
        // 1. Create a new ProductBatch for this purchase
        try {
            ProductBatch::create([
                'product_id' => $productId,
                'variant_id' => $variantId,
                'variant_value' => $variantValue,
                'batch_number' => 'PO-' . $purchaseOrderId . '-' . strtoupper(Str::random(4)),
                'quantity' => $quantity,
                'remaining_quantity' => $quantity,
                'supplier_price' => $costPrice,
                'received_date' => now(),
                'status' => 'active',
            ]);
        } catch (\Exception $e) {
            Log::warning("Failed to create batch for product {$productId}: " . $e->getMessage());
        }

        // 2. Increment ProductStock
        $stock = self::getOrCreateProductStock($productId, $variantId, $variantValue);

        if ($stock) {
            $stock->available_stock = ($stock->available_stock ?? 0) + $quantity;
            $stock->restocked_quantity = ($stock->restocked_quantity ?? 0) + $quantity;
            $stock->total_stock = ($stock->total_stock ?? 0) + $quantity;
            $stock->save();

            if (method_exists($stock, 'updateTotals')) {
                $stock->updateTotals();
            }
        }

        Log::info("Stock increased: Product {$productId}, Qty {$quantity}, PO #{$purchaseOrderId}");
    }

    /**
     * Decrease stock when a purchase voucher is reversed/deleted.
     * Reduces ProductStock and attempts to remove the latest batch.
     */
    public static function decreaseStock(int $productId, int $quantity, ?int $variantId = null, ?string $variantValue = null): void
    {
        // 1. Reduce ProductStock
        $stock = self::getProductStock($productId, $variantId, $variantValue);

        if ($stock) {
            $stock->available_stock = max(0, ($stock->available_stock ?? 0) - $quantity);
            $stock->restocked_quantity = max(0, ($stock->restocked_quantity ?? 0) - $quantity);
            $stock->save();

            if (method_exists($stock, 'updateTotals')) {
                $stock->updateTotals();
            }
        }

        // 2. Try to reduce from latest batch
        $batchQuery = ProductBatch::where('product_id', $productId)
            ->where('status', 'active');

        if ($variantId) {
            $batchQuery->where('variant_id', $variantId);
            if ($variantValue) {
                $batchQuery->where('variant_value', $variantValue);
            }
        }

        $latestBatch = $batchQuery->orderBy('received_date', 'desc')->first();

        if ($latestBatch) {
            $newRemaining = max(0, $latestBatch->remaining_quantity - $quantity);
            $latestBatch->update(['remaining_quantity' => $newRemaining]);
            if ($newRemaining <= 0) {
                $latestBatch->update(['status' => 'depleted']);
            }
        }

        Log::info("Stock decreased (reversal): Product {$productId}, Qty {$quantity}");
    }

    /**
     * Get or create a ProductStock record for a product/variant.
     */
    private static function getOrCreateProductStock(int $productId, ?int $variantId = null, ?string $variantValue = null): ?ProductStock
    {
        $stock = self::getProductStock($productId, $variantId, $variantValue);

        if (!$stock) {
            try {
                $data = [
                    'product_id' => $productId,
                    'available_stock' => 0,
                    'damage_stock' => 0,
                    'total_stock' => 0,
                    'sold_count' => 0,
                    'restocked_quantity' => 0,
                    'low_stock' => 5,
                ];

                if ($variantId) {
                    $data['variant_id'] = $variantId;
                }
                if ($variantValue) {
                    $data['variant_value'] = $variantValue;
                }

                $stock = ProductStock::create($data);
            } catch (\Exception $e) {
                Log::warning("Failed to create ProductStock for product {$productId}: " . $e->getMessage());
                return null;
            }
        }

        return $stock;
    }
}
