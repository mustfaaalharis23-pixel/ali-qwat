# مشاركة فاتورة الأوردر (PDF) — دليل التنفيذ

كل اللي تحتاجه حتى يصير بشاشة تفاصيل الأوردر زر **"مشاركة الفاتورة"** يدزّ الفاتورة للواتساب. الفاتورة تنصنع بالسيرفر — انت تنزّلها وتشاركها بس.

---

## 1. الـ endpoint

```http
GET {BASE_URL}/api/v1/orders/{id}/invoice
Authorization: Bearer <التوكن>
Accept-Language: ar
```

| | |
|---|---|
| الجواب الناجح | **ملف PDF خام** — `Content-Type: application/pdf` |
| الحجم | 100 – 150 كيلوبايت عادةً |
| اللغة | حسب `Accept-Language`: `ar` فاتورة عربية RTL، `en` إنكليزية |
| الصلاحية | `app.orders` (نفس صلاحية شاشة الطلبات) |

> **لا تفكّ الجواب كـ JSON.** هو بايتات ملف. بس الأخطاء ترجع JSON — تحت شلون تفرّقهن.

بالفاتورة: رقم الأوردر وتاريخه، البائع، الزبون وهاتفه، البنود بأسعارها، المجموع والخصم والصافي، ومكان توقيع.
ما بيها: الكلفة والربح — أبداً.

---

## 2. الحزم المطلوبة

```yaml
dependencies:
  dio: ^5.0.0            # أو http، أي واحد تستخدمه أصلاً
  path_provider: ^2.1.0  # مكان الملفات المؤقتة
  share_plus: ^10.0.0    # شيت المشاركة مال النظام
```

---

## 3. الكود

```dart
import 'dart:io';
import 'package:dio/dio.dart';
import 'package:path_provider/path_provider.dart';
import 'package:share_plus/share_plus.dart';

class InvoiceService {
  final Dio _dio;
  InvoiceService(this._dio);

  /// ينزّل فاتورة الأوردر ويشاركها. يرجّع رسالة خطأ لو فشل، و null لو نجح.
  Future<String?> shareInvoice({
    required int orderId,
    required String token,
    String locale = 'ar',
  }) async {
    try {
      final file = await _invoiceFile(orderId, token, locale);

      await Share.shareXFiles(
        [XFile(file.path, mimeType: 'application/pdf')],
        subject: 'فاتورة #$orderId',
      );

      return null;
    } on DioException catch (e) {
      return _errorMessage(e);
    } catch (_) {
      return 'تعذّر تحضير الفاتورة. حاول مرة ثانية.';
    }
  }

  /// الملف من الكاش إذا منزّل من قبل، وإلا ينزّله ويحفظه.
  Future<File> _invoiceFile(int orderId, String token, String locale) async {
    final dir = await getTemporaryDirectory();
    final file = File('${dir.path}/invoice-$orderId-$locale.pdf');

    if (await file.exists() && await file.length() > 0) {
      return file;
    }

    final response = await _dio.get<List<int>>(
      '/api/v1/orders/$orderId/invoice',
      options: Options(
        responseType: ResponseType.bytes,
        headers: {
          'Authorization': 'Bearer $token',
          'Accept-Language': locale,
        },
      ),
    );

    await file.writeAsBytes(response.data!);
    return file;
  }

  String _errorMessage(DioException e) {
    switch (e.response?.statusCode) {
      case 401:
        return 'انتهت الجلسة. سجّل دخول من جديد.';   // وامسح التوكن ورجّعه لشاشة الدخول
      case 403:
        return 'ما عندك صلاحية لهذي الفاتورة.';
      case 404:
        return 'الأوردر مو موجود.';
      default:
        return 'ماكو اتصال. تأكد من الإنترنت وحاول مرة ثانية.';
    }
  }
}
```

> **share_plus:** الإصدارات الأحدث بدّلت الاسم إلى
> `SharePlus.instance.share(ShareParams(files: [XFile(path)]))`.
> شوف README مال الإصدار اللي عندك واستعمل الشكل المطابق — الفكرة وحدة.

### الاستدعاء من الشاشة

```dart
final error = await invoiceService.shareInvoice(
  orderId: order.id,
  token: auth.token,
  locale: Get.locale?.languageCode ?? 'ar',
);

if (error != null) {
  Get.snackbar('الفاتورة', error);
}
```

---

## 4. نقاط لازم تنتبه إلها

**أ) فرّق بين الملف والخطأ.** الجواب الناجح PDF، والخطأ JSON. إذا تستخدم كود يفكّ JSON تلقائياً راح ينكسر على الملف. بـ dio خلّي `responseType: ResponseType.bytes` ودع الأخطاء تطلع كـ `DioException` مثل ما بالكود فوق.

**ب) الكاش.** خزّن الملف بالمجلد المؤقت باسم `invoice-{id}-{locale}.pdf`. المشاركة الثانية تصير بلا نت. إذا انحذف الأوردر أو تغيّر، امسح الملف وخلّيه ينزّل من جديد.

**ج) اللغة.** لا تدزّ `Accept-Language` ثابت — دزّ لغة التطبيق الحالية، حتى إذا بدّل البائع اللغة تطلع الفاتورة بنفسها. ولهذا يجي اسم اللغة بالملف المخزّن.

**د) الواتساب.** ماكو طريقة تدزّ **ملف** مباشرة لرقم واتساب من الكود؛ رابط `wa.me` يدزّ **نص فقط**. الطريق الوحيد للمرفق هو شيت المشاركة (`share_plus`)، والبائع يختار واتساب والمحادثة. إذا تريد تسهّلها: خلّي زر ثاني "إرسال نص" يفتح `wa.me/964xxxxxxxxx?text=...` برقم الزبون اللي يجي من `GET /api/v1/customers`.

**هـ) `401`.** يعني التوكن مات (سحبه المسؤول أو انسوى خروج) — امسح التوكن المخزون ووديه لشاشة الدخول، مثل أي `401` ثاني بالتطبيق.

**و) لا تبني الفاتورة بالتطبيق.** شكلها من السيرفر: أي تعديل عليها (شعار، خط، ترتيب، جملة بالأسفل) يوصلك بلا إصدار جديد. لو رسمتها بالتطبيق، كل تعديل يصير نسخة جديدة ومتجر وانتظار.

---

## 5. فحص قبل ما تسلّم

- [ ] الزر ينزّل الملف ويفتح شيت المشاركة، والواتساب يظهر بالقائمة.
- [ ] المرفق يوصل ويفتح، والعربي بيه سليم من اليمين لليسار.
- [ ] المستلم يكدر يطبع الملف.
- [ ] المشاركة الثانية لنفس الأوردر تشتغل **بلا نت** (من الكاش).
- [ ] بدّل لغة التطبيق للإنكليزي → الفاتورة تطلع إنكليزية.
- [ ] اقطع النت وجرّب أوردر جديد → تطلع رسالة واضحة، والتطبيق ما يطيح.
- [ ] جرّب بحساب ما عنده صلاحية `app.orders` → رسالة صلاحية، مو شاشة بيضاء.

---

الباقي من الـ API: [login.md](login.md) · [items.md](items.md) · [customers.md](customers.md) · [orders.md](orders.md) · [changelog.md](changelog.md)
