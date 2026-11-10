import Checkbox from '@/Components/Checkbox';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';
import { useTranslation } from 'react-i18next';

export default function Login({
    status,
    canResetPassword,
}: {
    status?: string;
    canResetPassword: boolean;
}) {
    const { t } = useTranslation();
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false as boolean,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('login'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <GuestLayout>
            <Head title={t('auth.login.title')} />

            <h1 className="text-xl font-semibold text-gray-900 dark:text-gray-100">
                {t('auth.login.title')}
            </h1>
            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                {t('auth.login.subtitle')}
            </p>

            {status && (
                <div className="mt-4 rounded-lg bg-brand-50 p-3 text-sm font-medium text-brand-700 dark:bg-brand-900/30 dark:text-brand-300">
                    {status}
                </div>
            )}

            <form onSubmit={submit} className="mt-6">
                <div>
                    <InputLabel htmlFor="email" value={t('auth.login.email')} />

                    <TextInput
                        id="email"
                        type="email"
                        name="email"
                        value={data.email}
                        className="mt-1 block w-full"
                        autoComplete="username"
                        isFocused={true}
                        onChange={(e) => setData('email', e.target.value)}
                    />

                    <InputError message={errors.email} className="mt-2" />
                </div>

                <div className="mt-4">
                    <InputLabel htmlFor="password" value={t('auth.login.password')} />

                    <TextInput
                        id="password"
                        type="password"
                        name="password"
                        value={data.password}
                        className="mt-1 block w-full"
                        autoComplete="current-password"
                        onChange={(e) => setData('password', e.target.value)}
                    />

                    <InputError message={errors.password} className="mt-2" />
                </div>

                <div className="mt-4 flex items-center justify-between">
                    <label className="flex items-center">
                        <Checkbox
                            name="remember"
                            checked={data.remember}
                            onChange={(e) =>
                                setData(
                                    'remember',
                                    (e.target.checked || false) as false,
                                )
                            }
                        />
                        <span className="ms-2 text-sm text-gray-600 dark:text-gray-400">
                            {t('auth.login.remember')}
                        </span>
                    </label>

                    {canResetPassword && (
                        <Link
                            href={route('password.request')}
                            className="rounded-md text-sm text-brand-600 underline hover:text-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 dark:text-brand-400 dark:hover:text-brand-300 dark:focus:ring-offset-gray-800"
                        >
                            {t('auth.login.forgot')}
                        </Link>
                    )}
                </div>

                <PrimaryButton className="mt-6 w-full justify-center" disabled={processing}>
                    {t('auth.login.submit')}
                </PrimaryButton>

                <p className="mt-6 text-center text-sm text-gray-500 dark:text-gray-400">
                    {t('auth.login.noAccount')}{' '}
                    <Link
                        href={route('register')}
                        className="font-medium text-brand-600 hover:text-brand-700 dark:text-brand-400 dark:hover:text-brand-300"
                    >
                        {t('auth.login.register')}
                    </Link>
                </p>
            </form>
        </GuestLayout>
    );
}
