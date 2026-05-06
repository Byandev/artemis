import { type ReactNode } from 'react';

interface Props {
    title: string;
    description?: string;
    children?: ReactNode;
}

export default function PageHeader({ title, description, children }: Props) {
    return (
        <div className="flex items-start justify-between gap-4 pb-5 mb-6 border-b border-black/6 dark:border-white/6 sm:items-center">
            <div className="min-w-0 flex-1">
                <h1 className="my-0! text-[22px]! font-semibold tracking-tight text-gray-800 dark:text-gray-100">
                    {title}
                </h1>
                {description && (
                    <p className="mt-0.5 text-[11px] text-gray-400 dark:text-gray-500">
                        {description}
                    </p>
                )}
            </div>
            {children && (
                <div className="flex max-w-full flex-nowrap items-center gap-2 overflow-x-auto shrink-0">
                    {children}
                </div>
            )}
        </div>
    );
}
