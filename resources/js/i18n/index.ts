import i18n from 'i18next';
import LanguageDetector from 'i18next-browser-languagedetector';
import { initReactI18next } from 'react-i18next';

import ar from './locales/ar.json';
import en from './locales/en.json';

export const SUPPORTED_LOCALES = ['en', 'ar'] as const;
export type SupportedLocale = (typeof SUPPORTED_LOCALES)[number];

export const RTL_LOCALES: SupportedLocale[] = ['ar'];

export function isRtl(locale: string): boolean {
    return RTL_LOCALES.includes(locale as SupportedLocale);
}

export function applyDocumentDirection(locale: string): void {
    const html = document.documentElement;
    html.lang = locale;
    html.dir = isRtl(locale) ? 'rtl' : 'ltr';
}

void i18n
    .use(LanguageDetector)
    .use(initReactI18next)
    .init({
        resources: {
            en: { translation: en },
            ar: { translation: ar },
        },
        fallbackLng: 'en',
        supportedLngs: SUPPORTED_LOCALES as unknown as string[],
        interpolation: {
            escapeValue: false,
        },
        detection: {
            order: ['localStorage', 'navigator'],
            caches: ['localStorage'],
            lookupLocalStorage: 'locale',
        },
    });

i18n.on('languageChanged', (locale) => {
    applyDocumentDirection(locale);
});

export default i18n;
