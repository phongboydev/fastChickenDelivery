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
        Schema::create('invoice_details', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('invoice_id')->constrained()->onDelete('cascade')->name('invoice_id');
            $table->foreignUuid('order_id')->constrained()->onDelete('cascade')->name('order_id');
            $table->foreignUuid('product_id')->constrained()->onDelete('cascade')->name('product_id');
            $table->string('product_name');
            $table->decimal('product_price', 15, 2);
            $table->integer('product_quantity');
            $table->decimal('product_discount', 15, 2);
            $table->decimal('product_total', 15, 2);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_details');
    }
};
