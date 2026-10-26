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
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('image');
            $table->boolean('is_active')->default(true);
            $table->double('sale_price')->default(0);
            $table->unsignedInteger('pieces_per_packet')->nullable();
            $table->double('cost_price')->default(0);
            $table->double('last_cost_price')->default(0);
            $table->double('available_quantity')->default(0);
            $table->double('incoming_quantity')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
