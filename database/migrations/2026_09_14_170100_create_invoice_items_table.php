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
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->foreignId('specific_description_id')->constrained()->restrictOnDelete();
            $table->foreignId('item_id')->nullable()->constrained()->restrictOnDelete();
            $table->double('price');
            $table->double('unit_cost')->nullable();
            $table->double('unit_cost_usd')->nullable();
            $table->double('price_usd')->nullable();
            $table->double('unit_cost_iqd')->nullable();
            $table->double('price_iqd')->nullable();
            $table->double('quantity');
            $table->double('boxes_count')->nullable();
            $table->double('box_quantity')->nullable();
            $table->double('boxes_per_carton')->nullable();
            $table->double('cartons_count')->nullable();
            $table->double('carton_cbm')->nullable();
            $table->double('cbm')->default(0);
            $table->double('transport_cost_usd')->nullable();
            $table->double('transport_cost_per_piece_usd')->nullable();
            $table->double('transport_cost_iqd')->nullable();
            $table->double('transport_cost_per_piece_iqd')->nullable();
            $table->double('final_unit_cost_iqd')->nullable();
            $table->date('produced_at')->nullable();
            $table->double('profit_difference')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};
