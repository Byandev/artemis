import { cn } from '@/lib/utils';
import { peso } from './formatters';
import type { TopCsr } from './types';

interface Props {
    csrs: TopCsr[];
    authUserId: number;
}

export default function TeamLeaderboard({ csrs, authUserId }: Props) {
    if (csrs.length === 0) {
        return (
            <div className="flex h-32 items-center justify-center text-sm text-gray-400 dark:text-gray-500">
                No CSR data for this period.
            </div>
        );
    }

    return (
        <div className="overflow-x-auto">
            <table className="w-full">
                <thead>
                    <tr className="text-left text-[10px] font-semibold tracking-wider text-gray-400 uppercase dark:text-gray-500">
                        <th className="px-5 py-3">#</th>
                        <th className="px-5 py-3">CSR Name</th>
                        <th className="px-5 py-3 text-right">Orders</th>
                        <th className="px-5 py-3 text-right">Sales</th>
                        <th className="px-5 py-3 text-right">Delivered</th>
                        <th className="px-5 py-3 text-right">Returning</th>
                        <th className="px-5 py-3 text-right">RTS Rate</th>
                    </tr>
                </thead>
                <tbody>
                    {csrs.map((csr, i) => {
                        const isMine = csr.user_id === authUserId;
                        const rts = Number(csr.rts_rate);
                        return (
                            <tr
                                key={csr.user_id}
                                className={cn(
                                    'border-t border-black/4 transition-colors dark:border-white/4',
                                    isMine
                                        ? 'bg-emerald-50/60 dark:bg-emerald-500/5'
                                        : 'hover:bg-gray-50 dark:hover:bg-zinc-800/40',
                                )}
                            >
                                <td className="px-5 py-3">
                                    <span
                                        className={cn(
                                            'inline-flex h-6 w-6 items-center justify-center rounded-full text-[11px] font-bold',
                                            i === 0
                                                ? 'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-400'
                                                : i === 1
                                                  ? 'bg-zinc-200 text-zinc-600 dark:bg-zinc-700 dark:text-zinc-300'
                                                  : i === 2
                                                    ? 'bg-orange-100 text-orange-700 dark:bg-orange-500/20 dark:text-orange-400'
                                                    : 'bg-zinc-100 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400',
                                        )}
                                    >
                                        {i + 1}
                                    </span>
                                </td>
                                <td className="px-5 py-3">
                                    <span className="text-[13px] font-medium text-gray-800 dark:text-gray-200">
                                        {csr.csr_name}
                                    </span>
                                    {isMine && (
                                        <span className="ml-1.5 rounded-full bg-emerald-100 px-1.5 py-0.5 text-[9px] font-semibold text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-400">
                                            You
                                        </span>
                                    )}
                                </td>
                                <td className="px-5 py-3 text-right font-mono text-[12px] text-gray-700 tabular-nums dark:text-gray-300">
                                    {Number(csr.total_orders).toLocaleString()}
                                </td>
                                <td className="px-5 py-3 text-right font-mono text-[12px] text-gray-700 tabular-nums dark:text-gray-300">
                                    {peso(Number(csr.total_sales))}
                                </td>
                                <td className="px-5 py-3 text-right font-mono text-[12px] text-emerald-600 tabular-nums dark:text-emerald-400">
                                    {Number(csr.delivered).toLocaleString()}
                                </td>
                                <td className="px-5 py-3 text-right font-mono text-[12px] text-orange-600 tabular-nums dark:text-orange-400">
                                    {Number(csr.returning_count).toLocaleString()}
                                </td>
                                <td className="px-5 py-3 text-right">
                                    <span
                                        className={cn(
                                            'font-mono text-[12px] font-semibold tabular-nums',
                                            rts > 15
                                                ? 'text-red-600 dark:text-red-400'
                                                : rts > 10
                                                  ? 'text-amber-600 dark:text-amber-400'
                                                  : 'text-emerald-600 dark:text-emerald-400',
                                        )}
                                    >
                                        {rts.toFixed(1)}%
                                    </span>
                                </td>
                            </tr>
                        );
                    })}
                </tbody>
            </table>
        </div>
    );
}
