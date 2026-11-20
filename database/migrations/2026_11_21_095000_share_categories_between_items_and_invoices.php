<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->moveItemLinks('item_categories', 'invoice_categories');
    }

    public function down(): void
    {
        $this->moveItemLinks('invoice_categories', 'item_categories');
    }

    /**
     * Keep the invoice IDs and the old item category rows so the foreign key can also be moved back by name.
     */
    private function moveItemLinks(string $source, string $target): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('LOCK TABLE item_categories, invoice_categories, items IN SHARE ROW EXCLUSIVE MODE');
        }

        DB::table($source)->chunkById(200, function (Collection $categories) use ($target): void {
            $existing = DB::table($target)->whereIn('name', $categories->pluck('name'))->get()->keyBy('name');
            foreach ($categories as $category) {
                $matched = $existing->get($category->name);
                if ($matched === null) {
                    DB::table($target)->insert([
                        'name' => $category->name,
                        'created_at' => $category->created_at,
                        'updated_at' => $category->updated_at,
                        'deleted_at' => $category->deleted_at,
                    ]);
                } elseif ($category->deleted_at === null && $matched->deleted_at !== null) {
                    DB::table($target)->where('id', $matched->id)->update(['deleted_at' => null]);
                }
            }
        });

        Schema::table('items', function (Blueprint $table): void {
            $table->dropForeign(['item_category_id']);
        });

        DB::table('items')->whereNotNull('item_category_id')->update([
            'item_category_id' => DB::raw("(SELECT target_category.id FROM {$source} AS source_category
                JOIN {$target} AS target_category ON target_category.name = source_category.name
                WHERE source_category.id = items.item_category_id)"),
        ]);

        Schema::table('items', function (Blueprint $table) use ($target): void {
            $table->foreign('item_category_id')->references('id')->on($target)->restrictOnDelete();
        });
    }
};
