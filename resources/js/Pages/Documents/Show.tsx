import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';

import ExportButton from '@/Components/document/ExportButton';
import PronunciationTab from '@/Components/document/PronunciationTab';
import QuizTab from '@/Components/document/QuizTab';
import SummaryTab from '@/Components/document/SummaryTab';
import TranslationTab from '@/Components/document/TranslationTab';
import Card from '@/Components/ui/Card';
import StatusBadge from '@/Components/ui/StatusBadge';
import { useDocumentChannel } from '@/hooks/useDocumentChannel';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { DocumentDetail, PageProps, ResultType } from '@/types';
import { Head, Link, router } from '@inertiajs/react';

type TabKey = ResultType | 'pronunciation';

const TABS: TabKey[] = ['summary', 'translation', 'quiz', 'pronunciation'];

const TAB_ICONS: Record<TabKey, JSX.Element> = {
    summary: (
        <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.75" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
        </svg>
    ),
    translation: (
        <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.75" d="M10.5 21l5.25-11.25L21 21m-9-3h7.5M3 5.621a48.474 48.474 0 016-.371m0 0c1.12 0 2.233.038 3.334.114M9 5.25V3m3.334 2.364C11.176 10.658 7.69 15.08 3 17.502" />
        </svg>
    ),
    quiz: (
        <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.75" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 11-18 0 9 9 0 0118 0zM12 17.25h.007v.008H12v-.008z" />
        </svg>
    ),
    pronunciation: (
        <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.75" d="M19.114 5.636a9 9 0 010 12.728M16.463 8.288a5.25 5.25 0 010 7.424M6.75 8.25l4.72-4.72a.75.75 0 011.28.53v15.88a.75.75 0 01-1.28.53l-4.72-4.72H4.51c-.98 0-1.76-.79-1.76-1.76v-4c0-.97.8-1.75 1.76-1.75h2.24z" />
        </svg>
    ),
};

export default function Show({ document }: PageProps<{ document: DocumentDetail }>) {
    const { t } = useTranslation();
    const [doc, setDoc] = useState(document);
    const [activeTab, setActiveTab] = useState<TabKey>('summary');

    useEffect(() => setDoc(document), [document]);

    useDocumentChannel(document.id, {
        onStatusUpdated: (payload) => {
            setDoc((prev) => ({
                ...prev,
                status: payload.status as DocumentDetail['status'],
                failure_reason: payload.failure_reason,
            }));
        },
        onResultReady: () => {
            // The event carries no content; re-fetch just the document prop (results + content).
            router.reload({ only: ['document'] });
        },
    });

    const resultFor = (type: ResultType) => doc.results.find((r) => r.result_type === type);
    const summaryResult = resultFor('summary');
    const summaryText = (summaryResult?.content?.text as string) ?? '';

    const isCompleted = (type: ResultType) => resultFor(type)?.status === 'completed';
    const exportUrl = (type: 'summary' | 'translation' | 'all') =>
        route('documents.export', { document: doc.id, type });
    const canExportAll = (['summary', 'translation', 'quiz'] as ResultType[]).some(isCompleted);

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <div>
                        <Link
                            href={route('documents.index')}
                            className="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-brand-600 dark:text-gray-400 dark:hover:text-brand-400"
                        >
                            <svg className="h-4 w-4 rtl:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                            </svg>
                            {t('document.backToList')}
                        </Link>
                        <h2 className="mt-1 text-xl font-semibold leading-tight text-gray-900 dark:text-gray-100">
                            {doc.original_name}
                        </h2>
                    </div>
                    <StatusBadge status={doc.status} />
                </div>
            }
        >
            <Head title={doc.original_name} />

            <div className="py-12">
                <div className="mx-auto max-w-5xl sm:px-6 lg:px-8">
                    {doc.status === 'failed' && doc.failure_reason && (
                        <div className="mb-6 rounded-xl bg-red-50 p-4 text-sm text-red-700 dark:bg-red-900/30 dark:text-red-300">
                            <span className="font-medium">{t('document.failed')}: </span>
                            {doc.failure_reason}
                        </div>
                    )}

                    <Card padded={false} className="overflow-hidden">
                        <div className="border-b border-gray-200 dark:border-gray-700">
                            <div className="flex items-center justify-between gap-4 pe-4">
                                <nav className="flex gap-6 overflow-x-auto px-6">
                                    {TABS.map((tab) => (
                                        <button
                                            key={tab}
                                            onClick={() => setActiveTab(tab)}
                                            className={`flex shrink-0 items-center gap-1.5 border-b-2 py-4 text-sm font-medium transition ${
                                                activeTab === tab
                                                    ? 'border-brand-500 text-brand-600 dark:text-brand-400'
                                                    : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400'
                                            }`}
                                        >
                                            {TAB_ICONS[tab]}
                                            {t(`document.tabs.${tab}`)}
                                        </button>
                                    ))}
                                </nav>
                                <div className="shrink-0">
                                    <ExportButton
                                        href={exportUrl('all')}
                                        label={t('document.export.all')}
                                        enabled={canExportAll}
                                    />
                                </div>
                            </div>
                        </div>

                        <div className="p-6">
                            {(activeTab === 'summary' || activeTab === 'translation') && (
                                <div className="mb-4 flex justify-end">
                                    <ExportButton
                                        href={exportUrl(activeTab)}
                                        label={t('document.export.pdf')}
                                        enabled={isCompleted(activeTab)}
                                    />
                                </div>
                            )}
                            {activeTab === 'summary' && (
                                <SummaryTab
                                    result={summaryResult}
                                    originalWordCount={doc.original_word_count}
                                />
                            )}
                            {activeTab === 'translation' && (
                                <TranslationTab result={resultFor('translation')} />
                            )}
                            {activeTab === 'quiz' && <QuizTab result={resultFor('quiz')} />}
                            {activeTab === 'pronunciation' && (
                                <PronunciationTab summaryText={summaryText} />
                            )}
                        </div>
                    </Card>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
