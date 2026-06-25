import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { ArrowDown, ArrowUp } from 'lucide-react';
import { metricLabel } from '../../_shared';

/** Sort selector + direction toggle, bound to the report's `sort` string. */
export function SortControl({
    sort,
    metrics,
    onChange,
}: {
    sort: string;
    metrics: string[];
    onChange: (sort: string) => void;
}) {
    const desc = sort.startsWith('-');
    const field = desc ? sort.slice(1) : sort;
    const options = metrics.length > 0 ? metrics : ['spend'];

    return (
        <div className="flex items-center gap-1">
            <Select
                value={field}
                onValueChange={(f) => onChange(`${desc ? '-' : ''}${f}`)}
            >
                <SelectTrigger className="h-8 w-[150px] text-xs">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    {options.map((m) => (
                        <SelectItem key={m} value={m}>
                            {metricLabel(m)}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            <button
                type="button"
                title={desc ? 'Descending' : 'Ascending'}
                onClick={() => onChange(`${desc ? '' : '-'}${field}`)}
                className="rounded-md border border-black/10 p-1.5 text-gray-500 hover:text-emerald-600 dark:border-white/10 dark:text-gray-400"
            >
                {desc ? (
                    <ArrowDown className="h-3.5 w-3.5" />
                ) : (
                    <ArrowUp className="h-3.5 w-3.5" />
                )}
            </button>
        </div>
    );
}
