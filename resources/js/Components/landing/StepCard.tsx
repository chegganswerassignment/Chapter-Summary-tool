interface Props {
    number: number;
    title: string;
    description: string;
}

export default function StepCard({ number, title, description }: Props) {
    return (
        <div className="relative flex-1">
            <div className="flex h-11 w-11 items-center justify-center rounded-full bg-brand-600 text-lg font-bold text-white shadow-sm">
                {number}
            </div>
            <h3 className="mt-4 font-semibold text-gray-900 dark:text-gray-100">{title}</h3>
            <p className="mt-1.5 text-sm leading-relaxed text-gray-500 dark:text-gray-400">
                {description}
            </p>
        </div>
    );
}
