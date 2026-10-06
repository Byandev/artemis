import { type PreviewAttempt, PREVIEW_FORMAT_LABELS } from '../types';

const RESULT_LABELS: Record<PreviewAttempt['result'], string> = {
    rendered: 'Shown',
    story_unavailable: 'Story Unavailable',
    no_iframe: 'No preview',
};

const RESULT_TONE: Record<PreviewAttempt['result'], string> = {
    rendered: 'text-emerald-600 dark:text-emerald-400',
    story_unavailable: 'text-amber-600 dark:text-amber-400',
    no_iframe: 'text-gray-400 dark:text-gray-500',
};

/**
 * Which Meta user's access each preview request went through, and what Meta
 * answered. Meta renders a preview with the permissions of whoever signs the
 * request, so this is what tells you whose page role a "Story Unavailable"
 * came from.
 */
export function PreviewAttempts({
    attempts,
}: {
    attempts: PreviewAttempt[] | undefined;
}) {
    if (!attempts?.length) return null;

    return (
        <>
            <p className="mt-5 mb-1 text-[10px] font-medium tracking-wider text-gray-400 uppercase dark:text-gray-500">
                Preview tried as
            </p>
            <ol className="divide-y divide-black/4 dark:divide-white/4">
                {attempts.map((attempt, i) => (
                    <li
                        key={i}
                        className="grid grid-cols-[1fr_auto] gap-2 py-1.5"
                    >
                        <div className="min-w-0">
                            <p className="truncate text-[12px] text-gray-700 dark:text-gray-200">
                                {attempt.user_name}
                            </p>
                            <p className="font-mono text-[10px] text-gray-400 dark:text-gray-500">
                                {attempt.user_id ?? 'System user'} ·{' '}
                                {PREVIEW_FORMAT_LABELS[attempt.format] ??
                                    attempt.format}
                                {attempt.cached && ' · remembered'}
                            </p>
                        </div>
                        <span
                            className={`text-[11px] font-medium whitespace-nowrap ${RESULT_TONE[attempt.result]}`}
                        >
                            {RESULT_LABELS[attempt.result]}
                        </span>
                    </li>
                ))}
            </ol>
        </>
    );
}
