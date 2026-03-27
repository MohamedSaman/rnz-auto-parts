<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_return_id')->constrained('purchase_returns')->cascadeOnDelete();
            $table->foreignId('purchase_order_item_id')->nullable()->constrained('purchase_order_items')->nullOnDelete();
            $table->foreignId('purchase_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('product_details')->restrictOnDelete();
            $table->unsignedBigInteger('variant_id')->nullable();
            $table->string('variant_value')->nullable();
            $table->decimal('purchased_qty', 12, 3)->default(0);
            $table->decimal('already_returned_qty', 12, 3)->default(0);
            $table->decimal('balance_returnable_qty', 12, 3)->default(0);
            $table->decimal('return_qty', 12, 3);
            $table->decimal('rate', 12, 2);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('line_total', 12, 2);
            $table->timestamps();

            $table->index(['purchase_id', 'product_id']);
            $table->index(['purchase_order_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_return_items');
    }
};
