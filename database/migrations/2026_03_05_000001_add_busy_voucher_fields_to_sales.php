<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add BUSY-style voucher fields to sales and sale_items tables.
     */
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            if (!Schema::hasColumn('sales', 'voucher_date')) {
                $table->date('voucher_date')->nullable()->after('notes');
            }
            if (!Schema::hasColumn('sales', 'salesman_id')) {
                $table->foreignId('salesman_id')->nullable()->after('voucher_date')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('sales', 'billing_type')) {
                $table->enum('billing_type', ['cash', 'credit'])->default('cash')->after('salesman_id');
            }
            if (!Schema::hasColumn('sales', 'tax_amount')) {
                $table->decimal('tax_amount', 10, 2)->default(0)->after('billing_type');
            }
            if (!Schema::hasColumn('sales', 'voucher_id')) {
                $table->foreignId('voucher_id')->nullable()->after('tax_amount')->constrained('vouchers')->nullOnDelete();
            }
        });

        Schema::table('sale_items', function (Blueprint $table) {
            if (!Schema::hasColumn('sale_items', 'cost_price_at_sale')) {
                $table->decimal('cost_price_at_sale', 10, 2)->default(0)->after('total');
            }
            if (!Schema::hasColumn('sale_items', 'tax_amount')) {
                $table->decimal('tax_amount', 10, 2)->default(0)->after('cost_price_at_sale');
            }
            if (!Schema::hasColumn('sale_items', 'tax_percentage')) {
                $table->decimal('tax_percentage', 5, 2)->default(0)->after('tax_amount');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropForeign(['salesman_id']);
            $table->dropForeign(['voucher_id']);
            $table->dropColumn(['voucher_date', 'salesman_id', 'billing_type', 'tax_amount', 'voucher_id']);
        });

        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropColumn(['cost_price_at_sale', 'tax_amount', 'tax_percentage']);
        });
    }
};
