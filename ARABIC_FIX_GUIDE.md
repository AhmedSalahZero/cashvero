# دليل حل مشكلة "Duplicate entry"

## 🔴 المشكلة

عند محاولة إنشاء Complex أو Building، تظهر رسالة خطأ:
```
Duplicate entry 'XXXX-31' for key 'properties.properties_code_company_unique'
```

## 💡 السبب

الكود الذي تحاول استخدامه موجود بالفعل في قاعدة البيانات للشركة 31 من محاولة سابقة.

## ✅ الحلول

### الحل الأول: حذف البيانات القديمة (موصى به)

**الطريقة 1: باستخدام phpMyAdmin أو MySQL Workbench**

1. افتح قاعدة البيانات `property_management`
2. اذهب إلى SQL tab
3. نفذ هذا الأمر لعرض جميع الـ Properties:

```sql
SELECT id, name, code, property_type, parent_property_id, created_at 
FROM properties 
WHERE company_id = 31 
ORDER BY created_at DESC;
```

4. لحذف property معين بالكود:

```sql
-- مثال: حذف property بكود 'a212'
DELETE FROM properties 
WHERE code = 'a212' AND company_id = 31;
```

5. أو لحذف كل الـ properties التي تم إنشاؤها اليوم (للاختبار):

```sql
DELETE FROM properties 
WHERE company_id = 31 
AND DATE(created_at) = CURDATE();
```

**الطريقة 2: باستخدام Terminal**

```bash
mysql -u your_username -p
```

ثم:

```sql
USE property_management;

-- اعرض الـ properties
SELECT id, name, code, property_type FROM properties WHERE company_id = 31;

-- احذف الـ property بالكود المكرر
DELETE FROM properties WHERE code = 'a212' AND company_id = 31;

-- تحقق من النتيجة
SELECT id, name, code, property_type FROM properties WHERE company_id = 31;

exit;
```

### الحل الثاني: استخدم أكواد مختلفة

عند إنشاء Complex جديد، استخدم أكواد مختلفة تماماً:

**✅ مثال صحيح:**
- Complex Code: `"C1"` أو `"COMPLEX-001"`
- Unit 1 Code: `"C1-U1"` أو `"C1-101"`
- Unit 2 Code: `"C1-U2"` أو `"C1-102"`
- Unit 3 Code: `"C1-U3"` أو `"C1-103"`

**❌ مثال خاطئ:**
- Complex Code: `"a212"`
- Unit 1 Code: `"a212"` ← نفس كود الـ Parent (خطأ!)
- Unit 2 Code: `"a212"` ← مكرر (خطأ!)

### الحل الثالث: التحديثات الجديدة

تم إضافة validation في الـ Frontend:
- ✅ يمنع استخدام نفس كود الـ Parent للـ Units
- ✅ يمنع تكرار أكواد الـ Units في نفس النموذج
- ✅ يعرض رسائل خطأ واضحة قبل الحفظ

## 🧪 اختبار الحل

بعد تطبيق أي من الحلول:

1. **إنشاء Complex:**
   - الكود: `TEST-001`
   
2. **إضافة Units:**
   - Unit 1: `TEST-001-U1`
   - Unit 2: `TEST-001-U2`
   
3. **حفظ** ← يجب أن يعمل بنجاح ✅

## 📋 نصائح

1. **استخدم أكواد فريدة دائماً**
   - استخدم نظام ترقيم: C1, C2, C3
   - أو استخدم تواريخ: C-2026-01
   
2. **تحقق من الأكواد قبل الحفظ**
   - تأكد أن كود الـ Unit مختلف عن كود الـ Parent
   - تأكد أن أكواد الـ Units غير مكررة

3. **احذف البيانات التجريبية**
   - بعد الانتهاء من الاختبار، احذف البيانات التجريبية
   - احتفظ فقط بالبيانات الحقيقية

## 🆘 إذا استمرت المشكلة

تواصل معي وأرسل:
1. رسالة الخطأ كاملة
2. الأكواد التي تحاول استخدامها
3. نتيجة هذا الأمر:

```sql
SELECT id, name, code, property_type, parent_property_id 
FROM properties 
WHERE company_id = 31 
ORDER BY created_at DESC 
LIMIT 10;
```
