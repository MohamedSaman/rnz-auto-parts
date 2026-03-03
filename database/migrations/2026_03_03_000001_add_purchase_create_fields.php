<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add new fields to purchase_orders table
        Schema::table('purchase_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('purchase_orders', 'invoice_number')) {
                $table->string('invoice_number')->nullable()->unique()->after('order_code');
            }
            if (!Schema::hasColumn('purchase_orders', 'transport_cost')) {
                $table->decimal('transport_cost', 12, 2)->default(0)->after('total_amount');
            }
            if (!Schema::hasColumn('purchase_orders', 'payment_type')) {
                $table->string('payment_type', 30)->default('cash')->after('transport_cost');
            }
            if (!Schema::hasColumn('purchase_orders', 'discount_amount')) {
                $table->decimal('discount_amount', 12, 2)->default(0)->after('payment_type');
            }
        });

        // Add new fields to product_details table
        Schema::table('product_details', function (Blueprint $table) {
            if (!Schema::hasColumn('product_details', 'fast_moving')) {
                $table->boolean('fast_moving')->default(false)->after('status');
            }
            if (!Schema::hasColumn('product_details', 'store_location')) {
                $table->string('store_location')->nullable()->after('fast_moving');
            }
            if (!Schema::hasColumn('product_details', 'rack_number')) {
                $table->string('rack_number')->nullable()->after('store_location');
            }
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn(['invoice_number', 'transport_cost', 'payment_type', 'discount_amount']);
        });

        Schema::table('product_details', function (Blueprint $table) {
            $table->dropColumn(['fast_moving', 'store_location', 'rack_number']);
        });
    }
};
