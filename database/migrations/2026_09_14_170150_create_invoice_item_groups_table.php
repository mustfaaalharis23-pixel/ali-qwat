<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The groups the packets of a purchase line are bought in: some packets at one lens power (or without one) and one mix of colors,
     * such as 6 packets of 1.00 and 4 of 1.50. A line without any is bought without a power and without colors.
     */
    public function up(): void
    {
        Schema::create('invoice_item_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('lens_power_id')->nullable()->constrained()->restrictOnDelete();
            $table->double('boxes_count');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_item_groups');
    }
};
