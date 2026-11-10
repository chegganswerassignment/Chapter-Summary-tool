import { router, usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';

import { applyDocumentDirection, SupportedLocale } from '@/i18n';
import { PageProps } from '@/types';

export default function LanguageSwitcher() {
    const { i18n, t } = useTranslation();
    const page = usePage<PageProps>();
    const isAuthenticated = Boolean(page.props.auth?.user);

    const currentLocale = (i18n.language?.split('-')[0] ?? 'en') as SupportedLocale;
    const nextLocale: SupportedLocale = currentLocale === 'ar' ? 'en' : 'ar';

    const handleSwitch = () => {
        void i18n.changeLanguage(nextLocale);
        applyDocumentDirection(nextLocale);

        if (isAuthenticated) {
            router.post(
                route('locale.update'),
                { locale: nextLocale },
                { preserveScroll: true, preserveState: true },
            );
        } else {
            document.cookie = `locale=${nextLocale};path=/;max-age=${60 * 60 * 24 * 365}`;
        }
    };

    return (
        <button
            type="button"
            onClick={handleSwitch}
            title={t('language.toggle')}
            className="inline-flex items-center justify-center rounded-md px-2 py-1 text-sm font-medium text-gray-500 transition duration-150 ease-in-out hover:bg-gray-100 hover:text-gray-700 focus:outline-none dark:text-gray-400 dark:hover:bg-gray-900 dark:hover:text-gray-200"
        >
            {currentLocale === 'ar' ? 'EN' : 'ع'}
        </button>
    );
}
