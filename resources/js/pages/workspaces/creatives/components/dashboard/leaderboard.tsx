import { InitialAvatar } from '../atoms';
import { LeaderRow } from './types';

interface Props {
    rows: LeaderRow[];
    currentUserId: number;
}

const COLS = 'grid grid-cols-[1.5rem_1fr_3rem_3rem_3rem] items-center gap-2';

/** Per-editor scorecard; the signed-in editor's row is highlighted. */
export default function Leaderboard({ rows, currentUserId }: Props) {
    if (rows.length === 0) {
        return (
            <p className="py-6 text-center text-[12px] text-gray-400 dark:text-gray-500">
                No data for this period.
            </p>
        );
    }

    return (
        <div className="space-y-1">
            <div
                className={`${COLS} px-2 pb-1 font-mono text-[10px] tracking-wide text-gray-400 uppercase dark:text-gray-500`}
            >
                <span>#</span>
                <span>Editor</span>
                <span className="text-right">Made</span>
                <span className="text-right">Appr.</span>
                <span className="text-right">Scale</span>
            </div>
            {rows.map((r, i) => {
                const isMe = r.creator_id === currentUserId;
                return (
                    <div
                        key={r.creator_id}
                        className={`${COLS} rounded-xl px-2 py-1.5 ${
                            isMe
                                ? 'bg-emerald-50 dark:bg-emerald-500/10'
                                : 'hover:bg-stone-50 dark:hover:bg-zinc-800/50'
                        }`}
                    >
                        <span className="font-mono text-[11px] font-semibold text-gray-400">
                            {i + 1}
                        </span>
                        <span className="flex min-w-0 items-center gap-2">
                            <InitialAvatar
                                name={r.name}
                                className="h-6 w-6 bg-stone-200 text-gray-600 dark:bg-zinc-700 dark:text-gray-300"
                            />
                            <span className="truncate text-[12px] font-medium text-gray-700 dark:text-gray-200">
                                {r.name}
                                {isMe && (
                                    <span className="ml-1 font-mono text-[10px] text-emerald-500">
                                        you
                                    </span>
                                )}
                            </span>
                        </span>
                        <span className="text-right font-mono text-[12px] text-gray-700 tabular-nums dark:text-gray-200">
                            {r.total}
                        </span>
                        <span className="text-right font-mono text-[12px] text-emerald-600 tabular-nums dark:text-emerald-400">
                            {r.approval_rate}%
                        </span>
                        <span className="text-right font-mono text-[12px] text-orange-500 tabular-nums">
                            {r.scaled}
                        </span>
                    </div>
                );
            })}
        </div>
    );
}
