import { createI18n } from 'vue-i18n';
import ar from '../lang/ar.json';

/**
 * Same dictionary Laravel `__()` reads (English phrase → Arabic).
 * English locale keeps an empty map so `$t('Save')` falls back to the
 * key itself — matching Laravel JSON translations.
 */
export const i18n = createI18n({
    legacy: false,
    locale: 'en',
    fallbackLocale: 'en',
    missingWarn: false,
    fallbackWarn: false,
    warnHtmlMessage: false,
    missing: (_locale, key) => key,
    messages: {
        en: {},
        ar,
    },
});

export function applyLocale(locale) {
    const next = locale === 'ar' ? 'ar' : 'en';
    const dir = next === 'ar' ? 'rtl' : 'ltr';
    i18n.global.locale.value = next;
    if (typeof document === 'undefined') {
        return;
    }
    document.documentElement.lang = next === 'ar' ? 'ar' : 'en';
    document.documentElement.dir = dir;
}

export function formatNumber(value, options = {}) {
    const digits = options.fractionDigits ?? 2;
    return Number(value || 0).toLocaleString('en-EG', {
        minimumFractionDigits: digits,
        maximumFractionDigits: digits,
    });
}
