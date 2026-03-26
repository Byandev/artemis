import { type ReactNode } from 'react';

interface Props {
    title: string;
    description?: string;
    children?: ReactNode;
}

export default function PageHeader({ title, description, children }: Props) {
    return (
        <div className="flex flex-wrap items-center justify-between gap-4 pb-5 mb-6 border-b border-black/6 dark:border-white/6">
            <div>
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
                <div className="flex flex-wrap items-center gap-2">
                    {children}
                </div>
            )}
        </div>
    );
}
