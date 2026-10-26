<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The colors in one packet of a purchase line, in pieces, chosen once for all its packets: a packet of 24 pieces can hold
     * 10 red pieces and 14 blue.
     */
    public function up(): void
    {
        Schema::create('invoice_item_colors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('color_id')->constrained()->restrictOnDelete();
            $table->double('pieces');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_item_colors');
    }
};
