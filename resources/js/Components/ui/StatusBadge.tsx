import { useTranslation } from 'react-i18next';

import { DocumentStatus, ResultStatus } from '@/types';

const COLORS: Record<string, string> = {
    uploaded: 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200',
    extracting: 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300',
    queued: 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300',
    processing: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
    pending: 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200',
    completed: 'bg-brand-100 text-brand-700 dark:bg-brand-900/40 dark:text-brand-300',
    failed: 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
};

const ANIMATED = new Set(['extracting', 'queued', 'processing']);

export default function StatusBadge({ status }: { status: DocumentStatus | ResultStatus }) {
    const { t } = useTranslation();

    return (
        <span
            className={`inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium ${
                COLORS[status] ?? COLORS.uploaded
            }`}
        >
            {ANIMATED.has(status) && (
                <span className="h-1.5 w-1.5 animate-pulse rounded-full bg-current" />
            )}
            {t(`documents.status.${status}`, status)}
        </span>
    );
}
