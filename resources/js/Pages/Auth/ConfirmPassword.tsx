import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';
import { useTranslation } from 'react-i18next';

export default function ConfirmPassword() {
    const { t } = useTranslation();
    const { data, setData, post, processing, errors, reset } = useForm({
        password: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('password.confirm'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <GuestLayout>
            <Head title={t('auth.confirmPassword.title')} />

            <h1 className="text-xl font-semibold text-gray-900 dark:text-gray-100">
                {t('auth.confirmPassword.title')}
            </h1>
            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                {t('auth.confirmPassword.subtitle')}
            </p>

            <form onSubmit={submit} className="mt-6">
                <InputLabel htmlFor="password" value={t('auth.confirmPassword.password')} />

                <TextInput
                    id="password"
                    type="password"
                    name="password"
                    value={data.password}
                    className="mt-1 block w-full"
                    isFocused={true}
                    onChange={(e) => setData('password', e.target.value)}
                />

                <InputError message={errors.password} className="mt-2" />

                <PrimaryButton className="mt-6 w-full justify-center" disabled={processing}>
                    {t('auth.confirmPassword.submit')}
                </PrimaryButton>
            </form>
        </GuestLayout>
    );
}
