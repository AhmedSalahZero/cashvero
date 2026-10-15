/**
 * تاريخ النهاردة بصيغة YYYY-MM-DD بتوقيت جهاز المستخدم.
 *
 * بيتستخدم كـ :max على حقول تواريخ حركة الفلوس (قبض - صرف - مصروفات
 * نقدية - شراء/بيع عملة - تحويلات داخلية ... إلخ) عشان المستخدم
 * ما يقدرش يسجّل استلام أو دفع بتاريخ بعد النهاردة.
 *
 * ما بنستخدمش new Date().toISOString().slice(0,10) لأنها بترجّع تاريخ
 * UTC، والسيرفر شغال على Africa/Cairo — فبعد منتصف الليل بتوقيت مصر
 * كانت هترجّع تاريخ إمبارح وتمنع المستخدم من اختيار النهاردة.
 *
 * دي حماية واجهة بس — التحقق الحقيقي على السيرفر في الـ Form Requests.
 */
export function todayDate() {
    const now = new Date();
    const pad = (value) => String(value).padStart(2, '0');

    return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`;
}

/**
 * لو التاريخ أكبر من النهاردة رجّعه لـ today (الـ HTML max
 * بيمنع الاختيار من الـ picker بس مش الكتابة اليدوية).
 */
export function clampDateToToday(date) {
    if (!date) {
        return date;
    }

    const today = todayDate();

    return date > today ? today : date;
}
