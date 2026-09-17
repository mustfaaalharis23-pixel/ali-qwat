<?php

namespace Database\Seeders;

use App\Enums\InvoiceType;
use App\Models\GeneralDescription;
use App\Models\SpecificDescription;
use Illuminate\Database\Seeder;

class DescriptionSeeder extends Seeder
{
    /**
     * Fake general descriptions for local development, each with the specific descriptions under it.
     * The purchases and withdrawals descriptions are not here, because their migrations add them. Running the seeder again does not duplicate them.
     *
     * @var array<int, array{name: string, type: InvoiceType, specific: list<string>}>
     */
    private array $descriptions = [
        ['name' => 'رواتب', 'type' => InvoiceType::Output, 'specific' => ['رواتب الموظفين', 'رواتب العمال', 'مكافآت']],
        ['name' => 'ايجارات', 'type' => InvoiceType::Output, 'specific' => ['ايجار المعمل', 'ايجار المخزن']],
        ['name' => 'تشغيلية', 'type' => InvoiceType::Output, 'specific' => ['كهرباء', 'ماء', 'وقود المولدة', 'صيانة المكائن', 'انترنت']],
        ['name' => 'رسوم حكومية', 'type' => InvoiceType::Output, 'specific' => ['رسوم كمركية', 'ضرائب']],
        ['name' => 'شحن وتخليص', 'type' => InvoiceType::Output, 'specific' => ['شحن بحري', 'شحن جوي', 'تخليص كمركي']],
        ['name' => 'نثرية', 'type' => InvoiceType::Output, 'specific' => ['قرطاسية', 'ضيافة', 'تنظيف']],
        ['name' => 'عمولات', 'type' => InvoiceType::Output, 'specific' => ['عمولة مبيعات']],
        ['name' => 'مصاريف غير متوقعة', 'type' => InvoiceType::Output, 'specific' => ['مصاريف طارئة']],
        ['name' => 'استثمار', 'type' => InvoiceType::Input, 'specific' => ['استثمار الشركاء']],
        ['name' => 'ايرادات خارجية', 'type' => InvoiceType::Input, 'specific' => ['بيع خردة', 'ايرادات أخرى']],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ($this->descriptions as $description) {
            $general = GeneralDescription::firstOrCreate(
                ['name' => $description['name']],
                ['type' => $description['type']],
            );

            foreach ($description['specific'] as $name) {
                SpecificDescription::firstOrCreate([
                    'general_description_id' => $general->id,
                    'name' => $name,
                ]);
            }
        }
    }
}
