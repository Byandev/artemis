import { Link } from '@inertiajs/react';
import { Clapperboard, FileImage } from 'lucide-react';
import { FinalBadge } from '../atoms';
import { EditUrl, WorkItem } from './types';

interface Props {
    items: WorkItem[];
    editUrl: EditUrl;
    emptyText: string;
    showFeedback?: boolean;
}

/** Action queue list (needs-revision / waiting), each row links to edit. */
export default function WorkList({
    items,
    editUrl,
    emptyText,
    showFeedback = false,
}: Props) {
    if (items.length === 0) {
        return (
            <p className="py-6 text-center text-[12px] text-gray-400 dark:text-gray-500">
                {emptyText}
            </p>
        );
    }

    return (
        <div className="max-h-80 space-y-1.5 overflow-y-auto">
            {items.map((it) => (
                <Link
                    key={it.id}
                    href={editUrl(it.id)}
                    className="block rounded-xl border border-transparent bg-stone-50 px-3 py-2.5 transition-colors hover:border-black/6 hover:bg-stone-100 dark:bg-zinc-800/50 dark:hover:border-white/6 dark:hover:bg-zinc-800"
                >
                    <div className="flex items-center gap-2">
                        {it.format === 'video' ? (
                            <Clapperboard className="h-3.5 w-3.5 shrink-0 text-gray-400" />
                        ) : (
                            <FileImage className="h-3.5 w-3.5 shrink-0 text-gray-400" />
                        )}
                        <span className="truncate text-[13px] font-medium text-gray-700 dark:text-gray-200">
                            {it.name}
                        </span>
                        <span className="ml-auto shrink-0 font-mono text-[10px] text-gray-400 dark:text-gray-500">
                            {it.creative_date}
                        </span>
                    </div>
                    <div className="mt-1.5 flex items-center gap-2">
                        {it.product && (
                            <span className="truncate text-[10px] text-gray-400 dark:text-gray-500">
                                {it.product}
                            </span>
                        )}
                        <span className="ml-auto">
                            <FinalBadge status={it.final_status} />
                        </span>
                    </div>
                    {showFeedback && it.feedback && (
                        <p className="mt-2 line-clamp-2 rounded-lg bg-amber-50 px-2 py-1.5 text-[11px] text-amber-700 dark:bg-amber-500/10 dark:text-amber-300">
                            <span className="font-semibold">
                                {it.reviewer ?? 'Reviewer'}:
                            </span>{' '}
                            {it.feedback}
                        </p>
                    )}
                </Link>
            ))}
        </div>
    );
}
