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
     * The cash and postpaid withdrawal descriptions are added under the revenues general description, each with its transfer type.
     * The transfer type is the box (cash or postpaid) that a line of the description withdraws money from.
     */
    public function up(): void
    {
        Schema::create('specific_descriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('general_description_id')->constrained()->restrictOnDelete();
            $table->foreignId('item_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('transfer_type')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        $revenuesId = DB::table('general_descriptions')
            ->where('name', 'ايرادات')
            ->where('type', 'input')
            ->value('id');

        DB::table('specific_descriptions')->insert([
            [
                'general_description_id' => $revenuesId,
                'name' => 'سحب أموال كاش',
                'transfer_type' => 'cash',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'general_description_id' => $revenuesId,
                'name' => 'سحب أموال آجل',
                'transfer_type' => 'postpaid',
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
        Schema::dropIfExists('specific_descriptions');
    }
};
