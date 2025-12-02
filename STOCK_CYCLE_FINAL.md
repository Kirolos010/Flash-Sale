# دورة حياة الـ Stock - النسخة النهائية

## 📋 المنطق النهائي

```
total_stock = product.stock (الكمية الكلية الموجودة فعلياً)
available_stock = total_stock - sum(active_holds.quantity) (حساب ديناميكي)
```

---

## 🔄 الدورة الكاملة

### البداية:
```
total_stock = 100        (الكمية الكلية الموجودة فعلياً)
available_stock = 100    (الكمية المتاحة للحجز)
```

---

### Step 1: احمد يحجز 10 (createHold)

**Endpoint:** `POST /api/holds`

**ماذا يحدث:**
1. النظام يتحقق من `available_stock`
2. إذا كان كافي، يتم إنشاء Hold
3. **total_stock لا يتغير**
4. **available_stock يقل** (حساب ديناميكي)

**النتيجة:**
```
total_stock = 100        (زي ما هو - لم يتغير)
available_stock = 90     ⬇️ (خصم مؤقت - حساب ديناميكي)
hold.is_used = false
hold.expires_at = now() + 2 minutes
```

**الكود:**
```php
// app/Services/StockService.php
public function reserveStock(...) {
    // Calculate available_stock
    $reservedQuantity = $product->activeHolds()->sum('quantity');
    $available = $product->stock - $reservedQuantity;
    
    // Check if enough
    if ($available >= $quantity) {
        // Create hold (total_stock doesn't change)
        $hold = $product->holds()->create([...]);
    }
}
```

---

### Step 2: احمد ينشئ Order (createOrder)

**Endpoint:** `POST /api/orders`

**ماذا يحدث:**
1. Hold يتم تحويله إلى Order
2. Hold يصبح `is_used = true`
3. **total_stock لا يتغير**
4. **available_stock يرتفع** (لأن Hold لم يعد نشط)

**النتيجة:**
```
total_stock = 100        (زي ما هو - لم يتغير)
available_stock = 100    ⬆️ (ارتفع لأن Hold أصبح used)
hold.is_used = true
order.status = "pending"
```

**الكود:**
```php
// app/Http/Controllers/OrderController.php
$hold->markAsUsed(); // Hold يصبح used
// available_stock يرتفع تلقائياً (لأن Hold لم يعد نشط)
```

---

### Step 3: احمد يدفع - webhook success

**Endpoint:** `POST /api/payments/webhook`

**ماذا يحدث:**
1. Payment Provider يرسل Webhook بحالة `success`
2. Order يصبح `paid`
3. **total_stock يقل** (خصم نهائي - البضاعة راحت)
4. **available_stock يقل** (لأن total_stock قل)

**النتيجة:**
```
total_stock = 90         ⬇️ (خصم نهائي - البضاعة راحت)
available_stock = 90     ⬇️ (قل لأن total_stock قل)
order.status = "paid"
```

**الكود:**
```php
// app/Models/Order.php
public function markAsPaid(): void
{
    $this->update(['status' => 'paid']);
    
    // Decrement total_stock (final deduction)
    app(StockService::class)->decrementStock($this->product, $this->quantity);
}
```

---

### Scenario بديل: لو احمد ملغيش - expireHold

**ماذا يحدث:**
1. Hold ينتهي (بعد 2+ دقيقة)
2. Background Job يعمل
3. Hold يصبح `is_used = true`
4. **total_stock لا يتغير**
5. **available_stock يرتفع** (لأن Hold لم يعد نشط)

**النتيجة:**
```
total_stock = 100        (زي ما هو - لم يتغير)
available_stock = 100    ⬆️ (رجعت تاني - Hold أصبح used)
hold.is_used = true
```

**الكود:**
```php
// app/Services/HoldExpiryService.php
public function processExpiredHolds() {
    // Mark hold as used
    $hold->markAsUsed();
    
    // Invalidate cache (available_stock will increase automatically)
    // total_stock doesn't change
}
```

---

### Scenario بديل: لو الدفع فشل - webhook failed

**Endpoint:** `POST /api/payments/webhook`

**ماذا يحدث:**
1. Payment Provider يرسل Webhook بحالة `failed`
2. Order يصبح `cancelled`
3. **total_stock لا يتغير** (لأنه لم يتم تقليله بعد)
4. **available_stock يرتفع** (لأن Hold أصبح used عند Order)

**النتيجة:**
```
total_stock = 100        (زي ما هو - لم يتغير)
available_stock = 100    ⬆️ (رجعت لأن الدفع فشل)
order.status = "cancelled"
```

**الكود:**
```php
// app/Models/Order.php
public function cancel(): void
{
    $this->update(['status' => 'cancelled']);
    
    // Invalidate cache (available_stock will increase automatically)
    // total_stock doesn't change because it was never decremented
}
```

---

## 📊 جدول ملخص

| المرحلة | total_stock | available_stock | ملاحظات |
|---------|-------------|-----------------|----------|
| **البداية** | 100 | 100 | - |
| **بعد Hold** | 100 | 90 ⬇️ | Hold نشط (خصم مؤقت) |
| **بعد Order** | 100 | 100 ⬆️ | Hold أصبح used |
| **Payment Success** | 90 ⬇️ | 90 ⬇️ | خصم نهائي (البضاعة راحت) |
| **Hold Expired** | 100 | 100 ⬆️ | Hold أصبح used |
| **Payment Failed** | 100 | 100 ⬆️ | Order cancelled |

---

## 🔍 كيف يتم حساب Available Stock؟

### الصيغة:
```php
available_stock = total_stock - sum(active_holds.quantity)
```

### ما هي Active Holds؟
```php
active_holds = holds where:
    - expires_at > now() (لم تنتهي)
    AND
    - is_used = false (لم يتم استخدامها)
```

### المثال:
```
total_stock: 100

Holds:
- Hold 1: quantity=10, expires_at=12:05, is_used=false → نشط ✓
- Hold 2: quantity=5, expires_at=12:01, is_used=false → منتهي ✗
- Hold 3: quantity=3, expires_at=12:10, is_used=true → مستخدم ✗

active_holds = [Hold 1]
available_stock = 100 - 10 = 90
```

---

## 📝 ملخص القواعد

### ✅ متى total_stock يتغير؟
1. **عند نجاح الدفع** → total_stock يقل (خصم نهائي) ⬇️

### ✅ متى total_stock لا يتغير؟
1. **عند إنشاء Hold** → total_stock لا يتغير
2. **عند إنشاء Order** → total_stock لا يتغير
3. **عند انتهاء Hold** → total_stock لا يتغير
4. **عند فشل الدفع** → total_stock لا يتغير

### ✅ متى available_stock يقل؟
1. **عند إنشاء Hold نشط** → available_stock يقل (حساب ديناميكي) ⬇️
2. **عند نجاح الدفع** → available_stock يقل (لأن total_stock قل) ⬇️

### ✅ متى available_stock يرتفع؟
1. **عند إنشاء Order** → Hold يصبح used، available_stock يرتفع ⬆️
2. **عند انتهاء Hold** → Hold يصبح used، available_stock يرتفع ⬆️
3. **عند فشل الدفع** → Order cancelled، available_stock يرتفع ⬆️

---

## 🎯 سيناريو كامل

### السيناريو 1: بيع ناجح
```
1. البداية
   total_stock: 100, available_stock: 100

2. Hold (10 منتجات)
   total_stock: 100, available_stock: 90 ⬇️

3. Order
   total_stock: 100, available_stock: 100 ⬆️

4. Payment Success
   total_stock: 90 ⬇️, available_stock: 90 ⬇️
   
✅ المنتج تم بيعه (total_stock قل نهائياً)
```

### السيناريو 2: Hold منتهي
```
1. البداية
   total_stock: 100, available_stock: 100

2. Hold (10 منتجات)
   total_stock: 100, available_stock: 90 ⬇️

3. Hold ينتهي (2+ دقيقة)
   total_stock: 100, available_stock: 100 ⬆️
   
✅ الـ Stock متاح مرة أخرى
```

### السيناريو 3: فشل الدفع
```
1. البداية
   total_stock: 100, available_stock: 100

2. Hold (10 منتجات)
   total_stock: 100, available_stock: 90 ⬇️

3. Order
   total_stock: 100, available_stock: 100 ⬆️

4. Payment Failed
   total_stock: 100, available_stock: 100
   
✅ الـ Stock متاح مرة أخرى
```

---

## 🔧 الأكواد المسؤولة

### 1. حساب Available Stock
```php
// app/Models/Product.php
public function getAvailableStockAttribute(): int
{
    $reservedQuantity = $this->activeHolds()->sum('quantity');
    return max(0, $this->stock - $reservedQuantity);
}
```

### 2. إنشاء Hold
```php
// app/Services/StockService.php
public function reserveStock(...) {
    // Calculate available_stock
    $reservedQuantity = $product->activeHolds()->sum('quantity');
    $available = $product->stock - $reservedQuantity;
    
    if ($available >= $quantity) {
        // Create hold (total_stock doesn't change)
        $hold = $product->holds()->create([...]);
    }
}
```

### 3. نجاح الدفع (خصم نهائي)
```php
// app/Models/Order.php
public function markAsPaid(): void
{
    $this->update(['status' => 'paid']);
    // Decrement total_stock (final deduction)
    app(StockService::class)->decrementStock($this->product, $this->quantity);
}
```

### 4. Hold منتهي
```php
// app/Services/HoldExpiryService.php
public function processExpiredHolds() {
    $hold->markAsUsed();
    // Invalidate cache (available_stock will increase automatically)
    // total_stock doesn't change
}
```

---

## ✅ الخلاصة

1. **Hold** → total_stock لا يتغير، available_stock يقل (خصم مؤقت)
2. **Order** → لا شيء يتغير
3. **Payment Success** → total_stock يقل (خصم نهائي)، available_stock يقل
4. **Hold Expired** → total_stock لا يتغير، available_stock يرتفع
5. **Payment Failed** → total_stock لا يتغير، available_stock يرتفع

**المنطق:**
- `total_stock` = الكمية الفعلية في المخزون
- `available_stock` = `total_stock - active_holds` (حساب ديناميكي)
- الخصم النهائي يحدث فقط عند نجاح الدفع

