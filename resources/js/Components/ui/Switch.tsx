import { Switch as HeadlessSwitch, SwitchGroup, SwitchLabel } from '@headlessui/react';
import { ReactNode } from 'react';

interface Props {
    checked: boolean;
    onChange: (value: boolean) => void;
    label?: ReactNode;
    disabled?: boolean;
}

export default function Switch({ checked, onChange, label, disabled }: Props) {
    return (
        <SwitchGroup as="div" className="flex items-center gap-2.5">
            <HeadlessSwitch
                checked={checked}
                onChange={onChange}
                disabled={disabled}
                className={`${checked ? 'bg-brand-600' : 'bg-gray-200 dark:bg-gray-600'} relative inline-flex h-5 w-9 shrink-0 items-center rounded-full transition disabled:opacity-50`}
            >
                <span
                    className={`${checked ? 'translate-x-[18px] rtl:-translate-x-[18px]' : 'translate-x-0.5 rtl:-translate-x-0.5'} inline-block h-4 w-4 transform rounded-full bg-white shadow transition`}
                />
            </HeadlessSwitch>
            {label && (
                <SwitchLabel className="cursor-pointer text-sm text-gray-700 dark:text-gray-300">
                    {label}
                </SwitchLabel>
            )}
        </SwitchGroup>
    );
}
