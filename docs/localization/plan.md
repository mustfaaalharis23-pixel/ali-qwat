# خطة: اللغتين (العربي والإنكليزي)

> **الحالة: قيد التنفيذ.** القاعدة بـ `CLAUDE.md` (القاعدة 3).
> القرارات بالقسم 2 و 3 اتخذها Claude بتفويض من صاحب المشروع، بعد قراءة docs مال Laravel 13 و Filament 5 (2026-09-14).

## 1. اللغة
- الإنكليزي افتراضي واحتياطي (`APP_LOCALE=en` و `APP_FALLBACK_LOCALE=en`)، والعربي ينختار من زر اللغة.
- اللغات المدعومة وأسماءها مكتوبة بمكان واحد: `config('app.locales')`.
- بالعربي الواجهة تنقلب RTL: Filament ياخذ الاتجاه من ترجمته العربية (`filament-panels::layout.direction` = `rtl`).

## 2. تغيير اللغة
- **رابط بقائمة اليوزر** بـ Filament يروح لـ route اسمه `locale.switch`، مثل مثال docs Laravel (`App::setLocale`).
  - الـ route يقبل بس `ar` و `en`، وغيرهن يرجع 404.
  - الـ route ما يحتاج تسجيل دخول، حتى تنغير اللغة من صفحة الدخول هم.
- **الاختيار ينحفظ بالـ session**، و middleware `App\Http\Middleware\SetLocale` يطبقه بكل طلب.
  - مسجل بمجموعة `web` (مثل docs Laravel)، وباللوحة بـ `isPersistent: true` حتى ينطبق على طلبات Livewire هم (مثل docs Filament).
- **بعد التحويل يرجع للصفحة السابقة**، بس إذا جانت من موقعنا. إذا من موقع ثاني يروح للصفحة الرئيسية، حتى الرابط ما ينستغل لتحويل اليوزر لموقع ثاني.
- **اسم كل لغة ينكتب بلغتها:** "العربية" و "English"، حتى يعرفها اللي ما يقرا اللغة الثانية.
- **ليش مو package:**
  - Filament ما يوصي بأي language switcher، كلها community.
  - الأشهر (`bezhansalleh/filament-language-switch`) يحتاج custom theme و `npm run build`.
  - ويقدم لغة المتصفح على `app.locale`، فاللغة الافتراضية ما تبقى ثابتة.

## 3. النصوص
- **نصوصنا:** ملفات PHP بـ short keys، مثل مثال docs Filament (`__('filament/resources/customer.label')`).
  - مثال: `lang/ar/filament/resources/user.php` و `lang/en/filament/resources/user.php`.
  - تنقرا بـ `__()` داخل `getModelLabel()` و `->label()`.
- **رسائل Laravel** (validation وغيرها): من `laravel-lang/common` (بـ `require` مو `require-dev`، حسب docs مالته)، وتنضاف بـ `php artisan lang:add ar`.
  - `@php artisan lang:update` مضاف لـ `post-update-cmd` بـ `composer.json`، حتى الترجمات تتحدث ويه كل `composer update`.
