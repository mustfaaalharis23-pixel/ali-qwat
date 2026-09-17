# بحث: نظام المحاسبة (Accounting)

> **الحالة: قيد النقاش (2026-09-14).** أي تغيير بالاتفاقات ينكتب هنا أول.
> المرجع: توثيق النظام القديم (Nova) بـ `docs/invoices/research.md`. هذا الملف ينقل منه بس اللي يخص waqt.
> الاتفاقات العامة للموديلات والصلاحيات بـ `docs/users/research.md` و `docs/users/plan.md`.

## 1. الاتفاقات الأساسية
1. **ماكو عدة ماركتات.** النظام لمصنع واحد، فما نحتاج تقييد البيانات بالماركت.
2. **الشغل ينقسم قسمين، والاثنين ضمن الخطة الحالية** (تحديث 2026-09-14):
   - **القسم الأول:** الوصوف العامة، والوصوف الخاصة، والفواتير العامة.
   - **القسم الثاني:** فواتير الشراء. كانت مؤجلة وصارت ضمن الخطة، وتفاصيلها تنحسم بالنقاش (5.2).
3. **ربط الوصف الخاص بالصناديق (`transfer_type`) مؤجل.**

## 2. المفهوم (من النظام القديم)
### 2.1 داخل القسم الأول
- **كل حركة فلوس غير البيع هي فاتورة.**
- **الوصف العام** تصنيف رئيسي، إله اتجاه: داخل (`input`) لو خارج (`output`).
- **الوصف الخاص** تصنيف تفصيلي تحت وصف عام واحد، ويورث اتجاهه.
- **الفاتورة** تنتمي لوصف عام واحد، وبنودها كل واحد ينتمي لوصف خاص تحت نفس الوصف العام.
- **الفاتورة العامة** فلوس بس: ما بيها مخزون ولا دين، وتنحسب مدفوعة بالكامل.

```
وصف عام (الاتجاه)
 └─ وصف خاص
       ▲
فاتورة ── وصف عام
 └─ بند ── وصف خاص
```

### 2.2 القسم الثاني وما بعده
> أي من هذه يدخل بفواتير الشراء هسه ينحسم بالنقاش (5.2). الصناديق تبقى مؤجلة (الاتفاق 3).

- **وصف المادة:** وصف خاص مرتبط بمادة (`item_id`)، وينشأ تلقائياً ويا المادة.
- **فاتورة الشراء:** مورد، دفعات مخزون (Batches)، حالة معلّقة أو مؤكدة، تسديدات، ودين.
- **الصناديق:** `transfer_type` على الوصف الخاص، و `cashier_id` على الفاتورة.
- **الروابط الذاتية:** البند المجاني (ألغاه صاحب المشروع، plan 6.6)، فاتورة الإرجاع، التحويل الداخلي.
- **المطابقة:** النتيجة المحاسبية مقابل قيمة الماركت. تعتمد على المخزون والصناديق والديون.

## 3. الوضع الحالي بالمشروع
- Laravel 13.31.0، Filament 5.8.1، PostgreSQL 17.4.
- **الموديلات الموجودة:** `User` و `Item` بس.
- **ماكو** أي جدول أو موديل للوصوف أو الفواتير.
- **`Item`** (`app/Models/Item.php`) ما مرتبط بأي وصف. هذا الربط يخص القسم الثاني.

## 4. شي بالنظام القديم مرتبط بالقسم الأول
### 4.1 مرتبط بأشياء أجّلناها أو ما نحتاجها
> حسب plan 2: من حقول الشراء ينبنى هسه بس فاصل الشراء وجدول `suppliers` و `supplier_id` و `paid_amount` و `remaining`. الباقي يخص فواتير الشراء (plan 6): صارت ضمن الخطة، وتفاصيلها تنحسم بالنقاش.

| بالنظام القديم | السبب |
|---|---|
| `market_id` على كل الجداول | الاتفاق 1: مصنع واحد |
| `specific_descriptions.item_id` | وصف المادة، القسم الثاني |
| `general_descriptions.is_purchases_description` | الفاصل بين الشراء والعامة، القسم الثاني |
| `invoices.status` و `checker_id` | تأثيرها فعلياً على فواتير الشراء بس |
| `supplier_id` و `balance_snapshot` و `payoffs` | المورد والدين، القسم الثاني |
| `batches` وحقول الكمية والكراتين والصلاحية | المخزون، القسم الثاني. المجاني والهدية ألغاهن صاحب المشروع (plan 6.6) |
| `profit_difference` | إعادة تقييم المخزون، القسم الثاني |
| `transfer_type` و `cashier_id` و `cash_van_driver_id` | الاتفاق 3: الصناديق مؤجلة |
| `original_invoice_id` و `to_market` و `is_defective` و `porterage_fee` | إرجاع، تحويل داخلي، تالف (ألغاه صاحب المشروع، plan 6.6)، حمالة |

### 4.2 سلوك الفاتورة العامة بالنظام القديم
- **البنود:** كل بند وصف خاص + سعر. الكمية تنفرض `1`.
- **المبالغ:** `amount` = مجموع أسعار البنود، و `paid_amount = amount`، و `remaining = 0`.
- **الحقول:** الاسم، التاريخ (إجباري)، المستخدم المنشئ، الصور، الملاحظات.
- **الفلاتر:** مدى التاريخ، والوصف العام أو الخاص.
- **التعديل:** بس `is_super`. **الحذف:** `is_super`، أو خلال 24 ساعة من الإنشاء.
- **الوصف العام:** بيه ترتيب (`order`) وتعطيل (`is_disabled`)، والوصف المعطّل ما يطلع بالفورم.
- **أوصاف يعتمد عليها الكود بالرقم أو بالاسم:** استثمار (1)، إيرادات خارجية (4)، ارجاع رأس مال (بالاسم). الحاسبة والتقارير تفرق بيها.

## 5. أسئلة مفتوحة
> ما محسومة. تنحسم بالنقاش وتنتقل لـ `docs/accounting/plan.md`.
> ✅ **تحديث 2026-09-14:** صاحب المشروع فوّض: "ابني كلشي مثل ما موجود بالدوكيومنتيشن". الأسئلة 2 و5 و9 و10 و15 إلى 22 انحسمت (plan 6). السؤالين 6 و14 مؤجلين ويا التقارير (plan 6.6).

### 5.1 القسم الأول

1. ✅ **محسوم (plan 1).** ~~الاتجاه (داخل/خارج): بالنظام القديم ينحفظ على الوصف العام والخاص والفاتورة. وين نحفظه؟~~
2. **البنود:** الفاتورة العامة إلها بنود متعددة، لو مبلغ ووصف واحد؟
3. ✅ **محسوم (plan 3).** ~~الدفع: نحتاج `paid_amount` و `remaining` بالفاتورة العامة، لو هي دائماً مدفوعة بالكامل؟~~
4. ✅ **محسوم (plan 2).** ~~التجهيز للقسم الثاني: نضيف فاصل الشراء (`is_purchases_description`) من هسه، لو ينضاف بالقسم الثاني؟~~
5. ⏸️ **مؤجل (plan 4).** المورد بالفواتير العامة: بالقديم الوصف العام يكدر يطلب مورد (`has_suppliers`). نحتاجه بالقسم الأول؟
6. **الأوصاف المميزة** (استثمار، إيرادات خارجية، ارجاع رأس مال): شلون الكود يميزها بدون رقم أو اسم مكتوب بالكود؟
7. ✅ **محسوم (plan 4).** ~~اسم الوصف: حقل واحد لكل اللغات مثل `Item`، لو اسم لكل لغة؟~~
8. ✅ **محسوم (plan 4).** ~~نوع المبالغ بالـ DB: بالقديم `double`. شنو نستخدم؟~~
9. **حالة الفاتورة العامة:** نحتاج معلّقة/مؤكدة، لو ما إلها معنى بدون شراء؟
10. **شروط التعديل والحذف:** نفس القديم (سوبر، و 24 ساعة للحذف)، لو بس `ModelPolicy`؟
11. ✅ **محسوم (plan 4).** ~~حذف وصف عليه فواتير: يُمنع، لو يُسمح بالـ soft delete؟~~
12. ✅ **محسوم (plan 4).** ~~الحقول الإضافية بالفاتورة: الاسم، الصور، الملاحظات، المستخدم المنشئ. شنو نحتاج منها؟~~
13. ✅ **محسوم (plan 4).** ~~الترتيب والتعطيل للوصف العام والخاص: نحتاجهم؟~~
14. **التقارير بالقسم الأول:** بدون صناديق، شنو نعرض؟ (مثلاً مجموع الداخل والخارج، أو مصاريف حسب الوصف).

### 5.2 القسم الثاني: فواتير الشراء
15. **وصف المادة:** الوصف الخاص يرتبط بالمادة (`item_id`) وينشأ تلقائياً وياها مثل القديم، لو البند يشير للمادة مباشرة؟
16. **الحالة والتأكيد:** نحتاج معلّقة/مؤكدة و `checker_id`؟ وشروط التأكيد (صورة، تطابق المبلغ)؟
17. **المخزون:** نحتاج `batches` وأعمدة الكمية والكلفة على `items`؟ والكمية تدخل المخزن وقت الحفظ لو وقت التأكيد؟
18. **البند:** شنو حقوله؟ (كمية، كراتين وسعة الكارتون، سعر السطر لو سعر الوحدة، صلاحية).
19. **الدين والتسديدات:** نحتاج جدول `payoffs`؟ وشلون ينحسب `paid_amount` و `remaining`؟ ونوع دفع المورد (`payment_type`: كاش/آجل، plan 5) شنو يأثر على الفاتورة؟
20. **الحالات الخاصة:** أي منها يدخل هسه: المجاني، الهدية، التالف، الإرجاع، فرق الربحية (`profit_difference`)؟ (المجاني والهدية والتالف ألغاهن صاحب المشروع، plan 6.6)
21. **الشاشة:** فواتير الشراء شاشة مستقلة عن الفواتير العامة على نفس الجدول، مثل القديم؟
22. **شروط التعديل والحذف:** نفس القديم (تعديل خلال 24 ساعة، حذف سوبر وبدون تسديدات)، لو بس `ModelPolicy`؟

## 6. معلومات من الدوكيومنتيشن والكود (القسم الأول)
> **معلومات، مو قرارات.** كل شي هنا خيارات متوفرة، والاختيار ينحسم بالنقاش.
> تاريخ البحث: 2026-09-14. المسارات نسبةً لجذر المشروع، والأرقام بعد `:` أرقام أسطر.
> البحث هنا يخص القسم الأول. بحث فواتير الشراء (القسم الثاني) بعده ما صار.

### 6.1 الإصدارات
- Laravel 13.31.0، Filament 5.8.1، Livewire 4.4.4، PHPUnit 12.5.35، PostgreSQL 17.4.
- PHP intl بنسخة ICU 76.1.

### 6.2 الداتابيس (Laravel)
#### الربط بين الجداول
- **`$table->foreignId('x_id')->constrained()`** ينشئ العمود والقيد سوية.
- **شنو يصير للابن إذا انحذف الأب:**
  | الـ method | النتيجة |
  |---|---|
  | `cascadeOnDelete()` | ينحذف الابن ويا الأب |
  | `restrictOnDelete()` | يُمنع حذف الأب إذا عنده أبناء |
  | `nullOnDelete()` | يصير عمود الابن `null` |
  | `noActionOnDelete()` | يُمنع الحذف إذا عنده أبناء |
- **القيد يشتغل بس بالحذف الحقيقي من الداتابيس.** الـ soft delete تحديث لعمود `deleted_at`، فما يوصل للقيد.
  - يعني وصف عليه فواتير ينحذف soft delete عادي حتى ويا `restrictOnDelete()`، والمنع يصير بس بالحذف النهائي (forceDelete)، ويطلع `QueryException`.
- **الـ modifiers** (مثل `nullable()`) لازم تنكتب قبل `constrained()`.

#### المبالغ
- **عمود `decimal(total, places)`** يصير بـ PostgreSQL نوع `decimal(total, places)` (`vendor/laravel/framework/src/Illuminate/Database/Schema/Grammars/PostgresGrammar.php:950-953`).
- **الـ cast `'decimal:2'`** يرجع **نص (string)** مو رقم، ويقرّب بـ `HalfUp` عن طريق `BigDecimal` (`vendor/laravel/framework/src/Illuminate/Database/Eloquent/Concerns/HasAttributes.php:1536-1543`).
- **البديل** عمود `double` مثل النظام القديم، وهذا نوع تقريبي (floating point).
- **validation:** `decimal:2` لازم بالضبط منزلتين عشريتين، و `decimal:0,2` من صفر لمنزلتين.

#### الاتجاه (داخل/خارج) وأي قيم ثابتة
- **Enum casting:** `'type' => InvoiceType::class` بـ `casts()` يحول القيمة من وإلى PHP backed enum.
- **عمود `$table->enum()`** يصير بـ PostgreSQL `varchar(255)` ويا `check` بالقيم المسموحة (`PostgresGrammar.php:972-979`). تغيير القيم بعدين يحتاج migration.
- **البديل:** عمود `string` عادي ويا الـ cast.
- **validation:** `Rule::enum(X::class)`، وبيه `only()` و `except()`.

#### أشياء ثانية
- **`DB::transaction(fn () => ...)`:** إذا صار exception كلشي يرجع (rollback).
- **المجاميع بدون تحميل السجلات:** `withSum('items', 'amount')` يضيف `items_sum_amount`، و `withCount('items')` يضيف `items_count`.
- **`Rule::exists(...)->withoutTrashed()`** يتجاهل السجلات المحذوفة soft delete (`vendor/laravel/framework/src/Illuminate/Validation/Rules/DatabaseRule.php:184`).
- **Factories:** `Invoice::factory()->has(InvoiceItem::factory()->count(3), 'items')` ينشئ فاتورة ويا بنودها.

### 6.3 صفحات Filament (Resource)
#### الـ soft delete بالشاشة
- نفس نمط `ItemResource` الموجود:
  - `TrashedFilter` بالجدول.
  - `DeleteAction` و `RestoreAction` و `ForceDeleteAction`، وأخواتهن الـ bulk.
  - `getRecordRouteBindingEloquentQuery()` بدون `SoftDeletingScope` (`app/Filament/Resources/Items/ItemResource.php:69-75`).
- الـ generator: `php artisan make:filament-resource X --soft-deletes`.

#### ترتيب الحفظ
- **صفحة الإضافة** (`vendor/filament/filament/src/Resources/Pages/CreateRecord.php:92-123`):
  1. تبدي transaction (إذا مفعّل).
  2. validation، ثم `mutateFormDataBeforeCreate($data)`.
  3. `handleRecordCreation()`: ينشئ الموديل ويسوي `save()` (`:219-231`).
  4. `saveRelationships()`: هنا تنحفظ البنود (Repeater).
  5. `afterCreate()`.
- **صفحة التعديل:**
  - **البنود تنحفظ قبل الفاتورة نفسها.** `getState()` يسوي `saveRelationships()` داخله (`vendor/filament/schemas/src/Concerns/HasState.php:497-501`).
  - بعدها يجي `mutateFormDataBeforeSave()` ثم `handleRecordUpdate()`، اللي يسوي `$record->update($data)` (`EditRecord.php:166-176` و `:281-286`).
  - هذا مهم إذا مجموع الفاتورة ينحسب من البنود: وقت التعديل البنود الجديدة تكون محفوظة قبل تحديث الفاتورة.
- **الـ hooks المتوفرة:** `beforeFill` و `afterFill` و `beforeValidate` و `afterValidate` و `beforeCreate` و `afterCreate` (وبالتعديل `beforeSave` و `afterSave`).

#### الـ transactions
- **مطفية افتراضياً على الـ panel** (`vendor/filament/filament/src/Panel/Concerns/HasDatabaseTransactions.php:9`).
- **الصفحة تتبع الـ panel** إلا إذا حددنا `$hasDatabaseTransactions` عليها (`vendor/filament/filament/src/Pages/Concerns/CanUseDatabaseTransactions.php:11-15`).
- **طرق التفعيل:** `->databaseTransactions()` على الـ panel، أو `protected ?bool $hasDatabaseTransactions = true` على صفحة، أو `->databaseTransaction()` على action.
- **بدون transaction:** إذا فشل حفظ بند بعد ما انحفظت الفاتورة، الفاتورة تبقى بالداتابيس.

#### الصلاحيات
- Filament ينادي `viewAny` و `view` و `create` و `update` و `delete` و `deleteAny` و `restore` و `restoreAny` و `forceDelete` و `forceDeleteAny`.
- **ترتيب الجدول بالسحب (`reorderable`)** يفحص `reorder()` بالـ Policy. `ModelPolicy` ما يعرّفها (`docs/users/plan.md` 2.1)، فإذا احتجناها تنحسم وقتها.

### 6.4 الفورم
#### البنود: Repeater مربوط بعلاقة `HasMany`
- **`Repeater::make('items')->relationship()`:** يحمّل البنود من العلاقة، وينشئها ويعدلها ويحذفها وقت الحفظ.
- **الحذف:** البند اللي ينشال من الفورم ينحذف بـ `$record->delete()` (`vendor/filament/forms/src/Components/Repeater.php:1013-1022`). إذا موديل البند بيه SoftDeletes، يصير soft delete.
- **التحميل:** البنود تنجاب بـ query العلاقة (`Repeater.php:1206-1262`)، فالبنود المحذوفة soft delete ما تطلع.
- **ويا `relationship()`:**
  - الـ repeater ما ينرسل ويا `$data` مال الفاتورة (`dehydrated(false)`).
  - الترتيب مطفي (`reorderable(false)`) (`Repeater.php:985-987`).
  - الترتيب يحتاج عمود بجدول البنود و `->orderColumn('sort')`.
- **الشكل:** `->table([TableColumn::make(...)])` يعرض البنود كجدول، و `->compact()` يصغّره.
- **validation:**
  - `->minItems()` و `->maxItems()` لعدد البنود.
  - `->distinct()` على حقل داخل البند يمنع تكرار نفس القيمة بين البنود.
- **تعديل بيانات البند قبل الحفظ:** `mutateRelationshipDataBeforeCreateUsing()` و `mutateRelationshipDataBeforeSaveUsing()`، ويرجعن `null` حتى ما ينحفظ البند. وأكو `afterCreate()` لكل بند.
- **الدوكيومنتيشن:** الـ Repeater يناسب إذا البند بيه حقول قليلة. **البديل** Relation Manager: جدول تحت صفحة التعديل، والبنود تنضاف بعد ما تنحفظ الفاتورة.

#### الوصف الخاص يعتمد على الوصف العام
- **الحقل الأب `->live()`**، والحقل الابن `->options(fn (Get $get) => ...)` يقرا قيمته.
- **داخل الـ Repeater** `$get()` يقرا من نفس البند. للوصول لحقل بالفاتورة: `$get('../../general_description_id')`.
- **تصفير الأبناء:** `->afterStateUpdated(fn (Set $set) => ...)` على الأب.
- **Select مربوط بعلاقة:** `->relationship('x', 'name', modifyQueryUsing: fn (Builder $query) => ...)`، ويا `->searchable()` و `->preload()`.
- **الأمان:**
  - القيمة المختارة لازم إلها label من خيارات الحقل، وإلا الـ validation يفشل (`vendor/filament/forms/src/Components/Select.php:1786-1800`).
  - الدوكيومنتيشن يقول الفلترة الحقيقية تكون بـ `modifyQueryUsing` مال `relationship()`، لأن Filament يفحص القيمة المرسلة ضدها.

#### حقول ثانية
- **المبلغ:** `TextInput::make('amount')->numeric()->step(...)`، ويا `->prefix()` و `->suffix()`.
- **التاريخ:** `DatePicker`. الـ timezone ما ينطبق على حقل تاريخ بدون وقت.
- **صور متعددة:** `FileUpload::make('images')->multiple()` يحفظ JSON، ويحتاج cast `array` بالموديل. وأكو `->minFiles()` و `->maxFiles()`. نمط الصورة الواحدة موجود بـ `ItemForm.php`.
- **Enums بـ Filament:**
  - `HasLabel` للاسم (يكدر يرجع `__()` حتى يترجم)، و `HasColor` و `HasDescription` و `HasIcon`.
  - `Select` و `Radio` و `ToggleButtons` تاخذ `->options(X::class)`.
  - `TextColumn` ويا `->badge()` يلوّن تلقائياً إذا العمود cast لـ enum.
  - `->enum(X::class)` للـ validation.
- **`->unique()`** يتجاهل السجل الحالي بالتعديل افتراضياً (`vendor/filament/forms/src/Components/Concerns/CanBeValidated.php:563-570`)، و `->exists()` و `->scopedExists()` متوفرات.

### 6.5 الجدول
- **المجموع:** `TextColumn::make('amount')->summarize(Sum::make())` يحسبه بـ `sum()` على query الجدول (`vendor/filament/tables/src/Columns/Summarizers/Sum.php:19-32`).
  - يطلع سطر لمجموع الصفحة وسطر للمجموع الكلي.
  - **أول عمود بالجدول ما يقبل summarizer.**
- **التجميع:** `->groups([...])` و `->defaultGroup(...)`، ويا `->groupsOnly()` يعرض بس مجموع كل مجموعة. يفيد لتقرير مثل "مصاريف حسب الوصف".
- **تنسيق الأرقام:** `->numeric()` و `->money('IQD')` يستخدمن `Number::format` و `Number::currency` بلغة التطبيق (`vendor/filament/tables/src/Columns/Concerns/CanFormatState.php:250` و `:291`).
  - بـ ICU 76.1 وبالعربي الأرقام تطلع لاتينية: `1,234.5`.
  - الدينار العراقي يطلع بدون كسور: `‏1,234 د.ع.‏`.
- **الفلاتر:**
  - `SelectFilter::make('x')->relationship('x', 'name')->searchable()->preload()`.
  - مدى تاريخ: `Filter` مخصص بحقلين `DatePicker`، أو `DateConstraint` بالـ Query Builder.
- **ترتيب السجلات بالسحب:** `->reorderable('sort')`، ويحتاج `reorder()` بالـ Policy (6.3).

### 6.6 العربي والإنكليزي
- **اتجاه الصفحة:** Filament ياخذه من `__('filament-panels::layout.direction')`، والعربي `rtl` (`vendor/filament/filament/resources/views/components/layout/base.blade.php:16` و `vendor/filament/filament/resources/lang/ar/layout.php:5`).
- **اللغات:** `config/app.php:94`، والـ middleware `app/Http/Middleware/SetLocale.php`.
- **نصوص الشاشات:** بـ `lang/{ar,en}/filament/resources/{resource}.php`، مثل `item.php`.
- **نصوص الـ enums:** تنكتب بنفس الطريقة وتنقرا بـ `getLabel()`.

### 6.7 الـ tests
- **المشروع يستخدم PHPUnit مو Pest.**
  - أمثلة الدوكيومنتيشن مكتوبة بـ `livewire(...)` مال Pest. المقابل بـ PHPUnit هو `Livewire::test(CreateX::class)` (`vendor/livewire/livewire/src/LivewireManager.php:238`).
- **داتابيس الـ tests:** `pgsql` باسم `waqt_testing` (`phpunit.xml:26-27`)، ويا `RefreshDatabase` مثل `tests/Unit/ItemTest.php`.
- **صفحة الإضافة والتعديل:**
  - `fillForm([...])`، ثم `call('create')` أو `call('save')`.
  - بعدها `assertHasNoFormErrors()` أو `assertHasFormErrors([...])`، و `assertNotified()`.
- **الـ Repeater:** `Repeater::fake()` بأول الـ test يخلي مفاتيح البنود أرقام بدل UUID.
- **الجدول:**
  - `assertCanSeeTableRecords()` و `searchTable()` و `filterTable()`.
  - الـ actions: `callAction(TestAction::make(DeleteAction::class)->table($record))`.
- **Relation Manager:** ينفحص كـ Livewire component، ويا `ownerRecord` و `pageClass`.
- **الدخول:** صفحات الـ panel تفحص الدخول والصلاحية، فالـ test يحتاج `actingAs()` ليوزر فعّال (`is_active`) عنده الصلاحية أو `is_super` (`docs/users/plan.md` 1.5).
