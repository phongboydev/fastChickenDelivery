<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('product_by_days', function (Blueprint $table) {
            $table->decimal('price', 10, 2)->after('stock');
            Schema::dropColumns('product_by_days', ['quantity']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('column_price_in_product_by_days');
    }
};
