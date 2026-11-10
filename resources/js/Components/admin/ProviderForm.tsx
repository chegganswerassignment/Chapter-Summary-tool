import { FormEvent, ReactNode, useState } from 'react';
import { useTranslation } from 'react-i18next';

import Button from '@/Components/ui/Button';
import Switch from '@/Components/ui/Switch';
import { aiProviderSchema } from '@/lib/schemas';
import { AdminAiProvider } from '@/types';
import { router } from '@inertiajs/react';

interface Props {
    provider?: AdminAiProvider;
    onDone: () => void;
}

export default function ProviderForm({ provider, onDone }: Props) {
    const { t } = useTranslation();
    const [name, setName] = useState(provider?.name ?? '');
    const [slug, setSlug] = useState(provider?.slug ?? '');
    const [baseUrl, setBaseUrl] = useState(provider?.base_url ?? '');
    const [apiKey, setApiKey] = useState('');
    const [isActive, setIsActive] = useState(provider?.is_active ?? true);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();

        const payload = {
            name,
            slug,
            base_url: baseUrl || undefined,
            api_key: apiKey || undefined,
            is_active: isActive,
        };

        const result = aiProviderSchema.safeParse(payload);

        if (!result.success) {
            const fieldErrors: Record<string, string> = {};
            for (const issue of result.error.issues) {
                fieldErrors[String(issue.path[0])] = issue.message;
            }
            setErrors(fieldErrors);
            return;
        }

        setErrors({});
        setProcessing(true);

        const options = {
            preserveScroll: true,
            onFinish: () => setProcessing(false),
            onSuccess: onDone,
        };

        if (provider) {
            router.put(route('admin.ai-providers.update', provider.id), result.data, options);
        } else {
            router.post(route('admin.ai-providers.store'), result.data, options);
        }
    };

    return (
        <form onSubmit={handleSubmit} className="space-y-4">
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <Field label={t('admin.aiRouting.name')} error={errors.name}>
                    <input
                        value={name}
                        onChange={(e) => setName(e.target.value)}
                        className="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"
                    />
                </Field>
                <Field label={t('admin.aiRouting.slug')} error={errors.slug}>
                    <input
                        value={slug}
                        onChange={(e) => setSlug(e.target.value)}
                        disabled={Boolean(provider)}
                        className="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500 disabled:bg-gray-50 disabled:text-gray-400 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100 dark:disabled:bg-gray-800"
                    />
                </Field>
                <Field label={t('admin.aiRouting.baseUrl')} error={errors.base_url}>
                    <input
                        value={baseUrl}
                        onChange={(e) => setBaseUrl(e.target.value)}
                        className="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"
                    />
                </Field>
                <Field label={t('admin.aiRouting.apiKey')} error={errors.api_key}>
                    <input
                        type="password"
                        value={apiKey}
                        onChange={(e) => setApiKey(e.target.value)}
                        placeholder={t('admin.aiRouting.apiKeyPlaceholder')}
                        className="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"
                    />
                </Field>
            </div>

            <Switch checked={isActive} onChange={setIsActive} label={t('admin.aiRouting.active')} />

            <div className="flex justify-end gap-2 border-t border-gray-100 pt-4 dark:border-gray-700">
                <Button type="button" variant="ghost" onClick={onDone}>
                    {t('admin.aiRouting.cancel')}
                </Button>
                <Button type="submit" variant="primary" disabled={processing}>
                    {t('admin.aiRouting.save')}
                </Button>
            </div>
        </form>
    );
}

function Field({
    label,
    error,
    children,
}: {
    label: string;
    error?: string;
    children: ReactNode;
}) {
    return (
        <div>
            <label className="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">
                {label}
            </label>
            {children}
            {error && <p className="mt-1 text-xs text-red-600 dark:text-red-400">{error}</p>}
        </div>
    );
}
