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
            $table->double('quantity');
            $table->double('boxes_count')->nullable();
            $table->double('box_quantity')->nullable();
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
