import { Bot } from 'lucide-react';
import { useTranslation } from 'react-i18next';

export default function AiBotBadge() {
    const { t } = useTranslation();

    return (
        <div className="animate-float hidden select-none sm:block">
            <div className="relative flex items-center gap-2 rounded-2xl border border-brand-200/60 bg-white/90 px-3 py-2 shadow-[0_0_25px_-5px_rgba(16,185,129,0.5)] backdrop-blur dark:border-brand-500/30 dark:bg-gray-800/90">
                <span className="relative flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-brand-400 to-brand-600 text-white shadow-[0_0_15px_rgba(16,185,129,0.6)]">
                    <Bot className="h-[18px] w-[18px]" strokeWidth={2} />
                    <span className="absolute -end-0.5 -top-0.5 h-2.5 w-2.5 rounded-full border-2 border-white bg-emerald-400 dark:border-gray-800" />
                </span>
                <span className="max-w-[11rem] text-xs font-medium leading-snug text-gray-700 dark:text-gray-200">
                    {t('landing.hero.botGreeting')}
                </span>
            </div>
        </div>
    );
}
