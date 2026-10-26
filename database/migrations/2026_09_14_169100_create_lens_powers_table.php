<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The lens powers the purchased glasses come in, such as 1.50. It is created before the invoice lines, which point to it.
     */
    public function up(): void
    {
        Schema::create('lens_powers', function (Blueprint $table) {
            $table->id();
            $table->decimal('value', 5, 2)->unique();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lens_powers');
    }
};
