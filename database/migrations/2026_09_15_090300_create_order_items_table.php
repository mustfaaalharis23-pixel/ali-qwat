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
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            $table->foreignId('lens_power_id')->nullable()->constrained()->restrictOnDelete();
            $table->json('colors')->nullable();
            $table->double('quantity');
            $table->unsignedInteger('pieces')->default(0);
            $table->string('package')->default('packet');
            $table->unsignedInteger('package_size')->default(1);
            $table->double('price');
            $table->double('total_price');
            $table->double('cost_price')->default(0);
            $table->double('total_cost')->default(0);
            $table->double('profit')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
