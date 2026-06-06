import { Link } from '@inertiajs/react';
import { REVIEW_AVATAR_BG } from '../../types';
import { ReviewBadge } from '../atoms';
import { ActivityRow, EditUrl } from './types';

interface Props {
    rows: ActivityRow[];
    editUrl: EditUrl;
}

/** Reverse-chronological feed of reviews on the editor's creatives. */
export default function ActivityFeed({ rows, editUrl }: Props) {
    if (rows.length === 0) {
        return (
            <p className="py-6 text-center text-[12px] text-gray-400 dark:text-gray-500">
                No recent review activity.
            </p>
        );
    }

    return (
        <div className="max-h-80 space-y-3 overflow-y-auto">
            {rows.map((r) => (
                <div key={r.id} className="flex gap-2.5">
                    <span
                        className={`mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full font-mono text-[10px] font-bold ${REVIEW_AVATAR_BG[r.status]}`}
                    >
                        {(r.reviewer ?? '?').charAt(0).toUpperCase()}
                    </span>
                    <div className="min-w-0 flex-1">
                        <div className="flex items-center gap-2">
                            <Link
                                href={editUrl(r.creative_id)}
                                className="truncate text-[12px] font-medium text-gray-700 hover:underline dark:text-gray-200"
                            >
                                {r.creative_name ?? 'Creative'}
                            </Link>
                            <span className="ml-auto shrink-0 font-mono text-[10px] text-gray-400 dark:text-gray-500">
                                {r.created_at}
                            </span>
                        </div>
                        <div className="mt-1">
                            <ReviewBadge status={r.status} />
                        </div>
                        {r.feedback && (
                            <p className="mt-1 line-clamp-2 text-[11px] text-gray-500 dark:text-gray-400">
                                {r.feedback}
                            </p>
                        )}
                    </div>
                </div>
            ))}
        </div>
    );
}
