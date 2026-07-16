import { cn } from '@/lib/utils';
import { Link } from '@inertiajs/react';

export interface DashboardTab {
    key: string;
    label: string;
    url: string;
}

/**
 * Sales & Marketing dashboard tab bar — a refined underline style in the Artemis
 * brand colour. Plain text labels over a full-width divider; the active tab is
 * brand-coloured with a matching underline that sits on the divider, and
 * inactive tabs get a subtle underline on hover. Each tab is its own URL
 * (Inertia Link); every page rendered as an S&M tab shares this bar.
 */
export function DashboardTabNav({
    tabs,
    active,
    className,
}: {
    tabs: DashboardTab[];
    active?: string;
    className?: string;
}) {
    const current = active ?? tabs[0]?.key;

    return (
        <nav
            className={cn(
                'flex gap-6 overflow-x-auto border-b border-black/8 dark:border-white/10',
                className,
            )}
        >
            {tabs.map((t) => {
                const isActive = t.key === current;
                return (
                    <Link
                        key={t.key}
                        href={t.url}
                        preserveScroll
                        className={`-mb-px shrink-0 border-b-2 px-1 pb-3 font-mono text-[13px] font-semibold whitespace-nowrap transition-colors duration-200 ${
                            isActive
                                ? 'border-brand-500 text-brand-600 dark:border-brand-400 dark:text-brand-400'
                                : 'border-transparent text-gray-500 hover:border-black/15 hover:text-gray-900 dark:text-gray-400 dark:hover:border-white/20 dark:hover:text-gray-100'
                        }`}
                    >
                        {t.label}
                    </Link>
                );
            })}
        </nav>
    );
}
