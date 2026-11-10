import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';

import ModelForm from '@/Components/admin/ModelForm';
import ProviderForm from '@/Components/admin/ProviderForm';
import Button from '@/Components/ui/Button';
import Card from '@/Components/ui/Card';
import IconCircle from '@/Components/ui/IconCircle';
import Modal from '@/Components/ui/Modal';
import StatCard from '@/Components/ui/StatCard';
import Switch from '@/Components/ui/Switch';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { AdminAiModel, AdminAiProvider, PageProps } from '@/types';
import { Head, router } from '@inertiajs/react';

function formatContextWindow(tokens: number): string {
    if (tokens >= 1_000_000) return `${(tokens / 1_000_000).toFixed(tokens % 1_000_000 === 0 ? 0 : 1)}M`;
    if (tokens >= 1_000) return `${Math.round(tokens / 1000)}K`;
    return String(tokens);
}

export default function AiRouting({
    providers,
    activeModelId,
}: PageProps<{ providers: AdminAiProvider[]; activeModelId: number | null }>) {
    const { t } = useTranslation();
    const [providerModal, setProviderModal] = useState<'new' | AdminAiProvider | null>(null);
    const [modelModal, setModelModal] = useState<{ providerId: number; model?: AdminAiModel } | null>(
        null,
    );

    const stats = useMemo(
        () => ({
            total: providers.length,
            active: providers.filter((p) => p.is_active).length,
            models: providers.reduce((sum, p) => sum + p.models.length, 0),
        }),
        [providers],
    );

    const toggleProviderActive = (provider: AdminAiProvider, value: boolean) => {
        router.put(
            route('admin.ai-providers.update', provider.id),
            {
                name: provider.name,
                slug: provider.slug,
                base_url: provider.base_url ?? '',
                is_active: value,
            },
            { preserveScroll: true },
        );
    };

    const handleDeleteProvider = (provider: AdminAiProvider) => {
        if (window.confirm(t('admin.aiRouting.deleteProviderConfirm', { name: provider.name }))) {
            router.delete(route('admin.ai-providers.destroy', provider.id), { preserveScroll: true });
        }
    };

    const activeProvider = providers.find((p) => p.models.some((m) => m.id === activeModelId));

    const handleActivate = (modelId: string) => {
        if (!modelId) return;
        router.put(route('admin.ai-models.activate'), { model_id: Number(modelId) }, { preserveScroll: true });
    };

    const handleDeleteModel = (model: AdminAiModel) => {
        if (window.confirm(t('admin.aiRouting.deleteModelConfirm', { name: model.display_name }))) {
            router.delete(route('admin.ai-models.destroy', model.id), { preserveScroll: true });
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
                                d="M9.75 3.104v5.714a2.25 2.25 0 01-.659 1.591L5 14.5M9.75 3.104c-.251.023-.501.05-.75.082m.75-.082a24.301 24.301 0 014.5 0m0 0v5.714c0 .597.237 1.17.659 1.591L19.8 15.3M14.25 3.104c.251.023.501.05.75.082M19.8 15.3l-1.57.393A9.065 9.065 0 0112 15a9.065 9.065 0 00-6.23-.693L5 14.5m14.8.8l1.402 1.402c1.232 1.232.65 3.318-1.067 3.611A48.309 48.309 0 0112 21c-2.773 0-5.491-.235-8.135-.687-1.718-.293-2.3-2.379-1.067-3.61L5 14.5"
                            />
                        </svg>
                    </IconCircle>
                    <div>
                        <h2 className="text-xl font-semibold leading-tight text-gray-900 dark:text-gray-100">
                            {t('admin.aiRouting.title')}
                        </h2>
                        <p className="text-sm text-gray-500 dark:text-gray-400">
                            {t('admin.aiRouting.subtitle')}
                        </p>
                    </div>
                </div>
            }
        >
            <Head title={t('admin.aiRouting.title')} />

            <div className="py-12">
                <div className="mx-auto max-w-5xl space-y-6 sm:px-6 lg:px-8">
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <StatCard
                            label={t('admin.aiRouting.stats.totalProviders')}
                            value={stats.total}
                            icon={
                                <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.75" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 5.625c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125" />
                                </svg>
                            }
                        />
                        <StatCard
                            label={t('admin.aiRouting.stats.activeProviders')}
                            value={stats.active}
                            icon={
                                <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.75" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            }
                        />
                        <StatCard
                            label={t('admin.aiRouting.stats.totalModels')}
                            value={stats.models}
                            icon={
                                <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.75" d="M21 7.5l-9-5.25L3 7.5m18 0l-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9" />
                                </svg>
                            }
                        />
                    </div>

                    <Card>
                        <div className="flex flex-wrap items-start justify-between gap-4">
                            <div className="max-w-md">
                                <h3 className="text-lg font-semibold text-gray-900 dark:text-gray-100">
                                    {t('admin.aiRouting.activeModel.title')}
                                </h3>
                                <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                    {t('admin.aiRouting.activeModel.description')}
                                </p>
                            </div>
                            <select
                                aria-label={t('admin.aiRouting.activeModel.select')}
                                value={activeModelId ?? ''}
                                onChange={(e) => handleActivate(e.target.value)}
                                className="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:w-80 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"
                            >
                                {activeModelId === null && (
                                    <option value="">{t('admin.aiRouting.activeModel.select')}</option>
                                )}
                                {providers.map((provider) => (
                                    <optgroup key={provider.id} label={provider.name}>
                                        {provider.models.map((model) => (
                                            <option
                                                key={model.id}
                                                value={model.id}
                                                disabled={!provider.is_active && model.id !== activeModelId}
                                            >
                                                {model.display_name} ({model.api_model_code})
                                                {!provider.is_active
                                                    ? ` — ${t('admin.aiRouting.activeModel.inactiveProvider')}`
                                                    : ''}
                                            </option>
                                        ))}
                                    </optgroup>
                                ))}
                            </select>
                        </div>

                        {activeModelId === null && (
                            <p className="mt-4 rounded-lg bg-amber-50 p-3 text-sm text-amber-700 dark:bg-amber-900/30 dark:text-amber-300">
                                {t('admin.aiRouting.activeModel.none')}
                            </p>
                        )}
                        {activeProvider && !activeProvider.has_api_key && (
                            <p className="mt-4 rounded-lg bg-amber-50 p-3 text-sm text-amber-700 dark:bg-amber-900/30 dark:text-amber-300">
                                {t('admin.aiRouting.activeModel.noKey')}
                            </p>
                        )}
                    </Card>

                    <div className="flex items-center justify-between">
                        <h3 className="text-lg font-semibold text-gray-900 dark:text-gray-100">
                            {t('admin.aiRouting.providers')}
                        </h3>
                        <Button
                            variant="primary"
                            onClick={() => setProviderModal('new')}
                            icon={<PlusIcon />}
                        >
                            {t('admin.aiRouting.addProvider')}
                        </Button>
                    </div>

                    {providers.length === 0 && (
                        <Card className="text-center text-sm text-gray-500 dark:text-gray-400">
                            {t('admin.aiRouting.emptyProviders')}
                        </Card>
                    )}

                    <div className="space-y-4">
                        {providers.map((provider) => (
                            <Card key={provider.id} padded={false} className="overflow-hidden">
                                <div className="flex flex-wrap items-start justify-between gap-4 p-5">
                                    <div className="flex items-start gap-3">
                                        <IconCircle tone="brand">
                                            {provider.name.charAt(0).toUpperCase()}
                                        </IconCircle>
                                        <div>
                                            <div className="flex flex-wrap items-center gap-2">
                                                <p className="font-semibold text-gray-900 dark:text-gray-100">
                                                    {provider.name}
                                                </p>
                                                <span className="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-500 dark:bg-gray-700 dark:text-gray-300">
                                                    {provider.slug}
                                                </span>
                                                <span
                                                    className={`rounded-full px-2 py-0.5 text-xs font-medium ${
                                                        provider.has_api_key
                                                            ? 'bg-brand-50 text-brand-700 dark:bg-brand-900/30 dark:text-brand-300'
                                                            : 'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300'
                                                    }`}
                                                >
                                                    {provider.has_api_key
                                                        ? t('admin.aiRouting.hasApiKey')
                                                        : t('admin.aiRouting.noApiKey')}
                                                </span>
                                            </div>
                                            {provider.base_url && (
                                                <p className="mt-1 text-xs text-gray-400">{provider.base_url}</p>
                                            )}
                                        </div>
                                    </div>

                                    <div className="flex items-center gap-3">
                                        <Switch
                                            checked={provider.is_active}
                                            onChange={(value) => toggleProviderActive(provider, value)}
                                            label={t('admin.aiRouting.active')}
                                        />
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => setProviderModal(provider)}
                                            aria-label={t('admin.aiRouting.edit')}
                                        >
                                            <PencilIcon />
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => handleDeleteProvider(provider)}
                                            aria-label={t('admin.aiRouting.delete')}
                                            className="hover:!bg-red-50 hover:!text-red-600 dark:hover:!bg-red-900/20 dark:hover:!text-red-400"
                                        >
                                            <TrashIcon />
                                        </Button>
                                    </div>
                                </div>

                                <div className="border-t border-gray-100 bg-gray-50/60 p-5 dark:border-gray-700 dark:bg-gray-900/30">
                                    <div className="mb-3 flex items-center justify-between">
                                        <h4 className="text-sm font-medium text-gray-700 dark:text-gray-300">
                                            {t('admin.aiRouting.models')}
                                        </h4>
                                        <Button
                                            variant="secondary"
                                            size="sm"
                                            icon={<PlusIcon className="h-3.5 w-3.5" />}
                                            onClick={() => setModelModal({ providerId: provider.id })}
                                        >
                                            {t('admin.aiRouting.addModel')}
                                        </Button>
                                    </div>

                                    {provider.models.length === 0 ? (
                                        <p className="text-sm text-gray-400">{t('admin.aiRouting.emptyModels')}</p>
                                    ) : (
                                        <div className="space-y-2">
                                            {provider.models.map((model) => (
                                                <div
                                                    key={model.id}
                                                    className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-gray-200 bg-white px-4 py-2.5 dark:border-gray-700 dark:bg-gray-800"
                                                >
                                                    <div className="flex flex-wrap items-center gap-2">
                                                        <span className="font-medium text-gray-800 dark:text-gray-100">
                                                            {model.display_name}
                                                        </span>
                                                        <span className="text-xs text-gray-400">
                                                            {model.api_model_code}
                                                        </span>
                                                        <span className="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-500 dark:bg-gray-700 dark:text-gray-300">
                                                            {formatContextWindow(model.context_window)} tokens
                                                        </span>
                                                        {model.is_active && (
                                                            <span className="inline-flex items-center gap-1 rounded-full bg-brand-50 px-2 py-0.5 text-xs font-medium text-brand-700 dark:bg-brand-900/30 dark:text-brand-300">
                                                                <StarIcon />
                                                                {t('admin.aiRouting.activeModel.badge')}
                                                            </span>
                                                        )}
                                                    </div>
                                                    <div className="flex items-center gap-1">
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() =>
                                                                setModelModal({ providerId: provider.id, model })
                                                            }
                                                            aria-label={t('admin.aiRouting.edit')}
                                                        >
                                                            <PencilIcon className="h-3.5 w-3.5" />
                                                        </Button>
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() => handleDeleteModel(model)}
                                                            disabled={model.is_active}
                                                            title={model.is_active ? t('admin.aiRouting.activeModel.deleteBlocked') : undefined}
                                                            aria-label={t('admin.aiRouting.delete')}
                                                            className="hover:!bg-red-50 hover:!text-red-600 dark:hover:!bg-red-900/20 dark:hover:!text-red-400"
                                                        >
                                                            <TrashIcon className="h-3.5 w-3.5" />
                                                        </Button>
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    )}
                                </div>
                            </Card>
                        ))}
                    </div>
                </div>
            </div>

            <Modal
                open={providerModal !== null}
                onClose={() => setProviderModal(null)}
                title={
                    providerModal === 'new'
                        ? t('admin.aiRouting.addProvider')
                        : t('admin.aiRouting.editProvider')
                }
            >
                <ProviderForm
                    provider={providerModal !== 'new' ? providerModal ?? undefined : undefined}
                    onDone={() => setProviderModal(null)}
                />
            </Modal>

            <Modal
                open={modelModal !== null}
                onClose={() => setModelModal(null)}
                title={modelModal?.model ? t('admin.aiRouting.editModel') : t('admin.aiRouting.addModel')}
            >
                {modelModal && (
                    <ModelForm
                        providerId={modelModal.providerId}
                        model={modelModal.model}
                        onDone={() => setModelModal(null)}
                    />
                )}
            </Modal>
        </AuthenticatedLayout>
    );
}

function PlusIcon({ className = 'h-4 w-4' }: { className?: string }) {
    return (
        <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 4.5v15m7.5-7.5h-15" />
        </svg>
    );
}

function PencilIcon({ className = 'h-4 w-4' }: { className?: string }) {
    return (
        <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth="1.75"
                d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10"
            />
        </svg>
    );
}

function TrashIcon({ className = 'h-4 w-4' }: { className?: string }) {
    return (
        <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth="1.75"
                d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"
            />
        </svg>
    );
}

function StarIcon() {
    return (
        <svg className="h-3 w-3" fill="currentColor" viewBox="0 0 20 20">
            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.286 3.958a1 1 0 00.95.69h4.162c.969 0 1.371 1.24.588 1.81l-3.367 2.447a1 1 0 00-.363 1.118l1.287 3.958c.3.922-.755 1.688-1.538 1.118l-3.367-2.447a1 1 0 00-1.176 0l-3.367 2.447c-.783.57-1.838-.196-1.538-1.118l1.287-3.958a1 1 0 00-.363-1.118L2.063 9.385c-.783-.57-.38-1.81.588-1.81h4.162a1 1 0 00.95-.69l1.286-3.958z" />
        </svg>
    );
}
