import { ReactNode } from 'react';

interface Props {
    children: ReactNode;
    tone?: 'brand' | 'gray';
    size?: 'sm' | 'md' | 'lg';
}

const TONE_CLASSES: Record<NonNullable<Props['tone']>, string> = {
    brand: 'bg-brand-100 text-brand-700 dark:bg-brand-900/40 dark:text-brand-300',
    gray: 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-300',
};

const SIZE_CLASSES: Record<NonNullable<Props['size']>, string> = {
    sm: 'h-8 w-8 text-xs',
    md: 'h-10 w-10 text-sm',
    lg: 'h-12 w-12 text-base',
};

export default function IconCircle({ children, tone = 'brand', size = 'md' }: Props) {
    return (
        <div
            className={`flex shrink-0 items-center justify-center rounded-full font-semibold ${TONE_CLASSES[tone]} ${SIZE_CLASSES[size]}`}
        >
            {children}
        </div>
    );
}
