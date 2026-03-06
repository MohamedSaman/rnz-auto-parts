<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // --- Sales ---
        Schema::table('sales', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('user_id')
                ->constrained('branches')->nullOnDelete();
            $table->unsignedBigInteger('voucher_id')->nullable()->after('branch_id');
            $table->decimal('tax_amount', 15, 2)->default(0)->after('discount_amount');
            $table->softDeletes();

            $table->foreign('voucher_id')->references('id')->on('vouchers')->nullOnDelete();
            $table->index('branch_id');
        });

        // --- Sale Items (store actual FIFO cost at time of sale) ---
        Schema::table('sale_items', function (Blueprint $table) {
            $table->decimal('cost_price_at_sale', 15, 2)->default(0)->after('total');
            $table->softDeletes();
        });

        // --- Purchase Orders ---
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('supplier_id')
                ->constrained('branches')->nullOnDelete();
            $table->unsignedBigInteger('voucher_id')->nullable()->after('branch_id');
            $table->decimal('tax_amount', 15, 2)->default(0)->after('discount_amount');
            $table->softDeletes();

            $table->foreign('voucher_id')->references('id')->on('vouchers')->nullOnDelete();
            $table->index('branch_id');
        });

        // --- Payments (customer payments) ---
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('customer_id')
                ->constrained('branches')->nullOnDelete();
            $table->unsignedBigInteger('voucher_id')->nullable()->after('branch_id');
            $table->softDeletes();

            $table->foreign('voucher_id')->references('id')->on('vouchers')->nullOnDelete();
            $table->index('branch_id');
        });

        // --- Purchase Payments (supplier payments) ---
        Schema::table('purchase_payments', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('supplier_id')
                ->constrained('branches')->nullOnDelete();
            $table->unsignedBigInteger('voucher_id')->nullable()->after('branch_id');
            $table->softDeletes();

            $table->foreign('voucher_id')->references('id')->on('vouchers')->nullOnDelete();
            $table->index('branch_id');
        });

        // --- Expenses ---
        Schema::table('expenses', function (Blueprint $table) {
            $table->unsignedBigInteger('account_id')->nullable()->after('category');
            $table->foreignId('branch_id')->nullable()->after('account_id')
                ->constrained('branches')->nullOnDelete();
            $table->unsignedBigInteger('voucher_id')->nullable()->after('branch_id');
            $table->softDeletes();

            $table->foreign('account_id')->references('id')->on('accounts')->nullOnDelete();
            $table->foreign('voucher_id')->references('id')->on('vouchers')->nullOnDelete();
            $table->index('branch_id');
        });

        // --- POS Sessions ---
        Schema::table('pos_sessions', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('user_id')
                ->constrained('branches')->nullOnDelete();
            $table->index('branch_id');
        });

        // --- Customers (link to ledger account) ---
        Schema::table('customers', function (Blueprint $table) {
            $table->unsignedBigInteger('account_id')->nullable()->after('id');
            $table->softDeletes();

            $table->foreign('account_id')->references('id')->on('accounts')->nullOnDelete();
        });

        // --- Product Suppliers (link to ledger account) ---
        Schema::table('product_suppliers', function (Blueprint $table) {
            $table->unsignedBigInteger('account_id')->nullable()->after('id');
            $table->softDeletes();

            $table->foreign('account_id')->references('id')->on('accounts')->nullOnDelete();
        });

        // --- Product Batches (branch tracking) ---
        Schema::table('product_batches', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('product_id')
                ->constrained('branches')->nullOnDelete();
        });

        // --- Product Stocks (branch tracking) ---
        Schema::table('product_stocks', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('product_id')
                ->constrained('branches')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);
            $table->dropForeign(['voucher_id']);
            $table->dropColumn(['branch_id', 'voucher_id', 'tax_amount', 'deleted_at']);
        });

        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropColumn(['cost_price_at_sale', 'deleted_at']);
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);
            $table->dropForeign(['voucher_id']);
            $table->dropColumn(['branch_id', 'voucher_id', 'tax_amount', 'deleted_at']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);
            $table->dropForeign(['voucher_id']);
            $table->dropColumn(['branch_id', 'voucher_id', 'deleted_at']);
        });

        Schema::table('purchase_payments', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);
            $table->dropForeign(['voucher_id']);
            $table->dropColumn(['branch_id', 'voucher_id', 'deleted_at']);
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->dropForeign(['account_id']);
            $table->dropForeign(['branch_id']);
            $table->dropForeign(['voucher_id']);
            $table->dropColumn(['account_id', 'branch_id', 'voucher_id', 'deleted_at']);
        });

        Schema::table('pos_sessions', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);
            $table->dropColumn(['branch_id']);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropForeign(['account_id']);
            $table->dropColumn(['account_id', 'deleted_at']);
        });

        Schema::table('product_suppliers', function (Blueprint $table) {
            $table->dropForeign(['account_id']);
            $table->dropColumn(['account_id', 'deleted_at']);
        });

        Schema::table('product_batches', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);
            $table->dropColumn(['branch_id']);
        });

        Schema::table('product_stocks', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);
            $table->dropColumn(['branch_id']);
        });
    }
};
