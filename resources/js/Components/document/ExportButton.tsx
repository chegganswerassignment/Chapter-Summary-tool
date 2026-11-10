import { Download } from 'lucide-react';
import { useTranslation } from 'react-i18next';

interface Props {
    href: string;
    label: string;
    enabled: boolean;
}

const BASE =
    'inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-medium shadow-sm transition';

export default function ExportButton({ href, label, enabled }: Props) {
    const { t } = useTranslation();

    if (!enabled) {
        return (
            <span
                aria-disabled="true"
                title={t('document.export.notReady')}
                className={`${BASE} cursor-not-allowed border border-gray-200 text-gray-400 dark:border-gray-700 dark:text-gray-600`}
            >
                <Download className="h-3.5 w-3.5" strokeWidth={2} />
                {label}
            </span>
        );
    }

    // A plain anchor on purpose: the browser handles the download natively (no fetched blob).
    return (
        <a
            href={href}
            className={`${BASE} border border-brand-200 bg-brand-50 text-brand-700 hover:bg-brand-100 dark:border-brand-500/30 dark:bg-brand-900/20 dark:text-brand-300 dark:hover:bg-brand-900/40`}
        >
            <Download className="h-3.5 w-3.5" strokeWidth={2} />
            {label}
        </a>
    );
}
