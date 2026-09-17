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
     * The single purchases general description is added with the table, so every item can get its description under it.
     * The revenues general description is added too, for the money moved from the cash and postpaid boxes to the accountant.
     */
    public function up(): void
    {
        Schema::create('general_descriptions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type');
            $table->boolean('is_purchases_description')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        DB::table('general_descriptions')->insert([
            [
                'name' => 'مشتريات شركات',
                'type' => 'output',
                'is_purchases_description' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'ايرادات',
                'type' => 'input',
                'is_purchases_description' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('general_descriptions');
    }
};
