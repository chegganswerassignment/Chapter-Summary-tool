import { PropsWithChildren } from 'react';
import { useTranslation } from 'react-i18next';

import { ProcessingResultData } from '@/types';

interface Props {
    result: ProcessingResultData | undefined;
}

export default function ResultState({ result, children }: PropsWithChildren<Props>) {
    const { t } = useTranslation();

    if (!result || result.status === 'pending') {
        return (
            <div className="flex items-center gap-3 rounded-xl bg-gray-50 p-6 text-sm text-gray-500 dark:bg-gray-700/40 dark:text-gray-400">
                <Spinner />
                {t('document.pending')}
            </div>
        );
    }

    if (result.status === 'processing') {
        return (
            <div className="flex items-center gap-3 rounded-xl bg-brand-50/60 p-6 text-sm text-brand-700 dark:bg-brand-900/20 dark:text-brand-300">
                <Spinner />
                {t('document.processing')}
            </div>
        );
    }

    if (result.status === 'failed') {
        return (
            <div className="rounded-xl bg-red-50 p-6 text-sm text-red-700 dark:bg-red-900/30 dark:text-red-300">
                <p className="font-medium">{t('document.failedResult')}</p>
                {result.error_message && <p className="mt-1 text-xs">{result.error_message}</p>}
            </div>
        );
    }

    return <>{children}</>;
}

function Spinner() {
    return (
        <svg className="h-5 w-5 animate-spin text-current" fill="none" viewBox="0 0 24 24">
            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
            <path
                className="opacity-75"
                fill="currentColor"
                d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"
            />
        </svg>
    );
}
