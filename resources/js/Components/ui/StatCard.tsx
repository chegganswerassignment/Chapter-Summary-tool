import { ReactNode } from 'react';

import Card from './Card';

interface Props {
    label: string;
    value: string | number;
    icon: ReactNode;
}

export default function StatCard({ label, value, icon }: Props) {
    return (
        <Card className="flex items-center gap-4">
            <div className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-900/30 dark:text-brand-400">
                {icon}
            </div>
            <div>
                <p className="text-2xl font-semibold text-gray-900 dark:text-gray-100">{value}</p>
                <p className="text-sm text-gray-500 dark:text-gray-400">{label}</p>
            </div>
        </Card>
    );
}
