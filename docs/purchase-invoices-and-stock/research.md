# بحث: فواتير الشراء ودخول الكميات للمخزن

> **الحالة: مثبتة (2026-09-20).** الكود الحالي هو المرجع (قرار صاحب المشروع، 2026-09-20).
> **آخر تحقق: 2026-09-22.** كل مرجع `ملف:سطر` انفتح وطابق الكود، والـ tests انعادت.
> **تحل محل:**
> - خطة 15/9 (`~/.claude/plans/soft-zooming-dijkstra.md`): بدون `batches`، وجدول `invoice_confirmations`، وصور المستلم... كلها انلغت.
> - أقسام التأكيد والمخزون بـ `docs/purchase-invoices-and-payoffs/research.md`: بيها "التأكيد ما انبنى" و"ملفات فارغة"، وهذا قديم. باقي الصفحة (الدين والتسديدات) بعده صحيح.
>
> المسارات نسبةً لجذر المشروع، والرقم بعد `:` رقم السطر. المشروع بعده لوكل فقط.

## 1. الخلاصة
- **السلسلة:** فاتورة (`invoices`) ← بنود (`invoice_items`) ← دفعة لكل بند (`batches`) ← `Item::recalculateStock()`.
- **مرحلتين للتأكيد:**
  1. **المستورد:** يصير تلقائياً لحظة الخلق، والخالق هو المستورد.
  2. **المستلم:** يدوي، يكتب مبلغ الفاتورة مرة ثانية.
- **الكمية تنقسم لرقمين منفصلين:**
  - **القادم** (`incoming_quantity`): بعد تأكيد المستورد. معلومة بس، ما تنباع.
  - **المتوفر** (`available_quantity`): بعد تأكيد المستلم بس، ويا الكلفة.
- **ماكو جمع وطرح أبداً.** كل تغيير يعيد حساب أرقام المادة من الصفر، فما تنحرف.
- **الدين يبدي من لحظة الخلق**، وما مربوط بالتأكيد.
  - زر "تسديد" بجدول الموردين يوزع مبلغ واحد على فواتير المورد اللي عليها باقي، من الأقدم للأحدث (`app/Invoices/DistributeSupplierPayoff.php`).

## 2. الجداول
> المبالغ والكميات `double`، والقيود `restrictOnDelete`، وكل الجداول بيها soft delete.

### 2.1 `invoices` (الأعمدة اللي تخص الشراء والتأكيد)
`database/migrations/2026_09_14_170000_create_invoices_table.php`

| العمود | المعنى |
|---|---|
| `status` | `pending` (افتراضي) ← `importer_confirmed` ← `confirmed` |
| `importer_checker_id`، `importer_confirmed_at` | منو أكد كمستورد ووكتيش. بالخلق = `user_id` |
| `receiver_checker_id`، `receiver_confirmed_at` | منو أكد كمستلم ووكتيش |
| `amount` | مجموع أسعار البنود، ينحسب تلقائياً |
| `paid_amount`، `remaining` | من التسديدات (التفاصيل بالصفحة القديمة) |
| `cbm` | مجموع CBM البنود، ينحسب ويا المبلغ بـ `recalculateAmounts` |
| `images` | صور الفاتورة، إجبارية |
| `original_invoice_id` | للإرجاع، ما مستخدم |
| `profit_difference` | موجود، ومحد يكتبه |

### 2.2 `invoice_items`
| العمود | المعنى |
|---|---|
| `specific_description_id` | وصف المادة، لازم يكون تحت وصف المشتريات |
| `item_id` | ينقرأ تلقائياً من الوصف الخاص |
| `boxes_count`، `box_quantity` | عدد الباكيتات وسعة الباكيت (الاسم القديم `box` = باكيت) |
| `quantity` | `boxes_count × box_quantity`، ينحسب تلقائياً |
| `cartons_count`، `carton_cbm` | عدد الكراتين وحجم الكارتونة الواحدة بالمتر المكعب. ما إلهن علاقة بالباكيت. إجباريات بالشاشة، وفارغات لبند الفاتورة العامة |
| `cbm` | `cartons_count × carton_cbm`، ينحسب تلقائياً. صفر لبند الفاتورة العامة |
| `price` | سعر البند كامل، مو سعر الوحدة. بالشاشة ينحسب من كلفة القطعة |
| `produced_at` | موجود، ومخفي من الشاشة |
| `profit_difference` | موجود، ومحد يكتبه |

### 2.3 `batches`
`database/migrations/2026_09_16_190958_create_batches_table.php`، الموديل `app/Models/Batch.php`

| العمود | المعنى |
|---|---|
| `invoice_id`، `invoice_item_id`، `item_id` | الفاتورة والبند والمادة |
| `quantity` | نفس كمية البند |
| `purchase_price` | سعر الوحدة: `abs(price) ÷ abs(quantity)`، وصفر إذا الكمية صفر |

- **نسخة من البند، ما تنكتب بالإيد.** تنكتب ويا البند بـ `saveQuietly` (بلا audit).
- **تنحذف ويا البند:** حذف البند يحذفها soft، والحذف النهائي يحذفها نهائياً (`InvoiceItem::booted`، `:57-67`).

### 2.4 `items` (أعمدة المخزون)
`database/migrations/2026_09_13_230459_create_items_table.php`

| العمود | المعنى | منو يكتبه |
|---|---|---|
| `available_quantity` | المتوفر | `recalculateStock` / `recalculateAvailableQuantity` |
| `incoming_quantity` | القادم (بالطريق) | `recalculateStock` |
| `cost_price` | كلفة الوحدة الحالية | `recalculateStock` |
| `last_cost_price` | الكلفة اللي قبلها | `recalculateStock` |

- **الأربعة للقراءة بس بالشاشة:** مو fillable، وبفورم المادة `disabled` و`dehydrated(false)`.

## 3. الخلق خطوة بخطوة
| # | الخطوة | وين | أثرها على المخزون |
|---|---|---|---|
| 1 | الفورم: الاسم، المورد، التاريخ، البنود، الدفعة الأولى، الصور، الملاحظات | `PurchaseInvoiceForm::configure` (`app/Filament/Resources/PurchaseInvoices/Schemas/PurchaseInvoiceForm.php:39`) | — |
| 2 | الوصف العام = المشتريات، النوع = خارج، `user_id` = المستخدم. الدفعة الأولى تنشال من البيانات وتنحفظ على الصفحة | `CreatePurchaseInvoice::mutateFormDataBeforeCreate` (`app/Filament/Resources/PurchaseInvoices/Pages/CreatePurchaseInvoice.php:62`) | — |
| 3 | الفاتورة تنحفظ `pending`. بالـ `saving`: الوصف ما يتغير، النوع يطابق الوصف، المورد إجباري، المدفوع والباقي | `Invoice::booted` (`app/Models/Invoice.php:58`) | `StockObserver` يشتغل، بس ماكو batches بعد |
| 4 | كل بند: `item_id` من الوصف، الكمية = كراتين × كمية، والمبلغ يغطي المدفوع | `InvoiceItem::applyInvoiceRules` (`app/Models/InvoiceItem.php:136`) | — |
| 5 | بعد حفظ البند: مبلغ الفاتورة ينعاد حسابه، والـ batch ينكتب | `InvoiceItem::booted` (`:52`)، `syncBatch` (`:175`) | `recalculateStock` للمادة: الفاتورة `pending`، فما يتغير شي |
| 6 | تأكيد المستورد التلقائي: الحالة `importer_confirmed`، والمستورد = `user_id`، بلا صلاحية وبلا مبلغ | `CreatePurchaseInvoice::afterCreate` (`:30`) ← `ConfirmPurchaseInvoice::handleOnCreation` (`app/Invoices/ConfirmPurchaseInvoice.php:42`) | **القادم يزيد** بكمية البنود. المتوفر والكلفة ما يتغيرن |
| 7 | الدفعة الأولى (إذا أكبر من صفر): تسديد بعلامة `is_initial_payment`، وبتاريخ الفاتورة | `CreatePurchaseInvoice::payTheInitialPayment` (`:40`) | — |

- **`handleOnCreation`** ما يسوي شي إذا الفاتورة مو شراء، أو إذا حالتها مو `pending`.
- **الدفعة الأولى** ([Payoff.php](../../app/Models/Payoff.php)):
  - ما تتعدل ولا تنحذف لوحدها (`:125`، `:146`).
  - تنحذف وترجع بس ويا فاتورتها (`Invoice::booted`، `:72-78`).
  - ما تمنع حذف الفاتورة: `ensureHasNoPayoffs` يستثنيها (`app/Models/Invoice.php:581`).

## 4. الحالات والتأكيد
### 4.1 الحالات (`app/Enums/InvoiceStatus.php`)
| الحالة | العربي | بانتظار | المخزون |
|---|---|---|---|
| `pending` | معلّقة | المستورد | ولا شي |
| `importer_confirmed` | مؤكدة من المستورد | المستلم | قادم |
| `confirmed` | مؤكدة | — | متوفر + كلفة |

- **`InvoiceConfirmation`** (`app/Enums/InvoiceConfirmation.php`): لكل طرف:
  - صلاحيته (`ability`).
  - الحالة اللي قبله (`requiredStatus`) واللي بعده (`nextStatus`).
  - أعمدته (`checkerColumn`، `confirmedAtColumn`).
- **`Invoice::awaitedConfirmation()`** (`app/Models/Invoice.php:92`): يرجّع الطرف اللي بانتظاره، أو `null`.

### 4.2 التأكيد اليدوي (`ConfirmPurchaseInvoice::handle`، `app/Invoices/ConfirmPurchaseInvoice.php:22`)
- **الصلاحية:** `Gate::forUser($checker)->authorize($side->ability(), $invoice)`.
- **الشروط:**
  - فاتورة شراء، ومو محذوفة.
  - طرف المستورد: لازم بيها صور.
  - الحالة = `requiredStatus` مال الطرف. هذا اللي يفرض الترتيب.
  - المبلغ المكتوب = `amount` (فرق أقل من 0.0001).
- **النتيجة:** `forceFill` للمؤكِّد والوكت والحالة الجديدة، ثم `save`. `StockObserver` يعيد الحساب.
- **هسه عملياً:** تأكيد المستلم بس. تأكيد المستورد اليدوي يطلع بس لفاتورة رجعت `pending` بالتراجع.

### 4.3 التراجع (`RevertInvoiceConfirmation::handle`، `app/Invoices/RevertInvoiceConfirmation.php:21`)
- **لمنو:** للسوبر بس (`is_super`)، وغيره `AuthorizationException`.
- **شنو يتراجع:** آخر تأكيد.
  - `confirmed` ← `importer_confirmed`: المتوفر ينقص والقادم يزيد، والكلفة ترجع لآخر batch مؤكَّد قبلها.
  - `importer_confirmed` ← `pending`: القادم ينقص.
- **فاتورة `pending`:** ينرفض (`invoice_has_no_confirmation_to_revert`).

## 5. المخزون
### 5.1 `StockObserver` (`app/Observers/StockObserver.php`)
- **مربوط بـ `#[ObservedBy]` على:** `Invoice`، و`InvoiceItem`، و`Order`، و`OrderItem`.
- **الأحداث:** `saved`، و`deleted`، و`restored`، و`forceDeleted`.
- **المواد اللي يعيد حسابها** (`itemIdsOf`، `:70`):
  - **بند شراء أو بند بيع:** مادته، والمادة اللي انتقل منها (`getOriginal('item_id')`).
  - **فاتورة:** مواد كل batches الفاتورة، حتى المحذوفة.
  - **طلب:** مواد كل بنوده، حتى المحذوفة.
- **شنو يستدعي** (`recalculateFor`، `:54`):
  - **البيع** (`Order`، `OrderItem`): `recalculateAvailableQuantity()`، المتوفر بس.
  - **الشراء** (`Invoice`، `InvoiceItem`): `recalculateStock()`، يعني المتوفر والقادم والكلفة.

### 5.2 المعادلات (`app/Models/Item.php`)
| الرقم | المعادلة | السطر |
|---|---|---|
| المتوفر | مجموع كميات batches الفواتير `confirmed` − مجموع كميات بنود البيع غير المحذوفة، بطلبات غير محذوفة | `availableQuantity` `:169` |
| القادم | مجموع كميات batches الفواتير `importer_confirmed` | `incomingQuantity` `:185` |
| الكلفة | `purchase_price` لآخر batch مؤكَّد، مرتب `invoice_id` ثم `id` تنازلي، ومقرّب لمنزلتين. ماكو = 0 | `costPriceOfTheLastPurchase` `:281` |
| `last_cost_price` | ياخذ `cost_price` القديم، بس إذا الكلفة تغيّرت فعلاً | `recalculateStock` `:206` |
| المتوسط | Σ(كمية × سعر) ÷ Σ كمية، للـ batches المؤكَّدة | `averagePurchasePrice` `:237` |
| الأعلى والأقل | max وmin لـ `purchase_price` المؤكَّد | `:252`، `:262` |
| قيمة المخزن الآن | Σ(`available_quantity × cost_price`) للمواد الفعّالة اللي متوفرها > 0 | `stockValue` `:109` |
| قيمة المخزن بتاريخ | نفس الفكرة من الصفر: batches مؤكَّدة بتاريخ فاتورة ≤ اليوم، ناقص المبيع بتاريخ طلب ≤ اليوم | `stockValueAt` `:126` |

- **batches بتنحسب بـ "المؤكَّد"** (`purchases()`، `:270`): بس اللي كميتها وسعرها أكبر من صفر.
- **الـ batches المحذوفة، والـ batches مال فاتورة محذوفة، ما تنحسب:** `whereHas('invoice')` يستثني الفاتورة المحذوفة (`batchesOfInvoicesWith`، `:296`).
- **المشتريات غير المؤكَّدة بتاريخ** (`Invoice::unconfirmedPurchasesAt`، `app/Models/Invoice.php:378`): مبالغ فواتير الشراء اللي حالتها مو `confirmed`. تستخدمها `app/Metrics/CompanyValue.php` ويا `stockValueAt`.

### 5.3 البيع
- **فحص الكمية** (`OrderItem::ensureTheItemHasEnoughStock`، `app/Models/OrderItem.php:109`):
  - الكمية ما تتجاوز `available_quantity`.
  - البند المحفوظ يحسب كميته القديمة كمتوفرة إله.
- **الكلفة** (`OrderItem::applyCostAndProfit`، `:91`):
  - البند ياخذ `cost_price` المادة لحظة الخلق، أو لحظة تغيير المادة، وبعدها ما تتغير.
  - الربح = (السعر − الكلفة) × الكمية.
- **القادم ما إله علاقة بالبيع أبداً.**

## 6. التعديل والحذف (`app/Policies/InvoicePolicy.php`)
| العملية | الشرط لغير السوبر | السطر |
|---|---|---|
| تعديل فاتورة شراء | صلاحية `invoice.update` + الحالة مو `confirmed` + أقل من 24 ساعة من الخلق | `update` `:19` |
| حذف فاتورة شراء | ممنوع. السوبر بس | `delete` `:33` |
| تأكيد | فاتورة شراء، مو محذوفة، بمرحلة الطرف، وعنده صلاحية الطرف | `canConfirm` `:59` |

- **السوبر يعبر كل شي** بـ `Gate::before` (`app/Providers/AppServiceProvider.php:43`).
- **تعديل البنود** يعيد كتابة الـ batch، والمخزون ينعاد حسابه.
  - تعديل فاتورة `importer_confirmed` يغيّر القادم بس، والحالة تبقى.
  - صفحة التعديل تحفظ البنود قبل الفاتورة، فتعيد حساب المبالغ بـ `afterSave` (`EditPurchaseInvoice.php:41`).
- **الحذف:**
  - فاتورة عليها تسديدات (غير الأولى) ما تنحذف (`Invoice::ensureHasNoPayoffs`، `:581`).
  - حذف فاتورة `confirmed` يطلّع كميتها من المتوفر. إذا انباع منها، المتوفر يصير سالب.
  - للمواد السالبة lens: `app/Filament/Lenses/ItemsWithANegativeQuantityLens.php`، وبيه زر إعادة حساب.
- **الاسترجاع:** يرجّع الفاتورة، والمخزون ينعاد حسابه (`restored`).

## 7. الشاشات (`app/Filament/Resources/PurchaseInvoices/`)
### 7.1 الإنشاء والتعديل (`Schemas/PurchaseInvoiceForm.php`)
- **البنود:** repeater جدول: المنتج 25%، وعدد الباكيتات وسعة الباكيت وعدد الكراتين وحجم الكارتونة (CBM) وكلفة القطعة 15% لكل وحدة. بند واحد على الأقل.
  - **عدد الكراتين وحجم الكارتونة:** إجباريات وأكبر من صفر. تحت الحجم يطلع CBM البند (`cbmHint`).
  - قائمة المادة تعرض بس الأوصاف الخاصة اللي إلها مادة (`item_id` مو فارغ).
  - **كلفة القطعة** (2026-09-22): تنكتب بدل سعر البند، وما إلها عمود.
    - بالحفظ: `price` = عدد الباكيتات × سعة الباكيت × كلفة القطعة، مقرّب لمنزلتين (`withLinePrice`).
    - بالتعديل: كلفة القطعة = `price ÷ quantity`، مقرّبة لمنزلتين (`withUnitCost`).
- **تلميحات البند** (chips، `resources/views/filament/forms/components/line-facts.blade.php`):
  - **تحت المادة** (`itemHint`):
    - المتوفر: أخضر، وأحمر إذا صفر.
    - القادم: أزرق إذا موجود.
    - سعر البيع.
  - **تحت سعة الباكيت** (`quantityHint`): عدد القطع = الباكيتات × السعة.
  - **تحت كلفة القطعة** (`costHint`):
    - السعر الكلي للبند، للكل.
    - الكلفة السابقة: أحمر إذا كلفة البند أغلى منها، وأخضر إذا أرخص. تطلع بس لمن عنده `viewCostPrice` على `Item`.
- **قسم المبالغ:** المبلغ، والمدفوع، والباقي، وCBM الفاتورة. للقراءة بس، ويتحدث ويا الكتابة. الـ CBM يطلع لحد 3 منازل.
- **الدفعة الأولى:**
  - بالإنشاء بس، وما تتجاوز مجموع البنود.
  - تحتها تلميح: دين المورد الحالي، ومبلغ الفاتورة، والباقي بعد الدفعة، ودين المورد بعد الفاتورة (`initialPaymentHint`).
- **الصور:** إجبارية. jpeg/png/webp، و2MB كحد أقصى، على الـ disk `public/invoices`.

### 7.2 صفحة العرض (`Pages/ViewPurchaseInvoice.php`، `Schemas/PurchaseInvoiceInfolist.php`)
- **الأزرار:** تأكيد، وتراجع، وتعديل.
- **التفاصيل:**
  - الاسم، والمورد، والتاريخ، والخالق.
  - الحالة، و"بانتظار".
  - المستورد ووكته، والمستلم ووكته.
- **المبالغ وCBM الفاتورة، والبنود:** البنود بصورة المادة، واسمها رابط لصفحتها، وبيها عدد الكراتين، وحجم الكارتونة (لحد 4 منازل)، وCBM البند.
- **الصور، والملاحظات، وجدول التسديدات:**
  - التسديدات عبر `RelationManagers/PayoffsRelationManager.php`.
  - الدفعة الأولى ما بيها تعديل ولا حذف.
  - بعد كل تغيير، الحدث `payoffs-changed` يعيد تحميل الفاتورة.
- **سجل التعديلات:** `AuditsRelationManager` تحت التسديدات.

### 7.3 الجدول (`Tables/PurchaseInvoicesTable.php`)
- **الأعمدة:** الرقم، والاسم، والمورد، والتاريخ، والمبلغ، والمدفوع، والباقي، والحالة، و"بانتظار"، والخالق (مخفي افتراضياً).
- **الترتيب:** التاريخ تنازلي.
- **الفلاتر:** المورد، والحالة، والمحذوفات.
- **أزرار كل سطر:** تأكيد، وتراجع، وعرض، وتعديل.
- **أزرار الجماعي:** حذف، وحذف نهائي، واسترجاع.

### 7.4 الأزرار (`Actions/`)
- **`ConfirmInvoiceAction`:**
  - يطلع بس للطرف اللي بانتظاره، ولمن عنده صلاحيته.
  - المودال يعرض المبلغ ويطلب يكتبه مرة ثانية.
  - الرفض يطلع كإشعار أحمر.
- **`RevertConfirmationAction`:**
  - للسوبر بس (`superOnly()`).
  - يطلع إذا الحالة `importer_confirmed` أو `confirmed`.

### 7.5 الـ widgets (صفحة القائمة)
| الـ widget | يعدّ | المصدر |
|---|---|---|
| `PendingPurchaseInvoicesMetric` | الفواتير `pending` | `Invoice::purchasesAwaitingCount(Importer)` |
| `ImporterConfirmedPurchaseInvoicesMetric` | الفواتير `importer_confirmed` | `Invoice::purchasesAwaitingCount(Receiver)` |
| `SupplierPurchasesChart` | أكثر الموردين شراءً | `Invoice::purchasesBySupplier()` |

- **صفحة المادة:** بيها widgets للمتوفر، والقادم، وأسعار الشراء (آخر، ومتوسط، وأعلى، وأقل) بـ `app/Filament/Resources/Items/Widgets/`.

## 8. الصلاحيات
- **الاسم يتكوّن من** `PermissionName::for(InvoicePolicy, ability)` (`app/Permissions/PermissionName.php`)، والكود يكتشفه بروحه:

| الصلاحية | لشنو |
|---|---|
| `invoice.view` / `create` / `update` / `delete` / `restore` | الأساسية، من `ModelPolicy` |
| `invoice.confirm_as_importer` | تأكيد المستورد اليدوي، بعد التراجع بس |
| `invoice.confirm_as_receiver` | تأكيد المستلم |
| `item.view_cost_price` | يشوف الكلفة السابقة تحت كلفة القطعة |

## 9. الـ tests (انعادت 2026-09-22: 73 من 82 نجحت، نفس نتيجة 2026-09-20)
| الملف | النتيجة | شنو يغطي |
|---|---|---|
| `tests/Unit/ItemStockTest.php` | ✅ 10/10 | المتوفر والقادم والكلفة و`last_cost_price`، والبيع، والتراجع، ونقل بند لمادة ثانية |
| `tests/Unit/BatchTest.php` | ✅ 4/4 | الـ batch ينكتب ويا البند |
| `tests/Unit/ConfirmPurchaseInvoiceTest.php` | ✅ 8/8 | شروط التأكيد اليدوي |
| `tests/Unit/ImporterConfirmationOnCreationTest.php` | ✅ 6/6 | تأكيد المستورد التلقائي |
| `tests/Unit/InvoicePolicyTest.php` | ✅ 24/24 | التعديل والحذف والتأكيد |
| `tests/Unit/InitialPaymentPayoffTest.php` | ✅ 9/9 | قواعد الدفعة الأولى |
| `tests/Feature/CreatePurchaseInvoiceInitialPaymentTest.php` | ✅ 1/1 | الدفعة الأولى من شاشة الإنشاء |
| `tests/Unit/ItemCostPriceTest.php` | ✅ 3/3 | الكلفة |
| `tests/Unit/InvoiceItemTest.php` | ✅ 8/8 | قواعد البند، وCBM البند |
| `tests/Unit/InvoiceCbmTest.php` | ✅ 3/3 | CBM الفاتورة: المجموع، والتعديل، والحذف |
| `tests/Unit/InvoiceDeletionTest.php` | ❌ 2/3 | `test_deleting_an_invoice_soft_deletes_its_lines_and_batches`: حذف الفاتورة ما يحذف بنودها ودفعاتها. الكود ما يسويها |
| `tests/Unit/ReturnInvoiceItemsTest.php` | ❌ 0/8 | `ReturnInvoiceItems::handle()` ما موجود. الكلاس فاضي |

## 10. فجوات معروفة (وصف بس، ماكو قرار عليها)
1. **نفس الشخص يأكد الطرفين:** `canConfirm` ما يقارن المستلم بالمستورد. الخالق هو المستورد، ويگدر يأكد كمستلم إذا عنده الصلاحية. السوبر دائماً يگدر.
2. **المستلم ما يرفع صور استلام:** يكتب المبلغ بس.
3. **التعديل ما يلغي تأكيد المستورد:** الفاتورة تبقى `importer_confirmed`، والقادم يتغير ويا البنود.
4. **`profit_difference`** على الفاتورة والبند: موجود، ومحد يكتبه.
5. **الإرجاع:** `app/Invoices/ReturnInvoiceItems.php` فاضي، واختباراته موجودة وفاشلة. العمود `original_invoice_id` مو مستخدم.
6. **حذف الفاتورة ما يحذف بنودها ودفعاتها:** اختباره فاشل. المخزون يطلع صحيح، لأن الحساب يستثني الفاتورة المحذوفة.
7. **ترتيب الكلفة بـ `invoice_id`:** مو بوكت تأكيد الاستلام. فاتورة قديمة تنستلم متأخر ما تصير هي الكلفة.
8. **المتوفر ممكن يصير سالب:** إذا انحذفت فاتورة `confirmed` أو انرجعت عن تأكيدها بعد ما انباع منها.
9. **`produced_at` مخفي** من الشاشة.
10. **عدّاد "الفواتير المعلّقة" صار شبه دائماً صفر:** الفاتورة تنولد `importer_confirmed`، فما تصير `pending` إلا بالتراجع. وشرحه بعده يقول "لم تُؤكَّد بعد".
11. **زر الحذف بصفحة التعديل** ينخفي إذا عليها أي تسديد، حتى الدفعة الأولى، بينما الموديل يسمح بحذفها إذا ما عليها غير الأولى (`EditPurchaseInvoice.php:30`). زر الحذف النهائي نفس الشي، ويحسب حتى التسديدات المحذوفة (`:32`).
12. **مؤجّل من قبل:** اختلاف الكمية المستلمة عن كمية المستورد، والعملة وسعر الصرف، ورحلة البضاعة (الصين ← باخرة ← مخزن).

## 11. خريطة الملفات
| الطبقة | الملفات |
|---|---|
| الموديلات | `app/Models/Invoice.php`، `InvoiceItem.php`، `Batch.php`، `Item.php`، `Payoff.php`، `OrderItem.php` |
| المخزون | `app/Observers/StockObserver.php` |
| العمليات | `app/Invoices/ConfirmPurchaseInvoice.php`، `RevertInvoiceConfirmation.php`، `DistributeSupplierPayoff.php`، `ReturnInvoiceItems.php` (فاضي) |
| الـ enums | `app/Enums/InvoiceStatus.php`، `InvoiceConfirmation.php`، `InvoiceType.php` |
| الصلاحيات | `app/Policies/InvoicePolicy.php`، `ModelPolicy.php`، `app/Permissions/PermissionName.php` |
| الشاشة | `app/Filament/Resources/PurchaseInvoices/`: `Pages/` (Create، Edit، View، List)، `Schemas/` (Form، Infolist)، `Tables/`، `Actions/` (Confirm، Revert)، `Widgets/`، `RelationManagers/PayoffsRelationManager.php` |
| المادة | `app/Filament/Resources/Items/Widgets/` (المتوفر، والقادم، وأسعار الشراء)، `app/Filament/Lenses/ItemsWithANegativeQuantityLens.php` |
| القيم | `app/Metrics/CompanyValue.php`، `app/Metrics/Calculations/StockValue.php` |
| الجداول | `database/migrations/2026_09_14_170000_create_invoices_table.php`، `..._170100_create_invoice_items_table.php`، `2026_09_16_190958_create_batches_table.php`، `2026_09_13_230459_create_items_table.php` |
| النصوص | `lang/{ar,en}/filament/resources/{invoice,invoice_item,purchase_invoice,payoff,item}.php`، `lang/{ar,en}/invoices.php` |
| الـ tests | ملفات القسم 9 |
