# API التطبيق — الزباين (v1)

قائمة الزباين. تنستخدم بشاشة اختيار الزبون وقت البيع، وبأي شاشة ثانية تحتاج زبون. الهيدرات والتوكن مشروحين بـ [login.md](login.md).

> **ديون الزباين ما تنعرض بالتطبيق أبداً** — لا بهذا الـ endpoint ولا بغيره. الديون تنشاف بلوحة التحكم بس.

---

## عرض الزباين

```http
GET /api/v1/customers
Authorization: Bearer <التوكن>
```

يحتاج صلاحية `app.orders` **أو** `app.checkout` (الشاشتين تحتاجان الزبون). مرتبين بالاسم، والزبون المحذوف ما يظهر.

### الباراميترات (اختيارية)

| الباراميتر | الوصف |
|---|---|
| `search` | بحث بالاسم أو برقم الهاتف |
| `per_page` | `1` – `100`، الافتراضي `25` |
| `page` | رقم الصفحة |

### الجواب — `200`

```json
{
  "data": [
    {
      "id": 1,
      "name": "علي حسن",
      "phone": "07701234567",
      "email": null,
      "payment_type": { "value": "postpaid", "label": "آجل" }
    }
  ],
  "links": { "next": null },
  "meta": { "current_page": 1, "last_page": 1, "per_page": 25, "total": 1 }
}
```

| الحقل | معناه |
|---|---|
| `payment_type.value` | `cash` أو `postpaid` — طريقة الدفع المعتادة للزبون. استعملها كقيمة افتراضية بشاشة البيع، بس البائع يكدر يغيّرها |
| `payment_type.label` | التسمية المترجمة حسب `Accept-Language` — اعرضها للمستخدم |

> `id` هو اللي يندز كـ `customer_id` وقت البيع — شوف [orders.md](orders.md).

### الأخطاء

| الحالة | الكود |
|---|---|
| بلا توكن أو توكن باطل | `401` |
| بلا أي وحدة من الصلاحيتين | `403` |
| `per_page` أكبر من 100 | `422` |

### مثال

```bash
curl "http://localhost:8000/api/v1/customers?search=علي" \
  -H "Accept: application/json" \
  -H "Accept-Language: ar" \
  -H "Authorization: Bearer 12|kZ8mQ1w..."
```
