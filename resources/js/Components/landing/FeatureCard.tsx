import { ReactNode } from 'react';

import Card from '@/Components/ui/Card';

interface Props {
    icon: ReactNode;
    title: string;
    description: string;
}

export default function FeatureCard({ icon, title, description }: Props) {
    return (
        <Card className="group h-full transition duration-300 hover:-translate-y-1 hover:border-brand-300 hover:shadow-[0_0_30px_-8px_rgba(16,185,129,0.45)] dark:hover:border-brand-500/40">
            <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-br from-brand-400 to-brand-600 text-white shadow-[0_0_18px_-4px_rgba(16,185,129,0.6)] transition group-hover:shadow-[0_0_24px_-4px_rgba(16,185,129,0.8)]">
                {icon}
            </div>
            <h3 className="mt-4 font-semibold text-gray-900 dark:text-gray-100">{title}</h3>
            <p className="mt-1.5 text-sm leading-relaxed text-gray-500 dark:text-gray-400">
                {description}
            </p>
        </Card>
    );
}
