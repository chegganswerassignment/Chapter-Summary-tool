import IconCircle from '@/Components/ui/IconCircle';
import PrimaryButton from '@/Components/PrimaryButton';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';
import { useTranslation } from 'react-i18next';

export default function VerifyEmail({ status }: { status?: string }) {
    const { t } = useTranslation();
    const { post, processing } = useForm({});

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('verification.send'));
    };

    return (
        <GuestLayout>
            <Head title={t('auth.verifyEmail.title')} />

            <IconCircle tone="brand" size="lg">
                <svg className="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        strokeWidth="1.75"
                        d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75"
                    />
                </svg>
            </IconCircle>

            <h1 className="mt-4 text-xl font-semibold text-gray-900 dark:text-gray-100">
                {t('auth.verifyEmail.title')}
            </h1>
            <p className="mt-2 text-sm leading-relaxed text-gray-500 dark:text-gray-400">
                {t('auth.verifyEmail.subtitle')}
            </p>

            {status === 'verification-link-sent' && (
                <div className="mt-4 rounded-lg bg-brand-50 p-3 text-sm font-medium text-brand-700 dark:bg-brand-900/30 dark:text-brand-300">
                    {t('auth.verifyEmail.resendSuccess')}
                </div>
            )}

            <form onSubmit={submit} className="mt-6 flex items-center justify-between">
                <PrimaryButton disabled={processing}>
                    {t('auth.verifyEmail.resendButton')}
                </PrimaryButton>

                <Link
                    href={route('logout')}
                    method="post"
                    as="button"
                    className="rounded-md text-sm text-gray-500 underline hover:text-gray-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 dark:text-gray-400 dark:hover:text-gray-100 dark:focus:ring-offset-gray-800"
                >
                    {t('auth.verifyEmail.logout')}
                </Link>
            </form>
        </GuestLayout>
    );
}
