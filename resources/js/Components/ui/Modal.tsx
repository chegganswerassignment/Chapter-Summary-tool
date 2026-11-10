import { Dialog, DialogPanel, DialogTitle } from '@headlessui/react';
import { ReactNode } from 'react';

interface Props {
    open: boolean;
    onClose: () => void;
    title: string;
    description?: string;
    children: ReactNode;
    maxWidth?: 'md' | 'lg' | 'xl';
}

const WIDTH_CLASSES: Record<NonNullable<Props['maxWidth']>, string> = {
    md: 'max-w-md',
    lg: 'max-w-lg',
    xl: 'max-w-xl',
};

export default function Modal({ open, onClose, title, description, children, maxWidth = 'lg' }: Props) {
    return (
        <Dialog open={open} onClose={onClose} transition className="relative z-50">
            <div
                className="fixed inset-0 bg-gray-900/50 backdrop-blur-sm transition duration-200 ease-out data-[closed]:opacity-0 dark:bg-black/60"
                aria-hidden="true"
            />

            <div className="fixed inset-0 flex w-screen items-center justify-center p-4">
                <DialogPanel
                    transition
                    className={`w-full ${WIDTH_CLASSES[maxWidth]} rounded-2xl bg-white p-6 shadow-xl transition duration-200 ease-out data-[closed]:translate-y-2 data-[closed]:opacity-0 dark:bg-gray-800`}
                >
                    <DialogTitle className="text-lg font-semibold text-gray-900 dark:text-gray-100">
                        {title}
                    </DialogTitle>
                    {description && (
                        <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">{description}</p>
                    )}
                    <div className="mt-5">{children}</div>
                </DialogPanel>
            </div>
        </Dialog>
    );
}
