import { HTMLAttributes, ReactNode } from 'react';

interface Props extends HTMLAttributes<HTMLDivElement> {
    children: ReactNode;
    padded?: boolean;
}

export default function Card({ children, padded = true, className = '', ...rest }: Props) {
    return (
        <div
            className={`rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800 ${
                padded ? 'p-6' : ''
            } ${className}`}
            {...rest}
        >
            {children}
        </div>
    );
}
