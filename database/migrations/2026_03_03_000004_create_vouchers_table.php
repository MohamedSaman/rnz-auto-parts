<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();
            $table->string('voucher_no', 30)->unique();
            $table->enum('voucher_type', [
                'sales',
                'purchase',
                'payment',
                'receipt',
                'journal',
                'expense',
                'contra',
            ]);
            $table->date('date');
            $table->text('narration')->nullable();
            $table->string('reference_type', 50)->nullable(); // sale, purchase_order, payment, etc.
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->boolean('is_posted')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['voucher_type', 'date']);
            $table->index(['reference_type', 'reference_id']);
            $table->index('branch_id');
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vouchers');
    }
};
