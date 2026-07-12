# تقرير أداء صفحة Cash Dashboard

**الرابط:** `http://127.0.0.1:8000/en/92/cashvero-dashboard/cash`  
**Route:** `view.customer.invoice.dashboard.cash`  
**Controller:** `CustomerInvoiceDashboardController@viewCashDashboard`  
**View:** `resources/views/admin/dashboard/cash.blade.php` (~1934 سطر)  
**التاريخ:** 2026-06-17

---

## ملخص تنفيذي

البطء الشديد في فتح الصفحة ناتج عن **تراكم مشاكل في الـ Backend والـ Frontend**، وليس عن سبب واحد.

| الطبقة | التأثير التقريبي |
|--------|------------------|
| Backend — استعلامات متداخلة | **عالٍ جداً** (مئات الاستعلامات محتملة) |
| Frontend — AmCharts + سكربتات | **عالٍ** (حتى بدون بيانات overdraft) |
| حجم HTML المُولَّد | **متوسط–عالٍ** |
| Layout عام للتطبيق | **متوسط** |

### بيانات شركة 92 (عينة)

| العنصر | العدد |
|--------|-------|
| العملات | 6 (EGP, EURO, USD, AED, SAR, OMR) |
| البنوك | 6 |
| الفروع | 6 |
| الحسابات النشطة | 19 |
| سجلات `current_account_bank_statements` | 1498 |
| سجلات `cash_in_safe_statements` | 392 |
| Overdrafts | 0 |
| Medium Term Loans | 1 (EGP) |

**الزمن الإجمالي المحتمل:** 10–30+ ثانية في ظروف غير مثالية.

---

## 1. أسباب البطء في الـ Backend

### 1.1 حلقات متداخلة (Cartesian Explosion)

المنطق في `viewCashDashboard` يتبع هذا النمط:

