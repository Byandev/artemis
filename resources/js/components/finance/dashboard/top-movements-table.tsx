import { cn } from '@/lib/utils';
import { format, parseISO } from 'date-fns';
import { ArrowDownRight, ArrowUpRight } from 'lucide-react';
import { type TopMovement } from './types';
import { humanizeCategory, money } from './utils';

const fmtDate = (d: string) => {
    try {
        return format(parseISO(d), 'd MMM');
    } catch {
        return d;
    }
};

/** The largest individual cash movements in the window, in/out colour-coded. */
export default function TopMovementsTable({ data }: { data: TopMovement[] }) {
    return (
        <div className="custom-scrollbar max-w-full overflow-x-auto">
            <table className="w-full min-w-[520px] border-collapse">
                <thead>
                    <tr className="border-b border-black/6 text-left font-mono text-[10px] tracking-wider text-gray-400 uppercase dark:border-white/6">
                        <th className="py-2 pr-3 font-medium">Date</th>
                        <th className="py-2 pr-3 font-medium">Description</th>
                        <th className="py-2 pr-3 font-medium">Category</th>
                        <th className="py-2 pl-3 text-right font-medium">
                            Amount
                        </th>
                    </tr>
                </thead>
                <tbody className="divide-y divide-black/5 dark:divide-white/5">
                    {data.map((m) => {
                        const out = m.type === 'out';
                        return (
                            <tr key={m.id} className="text-[12px]">
                                <td className="py-2.5 pr-3 font-mono text-gray-500 dark:text-gray-400">
                                    {fmtDate(m.date)}
                                </td>
                                <td className="max-w-[220px] truncate py-2.5 pr-3 text-gray-700 dark:text-gray-200">
                                    {m.description}
                                </td>
                                <td className="py-2.5 pr-3 text-gray-500 dark:text-gray-400">
                                    {humanizeCategory(m.transaction_type)}
                                </td>
                                <td
                                    className={cn(
                                        'py-2.5 pl-3 text-right font-mono font-medium',
                                        out
                                            ? 'text-red-500'
                                            : 'text-emerald-600 dark:text-emerald-400',
                                    )}
                                >
                                    <span className="inline-flex items-center justify-end gap-1">
                                        {out ? (
                                            <ArrowDownRight className="h-3 w-3" />
                                        ) : (
                                            <ArrowUpRight className="h-3 w-3" />
                                        )}
                                        ₱{money(m.amount)}
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
