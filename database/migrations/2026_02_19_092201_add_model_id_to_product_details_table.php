<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_details', function (Blueprint $table) {
            // Add the new model_id foreign key column (nullable so existing rows are not broken)
            $table->unsignedBigInteger('model_id')->nullable()->after('name');
            $table->foreign('model_id')->references('id')->on('product_models')->nullOnDelete();
        });

        // Drop the old text-based model column (keep data integrity; existing text data is lost)
        Schema::table('product_details', function (Blueprint $table) {
            $table->dropColumn('model');
        });
    }

    public function down(): void
    {
        Schema::table('product_details', function (Blueprint $table) {
            // Restore the old text column
            $table->string('model')->nullable()->after('name');
        });

        Schema::table('product_details', function (Blueprint $table) {
            $table->dropForeign(['model_id']);
            $table->dropColumn('model_id');
        });
    }
};
