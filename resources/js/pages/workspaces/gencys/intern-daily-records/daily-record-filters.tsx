import DatePicker from '@/components/ui/date-picker';
import InternMultiSelect, {
    InternOption,
} from '@/pages/workspaces/gencys/components/intern-multi-select';
import flatpickr from 'flatpickr';
import { Search } from 'lucide-react';
import moment from 'moment';
import DateOption = flatpickr.Options.DateOption;

export type { InternOption };

interface Props {
    interns: InternOption[];
    search: string;
    internIds: string[];
    dateStart: string;
    dateEnd: string;
    onSearchChange: (value: string) => void;
    onInternsApply: (ids: string[]) => void;
    onDateRangeChange: (start: string, end: string) => void;
}

export default function DailyRecordFilters({
    interns,
    search,
    internIds,
    dateStart,
    dateEnd,
    onSearchChange,
    onInternsApply,
    onDateRangeChange,
}: Props) {
    return (
        <div className="mt-4 mb-4 flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center">
            <div className="relative w-full sm:w-64">
                <Search className="pointer-events-none absolute top-1/2 left-3 h-3.5 w-3.5 -translate-y-1/2 text-gray-400 dark:text-gray-500" />
                <input
                    className="h-9 w-full rounded-[10px] border border-black/6 bg-stone-100 pr-3 pl-8 font-mono! text-[12px]! text-gray-800 transition-all outline-none placeholder:text-gray-400 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-100 dark:placeholder:text-gray-600 dark:focus:border-emerald-400"
                    placeholder="Search intern, company or username…"
                    value={search}
                    onChange={(e) => onSearchChange(e.target.value)}
                    aria-label="Search intern daily records"
                />
            </div>

            <InternMultiSelect
                interns={interns}
                selected={internIds}
                onApply={onInternsApply}
            />

            <DatePicker
                id={`daily-records-range-${dateStart}-${dateEnd}`}
                key={`${dateStart}-${dateEnd}`}
                mode="range"
                placeholder="Any date"
                defaultDate={
                    (dateStart && dateEnd
                        ? [dateStart, dateEnd]
                        : undefined) as never as DateOption
                }
                onChange={(dates) => {
                    if (dates.length === 2) {
                        onDateRangeChange(
                            moment(dates[0]).format('YYYY-MM-DD'),
                            moment(dates[1]).format('YYYY-MM-DD'),
                        );
                    } else if (dates.length === 0) {
                        onDateRangeChange('', '');
                    }
                }}
            />
        </div>
    );
}
