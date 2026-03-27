<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_return_id')->constrained('sales_returns')->cascadeOnDelete();
            $table->foreignId('sale_item_id')->nullable()->constrained('sale_items')->nullOnDelete();
            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('product_details')->restrictOnDelete();
            $table->unsignedBigInteger('variant_id')->nullable();
            $table->string('variant_value')->nullable();
            $table->decimal('sold_qty', 12, 3)->default(0);
            $table->decimal('already_returned_qty', 12, 3)->default(0);
            $table->decimal('balance_returnable_qty', 12, 3)->default(0);
            $table->decimal('return_qty', 12, 3);
            $table->decimal('rate', 12, 2);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('line_total', 12, 2);
            $table->timestamps();

            $table->index(['sale_id', 'product_id']);
            $table->index(['sale_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_return_items');
    }
};
