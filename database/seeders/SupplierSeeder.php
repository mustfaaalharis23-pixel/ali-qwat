<?php

namespace Database\Seeders;

use App\Models\Supplier;
use Illuminate\Database\Seeder;

class SupplierSeeder extends Seeder
{
    /**
     * Fake eyewear suppliers for local development. Running the seeder again does not duplicate them.
     *
     * @var array<int, array{name_ar: string, name_zh: ?string, phone: ?string, notes: ?string}>
     */
    private array $suppliers = [
        ['name_ar' => 'شركة ونتشو للنظارات', 'name_zh' => '温州眼镜有限公司', 'phone' => '+86 577 8888 1234', 'notes' => 'مورد الإطارات المعدنية.'],
        ['name_ar' => 'مصنع دانيانغ للعدسات', 'name_zh' => '丹阳镜片厂', 'phone' => '+86 511 8633 5566', 'notes' => 'عدسات طبية وعدسات حماية من الضوء الأزرق.'],
        ['name_ar' => 'شركة شيامن للنظارات الشمسية', 'name_zh' => '厦门太阳镜有限公司', 'phone' => '+86 592 5566 7788', 'notes' => null],
        ['name_ar' => 'شركة شنتشن للإكسسوارات', 'name_zh' => '深圳配件有限公司', 'phone' => '+86 755 2233 4455', 'notes' => 'علب النظارات وقماش التنظيف.'],
        ['name_ar' => 'شركة النور للشحن', 'name_zh' => null, 'phone' => '07701234567', 'notes' => 'شحن من الصين إلى بغداد.'],
        ['name_ar' => 'مكتب الرافدين للتخليص الكمركي', 'name_zh' => null, 'phone' => '07809876543', 'notes' => null],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ($this->suppliers as $supplier) {
            Supplier::firstOrCreate(['name_ar' => $supplier['name_ar']], $supplier);
        }
    }
}
