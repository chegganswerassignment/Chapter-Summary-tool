import { useTranslation } from 'react-i18next';

export default function HeroMockup() {
    const { t } = useTranslation();

    const tabs = [
        { key: 'summary', active: true },
        { key: 'translation', active: false },
        { key: 'pronunciation', active: false },
        { key: 'quiz', active: false },
    ] as const;

    return (
        <div className="w-full max-w-md rounded-2xl border border-gray-200 bg-white shadow-[0_0_60px_-15px_rgba(16,185,129,0.5)] dark:border-gray-700 dark:bg-gray-800 dark:shadow-[0_0_80px_-10px_rgba(16,185,129,0.35)]">
            <div className="flex items-center gap-1.5 rounded-t-2xl border-b border-gray-100 px-4 py-3 dark:border-gray-700">
                <span className="h-2.5 w-2.5 rounded-full bg-red-400" />
                <span className="h-2.5 w-2.5 rounded-full bg-amber-400" />
                <span className="h-2.5 w-2.5 rounded-full bg-brand-400" />
                <span className="mx-auto rounded-full bg-gray-100 px-3 py-1 text-[11px] text-gray-400 dark:bg-gray-700 dark:text-gray-400">
                    chaptersummarytool.com
                </span>
            </div>

            <div className="flex items-center gap-2 border-b border-gray-100 px-4 py-3 dark:border-gray-700">
                <span className="flex h-6 w-6 items-center justify-center rounded-md bg-brand-600 text-xs font-bold text-white">
                    C
                </span>
                <span className="text-sm font-semibold text-gray-800 dark:text-gray-100">
                    {t('app.name')}
                </span>
            </div>

            <div className="flex gap-4 border-b border-gray-100 px-4 dark:border-gray-700">
                {tabs.map((tab) => (
                    <span
                        key={tab.key}
                        className={`border-b-2 py-2.5 text-xs font-medium ${
                            tab.active
                                ? 'border-brand-500 text-brand-600 dark:text-brand-400'
                                : 'border-transparent text-gray-400'
                        }`}
                    >
                        {t(`document.tabs.${tab.key}`)}
                    </span>
                ))}
            </div>

            <div className="space-y-3 p-4">
                <div className="flex items-center gap-2">
                    <span className="rounded-full bg-brand-50 px-2.5 py-1 text-[11px] font-medium text-brand-700 dark:bg-brand-900/30 dark:text-brand-300">
                        {t('landing.hero.mockup.chapterLabel')}
                    </span>
                    <span className="text-[11px] text-gray-400">{t('landing.hero.mockup.reduction')}</span>
                </div>

                <div className="space-y-1.5">
                    <div className="h-2 w-full rounded-full bg-gray-100 dark:bg-gray-700" />
                    <div className="h-2 w-5/6 rounded-full bg-gray-100 dark:bg-gray-700" />
                    <div className="h-2 w-4/6 rounded-full bg-gray-100 dark:bg-gray-700" />
                    <div className="h-2 w-full rounded-full bg-gray-100 dark:bg-gray-700" />
                    <div className="h-2 w-3/6 rounded-full bg-gray-100 dark:bg-gray-700" />
                </div>

                <div className="rounded-xl border-2 border-dashed border-gray-200 p-6 text-center dark:border-gray-600">
                    <div className="mx-auto flex h-10 w-10 items-center justify-center rounded-full bg-brand-50 text-brand-600 dark:bg-brand-900/30 dark:text-brand-400">
                        <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path
                                strokeLinecap="round"
                                strokeLinejoin="round"
                                strokeWidth="2"
                                d="M12 16.5V9.75m0 0l-3 3m3-3l3 3M6.75 19.5a4.5 4.5 0 01-1.41-8.775 5.25 5.25 0 0110.233-2.33 3 3 0 013.758 3.848A3.752 3.752 0 0118 19.5H6.75z"
                            />
                        </svg>
                    </div>
                    <p className="mt-2 text-xs font-semibold text-gray-700 dark:text-gray-200">
                        {t('landing.hero.mockup.dropTitle')}
                    </p>
                    <p className="text-[11px] text-gray-400">{t('landing.hero.mockup.dropHint')}</p>
                    <span className="mt-3 inline-block rounded-lg bg-brand-600 px-4 py-1.5 text-[11px] font-semibold text-white">
                        {t('landing.hero.mockup.upload')}
                    </span>
                </div>
            </div>
        </div>
    );
}
