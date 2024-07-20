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
        Schema::create('orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained('users');
            $table->string('order_number',10);
            $table->date('order_date');
            $table->decimal('total_price', 10, 2);
            $table->enum('payment_status', ['Đã thanh toán', 'Chưa thanh toán'])->default('Chưa thanh toán');
            $table->enum('payment_method', ['Tiền mặt', 'Chuyển khoản'])->default('Tiền mặt');
            $table->date('payment_date');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
