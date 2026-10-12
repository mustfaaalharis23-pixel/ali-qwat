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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type');
            $table->foreignId('general_description_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('container_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('invoice_category_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('cashier_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->date('date');
            $table->double('amount')->default(0);
            $table->double('amount_usd')->nullable();
            $table->double('amount_iqd')->nullable();
            $table->double('paid_amount')->default(0);
            $table->double('remaining')->default(0);
            $table->double('cbm')->default(0);
            $table->double('cbm_price')->nullable();
            $table->timestamp('cbm_price_locked_at')->nullable();
            $table->double('transport_cost_usd')->nullable();
            $table->double('transport_cost_iqd')->nullable();
            $table->double('exchange_rate')->nullable();
            $table->string('exchange_rate_source')->nullable();
            $table->double('dinar_rate')->nullable();
            $table->string('status')->default('pending');
            $table->string('stage')->nullable();
            $table->foreignId('importer_checker_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('importer_confirmed_at')->nullable();
            $table->foreignId('receiver_checker_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('receiver_confirmed_at')->nullable();
            $table->foreignId('original_invoice_id')->nullable()->constrained('invoices')->restrictOnDelete();
            $table->foreignId('split_from_invoice_id')->nullable()->constrained('invoices')->restrictOnDelete();
            $table->double('profit_difference')->default(0);
            $table->json('images');
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
        Schema::dropIfExists('invoices');
    }
};
