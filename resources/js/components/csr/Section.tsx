import { cn } from '@/lib/utils';
import { Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';

interface Props {
    title: string;
    desc?: string;
    action?: { label: string; href: string };
    children: React.ReactNode;
    className?: string;
}

export default function Section({ title, desc, action, children, className = '' }: Props) {
    return (
        <div className={cn('overflow-hidden rounded-2xl border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900', className)}>
            <div className="flex items-center justify-between border-b border-black/4 px-5 py-4 dark:border-white/4">
                <div>
                    <h3 className="text-[13px] font-semibold text-gray-800 dark:text-white">{title}</h3>
                    {desc && <p className="mt-0.5 text-[11px] text-gray-400 dark:text-gray-500">{desc}</p>}
                </div>
                {action && (
                    <Link
                        href={action.href}
                        className="flex items-center gap-1 text-[11px] font-medium text-emerald-600 hover:text-emerald-700 dark:text-emerald-400 dark:hover:text-emerald-300"
                    >
                        {action.label}
                        <ArrowRight className="h-3 w-3" />
                    </Link>
                )}
            </div>
            {children}
        </div>
    );
}
