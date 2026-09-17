# API التطبيق — المواد (v1)

المواد اللي يبيعها التطبيق. الهيدرات والتوكن مشروحين بـ [login.md](login.md).

---

## عرض المواد

```http
GET /api/v1/items
Authorization: Bearer <التوكن>
```

يحتاج صلاحية `app.checkout`، وبدونها يرجع `403`.

**شنو يظهر:** المواد **الفعالة** بس. المادة المعطّلة أو المحذوفة ما تظهر أبداً.
**الترتيب:** اللي بيها كمية أول، وبعدين اللي خلصت، وكلها بالاسم.

### الباراميترات (اختيارية)

| الباراميتر | الوصف |
|---|---|
| `search` | بحث باسم المادة |
| `per_page` | `1` – `100`، الافتراضي `25` |
| `page` | رقم الصفحة |

### الجواب — `200`

```json
{
  "data": [
    {
      "id": 3,
      "name": "Blue Chair",
      "description": null,
      "image": "http://localhost:8000/storage/items/8bfa92c5.jpg",
      "sale_price": 1500,
      "available_quantity": 12,
      "incoming_quantity": 0,
      "is_out_of_stock": false
    },
    {
      "id": 4,
      "name": "Red Table",
      "description": null,
      "image": "http://localhost:8000/storage/items/bd1491aa.jpg",
      "sale_price": 9000,
      "available_quantity": 0,
      "incoming_quantity": 40,
      "is_out_of_stock": true
    }
  ],
  "links": { "next": null },
  "meta": { "current_page": 1, "last_page": 1, "per_page": 25, "total": 2 }
}
```

### الصورة

`image` يجي **رابط كامل جاهز** — حطّه مباشرة بأي مكوّن يحمّل صورة من الإنترنت (`Image.network` أو ما يقابله)، ما تحتاج تركّب المسار بنفسك.

```
"image": "http://192.168.0.236:8000/storage/items/aviator-sunglasses-iqB4F4.png"
```

- المادة اللي ما إلها صورة ترجّع `image: null` — اعرض صورة بديلة (placeholder).
- **جزء العنوان (`http://...:8000`) يتبدّل حسب السيرفر.** بالتطوير لازم يكون عنوان الجهاز اللي شغّال عليه السيرفر بالشبكة (مو `localhost` — الموبايل ما يوصلها)، وبالنشر يصير الدومين. لا تخزّن العنوان بالتطبيق مكتوب بالإيد؛ خلّيه بإعداد واحد.

### الكميات — الأهم بهذي الشاشة

| الحقل | معناه |
|---|---|
| `available_quantity` | الكمية الموجودة بالمخزن الحين (وصلت وانأكدت من الطرفين، ناقص المبيع) |
| `incoming_quantity` | الكمية **القادمة**: بضاعة انأكدت من المستورد وبعدها ما وصلت |
| `is_out_of_stock` | `true` يعني الكمية المتوفرة صفر |

**شلون تعرضها بالتطبيق:**

- `is_out_of_stock: false` → اعرضها عادي مع كميتها.
- `is_out_of_stock: true` و `incoming_quantity > 0` → علّمها "خلصت" واكتب ويّاها: **قادم 40**.
- `is_out_of_stock: true` و `incoming_quantity = 0` → علّمها "خلصت" بلا أي وعد.

> `sale_price` هو السعر اللي ينباع بيه فعلاً — السيرفر يعتمد سعره هو وقت البيع، مو السعر اللي يدزّه التطبيق.

### الأخطاء

| الحالة | الكود |
|---|---|
| بلا توكن أو توكن باطل | `401` |
| بلا صلاحية `app.checkout` | `403` |

### مثال

```bash
curl "http://localhost:8000/api/v1/items?search=chair" \
  -H "Accept: application/json" \
  -H "Accept-Language: ar" \
  -H "Authorization: Bearer 12|kZ8mQ1w..."
```
