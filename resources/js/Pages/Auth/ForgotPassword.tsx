import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';
import { useTranslation } from 'react-i18next';

export default function ForgotPassword({ status }: { status?: string }) {
    const { t } = useTranslation();
    const { data, setData, post, processing, errors } = useForm({
        email: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('password.email'));
    };

    return (
        <GuestLayout>
            <Head title={t('auth.forgotPassword.title')} />

            <h1 className="text-xl font-semibold text-gray-900 dark:text-gray-100">
                {t('auth.forgotPassword.title')}
            </h1>
            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                {t('auth.forgotPassword.subtitle')}
            </p>

            {status && (
                <div className="mt-4 rounded-lg bg-brand-50 p-3 text-sm font-medium text-brand-700 dark:bg-brand-900/30 dark:text-brand-300">
                    {status}
                </div>
            )}

            <form onSubmit={submit} className="mt-6">
                <InputLabel htmlFor="email" value={t('auth.forgotPassword.email')} />

                <TextInput
                    id="email"
                    type="email"
                    name="email"
                    value={data.email}
                    className="mt-1 block w-full"
                    isFocused={true}
                    onChange={(e) => setData('email', e.target.value)}
                />

                <InputError message={errors.email} className="mt-2" />

                <PrimaryButton className="mt-6 w-full justify-center" disabled={processing}>
                    {t('auth.forgotPassword.submit')}
                </PrimaryButton>
            </form>
        </GuestLayout>
    );
}
