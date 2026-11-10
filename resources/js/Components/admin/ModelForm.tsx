import { FormEvent, ReactNode, useState } from 'react';
import { useTranslation } from 'react-i18next';

import Button from '@/Components/ui/Button';
import { aiModelSchema } from '@/lib/schemas';
import { AdminAiModel } from '@/types';
import { router } from '@inertiajs/react';

interface Props {
    providerId: number;
    model?: AdminAiModel;
    onDone: () => void;
}

export default function ModelForm({ providerId, model, onDone }: Props) {
    const { t } = useTranslation();
    const [displayName, setDisplayName] = useState(model?.display_name ?? '');
    const [apiModelCode, setApiModelCode] = useState(model?.api_model_code ?? '');
    const [contextWindow, setContextWindow] = useState(model?.context_window ?? 8192);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();

        const result = aiModelSchema.safeParse({
            provider_id: providerId,
            display_name: displayName,
            api_model_code: apiModelCode,
            context_window: Number(contextWindow),
        });

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
            onError: (serverErrors: Record<string, string>) => setErrors(serverErrors),
        };

        if (model) {
            router.put(route('admin.ai-models.update', model.id), result.data, options);
        } else {
            router.post(route('admin.ai-models.store'), result.data, options);
        }
    };

    const inputClass =
        'w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100';

    return (
        <form onSubmit={handleSubmit} className="space-y-4">
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <Field label={t('admin.aiRouting.displayName')} error={errors.display_name}>
                    <input
                        value={displayName}
                        onChange={(e) => setDisplayName(e.target.value)}
                        className={inputClass}
                    />
                </Field>
                <Field label={t('admin.aiRouting.modelCode')} error={errors.api_model_code}>
                    <input
                        value={apiModelCode}
                        onChange={(e) => setApiModelCode(e.target.value)}
                        className={inputClass}
                    />
                </Field>
                <Field label={t('admin.aiRouting.contextWindow')} error={errors.context_window}>
                    <input
                        type="number"
                        value={contextWindow}
                        onChange={(e) => setContextWindow(Number(e.target.value))}
                        className={inputClass}
                    />
                </Field>
            </div>

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

function Field({ label, error, children }: { label: string; error?: string; children: ReactNode }) {
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
