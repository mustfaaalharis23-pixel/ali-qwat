# بحث: صلاحيات اليوزرز (Permissions)

> **الحالة: مثبتة (2026-09-13).** أي تغيير بالاتفاقات ينكتب هنا أول، والخطة بـ `docs/users/plan.md`.
> المعلومات الموجودة فعلاً واللي نحتاجها للاتفاقات. خطة العمل بصفحة منفصلة.
> تاريخ البحث: 2026-09-13. المسارات نسبةً لجذر مشروع waqt، والأرقام بعد `:` أرقام أسطر.

## 1. الاتفاقات الأساسية
1. **كل موديل بيه 5 صلاحيات أساسية:**
   - **عرض:** صلاحية وحدة تشمل القائمة والسجل الواحد.
   - **إضافة**
   - **تعديل**
   - **حذف:** soft delete.
   - **استرجاع:** restore.
2. **الحذف النهائي (forceDelete) مو صلاحية تنعطى لأحد.** بس يوزر `is_super` يكدر يسويه.
   - **استثناء:** اليوزرز ما ينحذفون نهائياً أبداً، حتى من `is_super` (plan 1.3).
3. **يوزر `is_super`** يتجاوز كل الفحوصات ويسوي أي شي.
4. **ماكو أدوار (roles).** الصلاحيات تنعطى مباشرة لكل يوزر، والنظام يبقى بسيط.
5. **الكود يلكي الـ Policies بروحه:** يربطها بموديلاتها ويعرض صلاحياتها، بدون قائمة نكتبها بإيدنا.
6. **صلاحية خاصة لموديل معين** (مثل اعتماد فاتورة) تنضاف يدوياً، والكود يبقى نظيف.
7. **لاحقاً عند الحاجة:** مجموعة (collection) صلاحيات مو مربوطة بموديل، مثل مجموعة "التقارير" بيها "عرض الأرباح" و "تصدير Excel".

## 2. الوضع الحالي بالمشروع
- Laravel 13.31.0، Filament 5.8.1، PHP `^8.3` (الجهاز 8.4.4)، PostgreSQL 17.4.
- **`app/Models/User.php`:** بدون SoftDeletes، وما يطبق `FilamentUser`، يعني على السيرفر أي يوزر ياخذ 403 (`vendor/filament/filament/src/Http/Middleware/Authenticate.php:35-40`).
- **جدول `users`:** ما بيه عمود `is_super`.
- **ماكو:** مجلد `app/Policies`، ولا استخدام لـ `Gate`، ولا package صلاحيات منصب.

## 3. شلون Filament يفحص الصلاحيات
### 3.1 الـ abilities اللي يناديها من الـ Policy
| الاتفاق | Ability | وين |
|---|---|---|
| عرض | `viewAny` | يخفي الـ resource ويمنع صفحاته (`vendor/filament/filament/src/Resources/Pages/Concerns/CanAuthorizeResourceAccess.php:19`) |
| عرض | `view` | صفحة وزر العرض (`ViewRecord.php:80`) |
| إضافة | `create` | `CreateRecord.php:75` |
| تعديل | `update` | `EditRecord.php:100` |
| حذف | `delete` / `deleteAny` | `DeleteAction` / `DeleteBulkAction` |
| استرجاع | `restore` / `restoreAny` | `RestoreAction` / `RestoreBulkAction` |
| حذف نهائي | `forceDelete` / `forceDeleteAny` | `ForceDeleteAction` / `ForceDeleteBulkAction` |

الربط بين الـ action والـ ability: `vendor/filament/filament/src/Resources/Pages/Page.php:309-324`.

### 3.2 نقاط مهمة
1. **ماكو Policy أو method يعني مسموح** (`vendor/filament/filament/src/helpers.php:87-92`). `->strictAuthorization()` على الـ panel يخليه يرمي exception بدل ما يسمح (`Panel/Concerns/HasAuth.php:691`).
2. **Filament يفحص الـ method بـ `method_exists`** (`helpers.php:62`): الـ method الموروثة من class أب تمشي، والسحرية عبر `__call` ما تمشي.
3. **`Gate::before` يوصل لكل فحوصات Filament** (`helpers.php:62-63` و `:81-85`): إذا رجع `true` ينفتح كل شي.
4. **الـ bulk actions تفحص `*Any` بس** (`Resources/Resource/Concerns/HasAuthorization.php:14-22`). الفحص لكل سجل يحتاج `->authorizeIndividualRecords()`.
5. **الـ action الخاص:**
   - `->authorize('approve')` ينادي `Policy::approve($user, $record)` (`vendor/filament/actions/src/Concerns/CanBeAuthorized.php:34`).
   - الـ action بدون `authorize()` مسموح للكل (`:16-19`).

## 4. شلون Laravel يفحص
الملف: `vendor/laravel/framework/src/Illuminate/Auth/Access/Gate.php`
- **اكتشاف تلقائي:** `App\Models\Invoice` ينربط بـ `App\Policies\InvoicePolicy` بدون تسجيل (`:724-744`)، أو بـ `#[UsePolicy(...)]` على الموديل (`:667`).
- **`Gate::before`:** `null` يعني كمّل للـ Policy، و `true` أو `false` قرار نهائي (`:441-442`، `:558-569`).
- **Method غير موجودة بالـ Policy تنرفض** (`:411-413`)، عكس Filament.
- **class أو سجل:** `can('create', Invoice::class)` ينادي `create(User $user)` بدون موديل، و `can('update', $invoice)` ينادي `update($user, $invoice)` (`:842-844`).
- **`restore()` و `forceDelete()`** على الموديل يجن من `SoftDeletes` (`vendor/laravel/framework/src/Illuminate/Database/Eloquent/SoftDeletes.php`).

## 5. شلون الكود يلكي الـ Policies وصلاحياتها
يخص الاتفاق 5 و 7.

- **`Gate::policies()` يرجع بس الـ Policies المسجلة يدوياً** بـ `Gate::policy()` (`Gate.php:914-917`، `:286`).
  - المكتشفة تلقائياً ما تظهر بيه، لأن `getPolicyFor()` يلكيها وقت الطلب وما يخزنها (`:653-690`).
  - فحتى نعرض كلها لازم نقرا ملفات `app/Policies`، أو نطلب `Gate::getPolicyFor($model)` لكل موديل.
- **Filament يعطي الـ resources وموديلاتها:**
  - `Filament::getResources()` يرجع أسماء classes الـ resources مال الـ panel الحالي (`vendor/filament/filament/src/FilamentManager.php:430-433`).
  - `Resource::getModel()` يرجع الموديل مال كل resource (`Resources/Resource.php:111-116`).
- **methods الـ Policy:** `ReflectionClass` مال PHP يطلع الـ public methods، و `getDeclaringClass()` يفرق الموروثة عن المكتوبة بنفس الـ Policy.
- **صلاحيات مو مربوطة بموديل:** `Gate::define()` يعرّف ability بدون موديل (`Gate.php:197`)، و `Gate::abilities()` يرجعها كلها (`:904-907`).

## 6. spatie/laravel-permission
- **النسخة:** 8.3.0 (2026-07-03)، يدعم Laravel 13 و PHP `^8.3`.
- **صلاحيات مباشرة لليوزر بدون أدوار مدعومة:** `givePermissionTo` و `syncPermissions` و `hasDirectPermission`.
- **الجداول:** الـ migration تنشئ `permissions` و `model_has_permissions`، وتنشئ هم جداول الأدوار (`roles` و `model_has_roles` و `role_has_permissions`) حتى لو ما نستخدمها.
- **الربط ويه Laravel:** يسجل `Gate::before` يرجع `true` إذا اليوزر عنده صلاحية بنفس اسم الـ ability، و `null` بغيرها.
- **Cache:** 24 ساعة، وينمسح تلقائياً ويه methods الـ package.
- المصدر: https://github.com/spatie/laravel-permission
