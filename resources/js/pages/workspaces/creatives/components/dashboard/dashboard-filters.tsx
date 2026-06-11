import DatePicker from '@/components/ui/date-picker';
import { Link } from '@inertiajs/react';
import flatpickr from 'flatpickr';
import { Table2 } from 'lucide-react';
import moment from 'moment';
import CreativeDashboardFilters from './creative-dashboard-filters';
import {
    ApplyFilter,
    DashboardFilters,
    EditorOption,
    ProductOption,
} from './types';
import DateOption = flatpickr.Options.DateOption;

interface Props {
    filters: DashboardFilters;
    products: ProductOption[];
    editors: EditorOption[];
    currentUserId: number;
    onChange: ApplyFilter;
    listUrl: string;
}

/** Header filter row: a Filters popover + date range + link to the full list. */
export default function DashboardFiltersBar({
    filters,
    products,
    editors,
    currentUserId,
    onChange,
    listUrl,
}: Props) {
    return (
        <>
            <CreativeDashboardFilters
                value={{
                    user_ids: filters.user_ids,
                    formats: filters.formats,
                    product_ids: filters.product_ids,
                }}
                editors={editors}
                products={products}
                defaultUserId={String(currentUserId)}
                onApply={(value) => onChange(value)}
            />
            <DatePicker
                id="creatives-date-range"
                mode="range"
                onChange={(dates) => {
                    if (dates.length === 2) {
                        onChange({
                            date_from: moment(dates[0]).format('YYYY-MM-DD'),
                            date_to: moment(dates[1]).format('YYYY-MM-DD'),
                        });
                    }
                }}
                defaultDate={
                    [filters.date_from, filters.date_to] as never as DateOption
                }
            />
            <Link
                href={listUrl}
                className="inline-flex h-9 shrink-0 items-center gap-1.5 rounded-[10px] border border-black/8 bg-white px-3 text-[12px] font-medium text-gray-600 shadow-[0_1px_3px_rgba(0,0,0,0.06),0_1px_2px_rgba(0,0,0,0.04)] transition-colors hover:border-black/14 hover:text-gray-800 dark:border-white/8 dark:bg-zinc-900 dark:text-gray-300 dark:shadow-none dark:hover:border-white/14 dark:hover:text-gray-100"
            >
                <Table2 className="h-3.5 w-3.5" /> All Creatives
            </Link>
        </>
    );
}
