import { ButtonHTMLAttributes, ReactNode } from 'react';

type Variant = 'primary' | 'secondary' | 'danger' | 'ghost';
type Size = 'sm' | 'md';

interface Props extends ButtonHTMLAttributes<HTMLButtonElement> {
    variant?: Variant;
    size?: Size;
    icon?: ReactNode;
}

const VARIANT_CLASSES: Record<Variant, string> = {
    primary:
        'bg-brand-600 text-white hover:bg-brand-700 focus-visible:outline-brand-600 disabled:hover:bg-brand-600 dark:bg-brand-500 dark:hover:bg-brand-400',
    secondary:
        'bg-white text-gray-700 border border-gray-300 hover:bg-gray-50 focus-visible:outline-gray-400 dark:bg-gray-800 dark:text-gray-200 dark:border-gray-600 dark:hover:bg-gray-700',
    danger: 'bg-white text-red-600 border border-red-200 hover:bg-red-50 focus-visible:outline-red-500 dark:bg-gray-800 dark:text-red-400 dark:border-red-900/50 dark:hover:bg-red-900/20',
    ghost: 'text-gray-500 hover:bg-gray-100 hover:text-gray-700 focus-visible:outline-gray-400 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-200',
};

const SIZE_CLASSES: Record<Size, string> = {
    sm: 'px-3 py-1.5 text-xs',
    md: 'px-4 py-2 text-sm',
};

export default function Button({
    variant = 'secondary',
    size = 'md',
    icon,
    className = '',
    children,
    ...rest
}: Props) {
    return (
        <button
            className={`inline-flex items-center gap-1.5 rounded-lg font-medium shadow-sm transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 disabled:cursor-not-allowed disabled:opacity-50 ${VARIANT_CLASSES[variant]} ${SIZE_CLASSES[size]} ${className}`}
            {...rest}
        >
            {icon}
            {children}
        </button>
    );
}
