<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Suppliers that exist before this migration get cash; after it, a new supplier must be given a payment type.
     */
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->string('payment_type')->nullable();
        });

        DB::table('suppliers')->update(['payment_type' => 'cash']);

        Schema::table('suppliers', function (Blueprint $table) {
            $table->string('payment_type')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn('payment_type');
        });
    }
};
