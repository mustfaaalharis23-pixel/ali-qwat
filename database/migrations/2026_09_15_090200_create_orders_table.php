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
            $table->id();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('cashier_id')->constrained('users')->restrictOnDelete();
            $table->string('payment_type');
            $table->double('amount')->default(0);
            $table->double('total_amount')->default(0);
            $table->double('paid_amount')->default(0);
            $table->double('remaining')->default(0);
            $table->double('down_payment')->nullable();
            $table->double('total_cost')->default(0);
            $table->double('profit')->default(0);
            $table->date('date');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
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
