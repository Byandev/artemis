import { ReactNode } from 'react';

interface Props {
    title: string;
    icon?: ReactNode;
    count?: number;
    /** Optional right-aligned header content (e.g. a toggle). */
    action?: ReactNode;
    children: ReactNode;
}

/** Themed section container with a header divider, matching ComponentCard. */
export default function Panel({ title, icon, count, action, children }: Props) {
    return (
        <div className="rounded-[14px] border border-black/6 bg-white transition-colors hover:border-black/10 dark:border-white/6 dark:bg-zinc-900 dark:hover:border-white/10">
            <div className="flex items-center gap-1.5 border-b border-black/5 px-5 py-3.5 dark:border-white/5">
                {icon}
                <h3 className="text-[13px] font-semibold tracking-tight text-gray-700 dark:text-gray-200">
                    {title}
                </h3>
                {(count !== undefined || action) && (
                    <div className="ml-auto flex items-center gap-2">
                        {count !== undefined && (
                            <span className="rounded-full bg-stone-100 px-2 py-0.5 font-mono text-[10px] text-gray-500 dark:bg-zinc-800 dark:text-gray-400">
                                {count}
                            </span>
                        )}
                        {action}
                    </div>
                )}
            </div>
            <div className="p-4 sm:p-5">{children}</div>
        </div>
    );
}
