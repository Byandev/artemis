import { type ReactNode } from 'react';

/**
 * The card every figure on the dashboard sits in: a label, something small on
 * the top-right (a trend, a share), and whatever the card reports underneath.
 *
 * Shared so the KPI row and the leaders below it are visibly the same object —
 * they differ in what they say, not in how they are drawn.
 */
export default function StatShell({
    label,
    aside,
    children,
}: {
    label: string;
    /** Top-right slot: the trend on a KPI, the share on a leader. */
    aside?: ReactNode;
    children: ReactNode;
}) {
    return (
        <div className="rounded-[14px] border border-black/6 bg-white p-[18px] pb-6 transition-colors hover:border-black/10 dark:border-white/6 dark:bg-zinc-900 dark:hover:border-white/10">
            <div className="flex items-start justify-between gap-3">
                <p className="text-[11px] font-medium tracking-wide text-gray-400 uppercase dark:text-gray-500">
                    {label}
                </p>
                {aside}
            </div>

            <div className="mt-4">{children}</div>
        </div>
    );
}
