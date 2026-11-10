import { FormEvent, useMemo, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';

import Button from '@/Components/ui/Button';
import Card from '@/Components/ui/Card';
import IconCircle from '@/Components/ui/IconCircle';
import StatCard from '@/Components/ui/StatCard';
import StatusBadge from '@/Components/ui/StatusBadge';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { documentUploadSchema, MAX_UPLOAD_MB } from '@/lib/schemas';
import { DocumentSummary, PageProps } from '@/types';
import { Head, Link, router } from '@inertiajs/react';

function formatBytes(bytes: number): string {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

export default function Index({ documents }: PageProps<{ documents: DocumentSummary[] }>) {
    const { t } = useTranslation();
    const fileInputRef = useRef<HTMLInputElement>(null);
    const [dragActive, setDragActive] = useState(false);
    const [selectedFile, setSelectedFile] = useState<File | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [uploading, setUploading] = useState(false);
    const [progress, setProgress] = useState(0);

    const stats = useMemo(
        () => ({
            total: documents.length,
            completed: documents.filter((d) => d.status === 'completed').length,
            processing: documents.filter((d) =>
                ['uploaded', 'extracting', 'queued', 'processing'].includes(d.status),
            ).length,
        }),
        [documents],
    );

    const validateAndSetFile = (file: File | null) => {
        setError(null);

        if (!file) {
            setSelectedFile(null);
            return;
        }

        const result = documentUploadSchema.safeParse({ file });

        if (!result.success) {
            const key = result.error.issues[0]?.message ?? 'documents.errors.fileRequired';
            setError(t(key, { maxMb: MAX_UPLOAD_MB }));
            setSelectedFile(null);
            return;
        }

        setSelectedFile(file);
    };

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();

        if (!selectedFile) {
            setError(t('documents.errors.fileRequired'));
            return;
        }

        setUploading(true);

        router.post(
            route('documents.store'),
            { file: selectedFile },
            {
                forceFormData: true,
                onProgress: (event) => setProgress(event?.percentage ?? 0),
                onFinish: () => {
                    setUploading(false);
                    setProgress(0);
                    setSelectedFile(null);
                    if (fileInputRef.current) fileInputRef.current.value = '';
                },
            },
        );
    };

    const handleDelete = (id: string) => {
        if (window.confirm(t('documents.deleteConfirm'))) {
            router.delete(route('documents.destroy', id));
        }
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center gap-3">
                    <IconCircle tone="brand" size="lg">
                        <svg className="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path
                                strokeLinecap="round"
                                strokeLinejoin="round"
                                strokeWidth="1.75"
                                d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"
                            />
                        </svg>
                    </IconCircle>
                    <div>
                        <h2 className="text-xl font-semibold leading-tight text-gray-900 dark:text-gray-100">
                            {t('documents.title')}
                        </h2>
                        <p className="text-sm text-gray-500 dark:text-gray-400">
                            {t('documents.subtitle')}
                        </p>
                    </div>
                </div>
            }
        >
            <Head title={t('documents.title')} />

            <div className="py-12">
                <div className="mx-auto max-w-5xl space-y-6 sm:px-6 lg:px-8">
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <StatCard
                            label={t('documents.stats.total')}
                            value={stats.total}
                            icon={
                                <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.75" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                                </svg>
                            }
                        />
                        <StatCard
                            label={t('documents.stats.completed')}
                            value={stats.completed}
                            icon={
                                <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.75" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            }
                        />
                        <StatCard
                            label={t('documents.stats.processing')}
                            value={stats.processing}
                            icon={
                                <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.75" d="M12 6v6l4 2m6-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            }
                        />
                    </div>

                    <Card>
                        <h3 className="mb-4 text-lg font-semibold text-gray-900 dark:text-gray-100">
                            {t('documents.upload')}
                        </h3>

                        <form onSubmit={handleSubmit}>
                            <div
                                onDragOver={(e) => {
                                    e.preventDefault();
                                    setDragActive(true);
                                }}
                                onDragLeave={() => setDragActive(false)}
                                onDrop={(e) => {
                                    e.preventDefault();
                                    setDragActive(false);
                                    validateAndSetFile(e.dataTransfer.files?.[0] ?? null);
                                }}
                                onClick={() => fileInputRef.current?.click()}
                                className={`flex cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed p-10 text-center transition ${
                                    dragActive
                                        ? 'border-brand-400 bg-brand-50/60 dark:bg-brand-900/10'
                                        : 'border-gray-200 hover:border-brand-300 hover:bg-brand-50/30 dark:border-gray-600 dark:hover:bg-brand-900/10'
                                }`}
                            >
                                <input
                                    ref={fileInputRef}
                                    type="file"
                                    accept=".pdf,.docx,.doc"
                                    className="hidden"
                                    onChange={(e) => validateAndSetFile(e.target.files?.[0] ?? null)}
                                />
                                <IconCircle tone="brand" size="lg">
                                    <svg className="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path
                                            strokeLinecap="round"
                                            strokeLinejoin="round"
                                            strokeWidth="1.75"
                                            d="M12 16.5V9.75m0 0l-3 3m3-3l3 3M6.75 19.5a4.5 4.5 0 01-1.41-8.775 5.25 5.25 0 0110.233-2.33 3 3 0 013.758 3.848A3.752 3.752 0 0118 19.5H6.75z"
                                        />
                                    </svg>
                                </IconCircle>
                                <p className="mt-3 font-semibold text-gray-800 dark:text-gray-100">
                                    {selectedFile ? selectedFile.name : t('documents.dropHere')}
                                </p>
                                <p className="mt-1 text-sm text-gray-400">
                                    {selectedFile
                                        ? formatBytes(selectedFile.size)
                                        : t('documents.dropHereHint')}
                                </p>
                            </div>

                            {error && (
                                <p className="mt-2 text-sm text-red-600 dark:text-red-400">{error}</p>
                            )}

                            {uploading && (
                                <div className="mt-3 h-2 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                                    <div
                                        className="h-full bg-brand-600 transition-all"
                                        style={{ width: `${progress}%` }}
                                    />
                                </div>
                            )}

                            <Button
                                type="submit"
                                variant="primary"
                                disabled={!selectedFile || uploading}
                                className="mt-4"
                            >
                                {uploading ? t('documents.uploading') : t('documents.uploadButton')}
                            </Button>
                        </form>
                    </Card>

                    <div>
                        <h3 className="mb-3 text-lg font-semibold text-gray-900 dark:text-gray-100">
                            {t('documents.recent')}
                        </h3>

                        {documents.length === 0 ? (
                            <Card className="text-center">
                                <p className="text-sm text-gray-500 dark:text-gray-400">
                                    {t('documents.empty')}
                                </p>
                                <p className="mt-1 text-xs text-gray-400">{t('documents.emptyHint')}</p>
                            </Card>
                        ) : (
                            <div className="space-y-3">
                                {documents.map((doc) => (
                                    <Card
                                        key={doc.id}
                                        padded={false}
                                        className="flex flex-wrap items-center justify-between gap-3 p-4"
                                    >
                                        <div className="flex min-w-0 items-center gap-3">
                                            <IconCircle tone="gray">
                                                <svg
                                                    className="h-4 w-4"
                                                    fill="none"
                                                    viewBox="0 0 24 24"
                                                    stroke="currentColor"
                                                >
                                                    <path
                                                        strokeLinecap="round"
                                                        strokeLinejoin="round"
                                                        strokeWidth="1.75"
                                                        d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"
                                                    />
                                                </svg>
                                            </IconCircle>
                                            <div className="min-w-0">
                                                <p className="truncate font-medium text-gray-900 dark:text-gray-100">
                                                    {doc.original_name}
                                                </p>
                                                <p className="text-xs text-gray-400">
                                                    {formatBytes(doc.file_size)} ·{' '}
                                                    {new Date(doc.created_at).toLocaleDateString()}
                                                </p>
                                            </div>
                                        </div>

                                        <div className="flex items-center gap-4">
                                            <StatusBadge status={doc.status} />
                                            <Link
                                                href={route('documents.show', doc.id)}
                                                className="text-sm font-medium text-brand-600 hover:text-brand-700 dark:text-brand-400 dark:hover:text-brand-300"
                                            >
                                                {t('documents.view')}
                                            </Link>
                                            <button
                                                onClick={() => handleDelete(doc.id)}
                                                className="text-sm font-medium text-gray-400 hover:text-red-600 dark:hover:text-red-400"
                                            >
                                                {t('documents.delete')}
                                            </button>
                                        </div>
                                    </Card>
                                ))}
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
