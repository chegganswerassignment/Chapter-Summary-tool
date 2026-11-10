import LanguageSwitcher from '@/Components/LanguageSwitcher';
import ThemeToggle from '@/Components/ThemeToggle';
import Card from '@/Components/ui/Card';
import { Link } from '@inertiajs/react';
import { PropsWithChildren } from 'react';
import { useTranslation } from 'react-i18next';

export default function Guest({ children }: PropsWithChildren) {
    const { t } = useTranslation();

    return (
        <div className="flex min-h-screen flex-col bg-gray-50 dark:bg-gray-900">
            <div className="flex items-center justify-between px-6 py-4">
                <Link href="/" className="flex items-center gap-2">
                    <span className="flex h-8 w-8 items-center justify-center rounded-lg bg-brand-600 text-sm font-bold text-white">
                        C
                    </span>
                    <span className="text-sm font-semibold text-gray-800 dark:text-gray-100">
                        {t('app.name')}
                    </span>
                </Link>
                <div className="flex items-center gap-2">
                    <LanguageSwitcher />
                    <ThemeToggle />
                </div>
            </div>

            <div className="flex flex-1 flex-col items-center justify-center px-6 pb-12">
                <Card className="w-full sm:max-w-md">{children}</Card>
            </div>
        </div>
    );
}
