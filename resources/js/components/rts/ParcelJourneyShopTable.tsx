import { DataTable, SortableHeader } from '@/components/ui/data-table';
import { toFrontendSort } from '@/lib/sort';
import { PaginatedData } from '@/types';
import { ColumnDef } from '@tanstack/react-table';
import { formatDate } from 'date-fns';
import { omit } from 'lodash';
import { RotateCcw } from 'lucide-react';
import { useMemo, useState } from 'react';
import { useParcelJourneyData } from './use-parcel-journey-data';

export interface ShopStat {
    id: number;
    shop_name: string;
    parcel_journey_started: string | null;
    tracked_orders: number;
    sms_sent: number;
    chat_sent: number;
}

const DEFAULT_SORT = '-parcel_journey_started';

/**
 * The per-shop breakdown behind the stat cards. It used to ride along with the
 * Inertia page render, so every sort and page turn re-rendered the whole page —
 * templates table included — to move one table. It now fetches on its own and
 * skeletons its rows while the next page is in flight.
 */
export default function ParcelJourneyShopTable({
    workspaceSlug,
    startDate,
    endDate,
}: {
    workspaceSlug: string;
    startDate: string;
    endDate: string;
}) {
    const [sort, setSort] = useState<string>(DEFAULT_SORT);
    const [page, setPage] = useState(1);
    const [perPage, setPerPage] = useState(10);

    const { data, loading, error, refetch } = useParcelJourneyData<
        PaginatedData<ShopStat>
    >(workspaceSlug, 'shops', {
        start_date: startDate,
        end_date: endDate,
        sort,
        page,
        per_page: perPage,
    });

    const initialSorting = useMemo(() => toFrontendSort(DEFAULT_SORT), []);

    const columns = useMemo<ColumnDef<ShopStat>[]>(
        () => [
            {
                accessorKey: 'shop_name',
                header: ({ column }) => (
                    <SortableHeader column={column} title="Shop" />
                ),
                cell: ({ row }) => (
                    <div>
                        <p className="text-[12px] font-medium text-gray-800 dark:text-gray-200">
                            {row.original.shop_name}
                        </p>
                        <p className="font-mono text-[10px] text-gray-400">
                            ID: {row.original.id}
                        </p>
                    </div>
                ),
                size: 220,
            },
            {
                accessorKey: 'parcel_journey_started',
                header: ({ column }) => (
                    <SortableHeader column={column} title="Journey Started" />
                ),
                cell: ({ row }) =>
                    row.original.parcel_journey_started
                        ? formatDate(
                              new Date(row.original.parcel_journey_started),
                              'MMM dd, yyyy',
                          )
                        : '-',
            },
            {
                accessorKey: 'tracked_orders',
                header: ({ column }) => (
                    <SortableHeader column={column} title="Tracked Orders" />
                ),
                cell: ({ row }) =>
                    Number(row.original.tracked_orders).toLocaleString(),
            },
            {
                accessorKey: 'sms_sent',
                header: ({ column }) => (
                    <SortableHeader column={column} title="SMS Sent" />
                ),
                cell: ({ row }) =>
                    Number(row.original.sms_sent).toLocaleString(),
            },
            {
                accessorKey: 'chat_sent',
                header: ({ column }) => (
                    <SortableHeader column={column} title="Chat Sent" />
                ),
                cell: ({ row }) =>
                    Number(row.original.chat_sent).toLocaleString(),
            },
        ],
        [],
    );

    return (
        <div className="mb-6 rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
            <div className="flex items-center justify-between gap-3 border-b border-black/6 px-4 py-3 dark:border-white/6">
                <p className="font-mono text-[11px] font-medium tracking-wider text-gray-400 uppercase dark:text-gray-500">
                    Per Shop Analytics
                </p>
                {error && (
                    <button
                        type="button"
                        onClick={refetch}
                        className="flex cursor-pointer items-center gap-1.5 text-[11px] text-red-500 hover:underline dark:text-red-400"
                    >
                        <RotateCcw className="h-3 w-3" />
                        Failed — retry
                    </button>
                )}
            </div>
            <DataTable
                columns={columns}
                data={error ? [] : (data?.data ?? [])}
                loading={loading}
                initialSorting={initialSorting}
                meta={
                    data
                        ? omit(data, ['data'])
                        : // Keeps the pager rendered on the first load, so the
                          // table doesn't grow a footer once the rows land.
                          {
                              current_page: page,
                              last_page: page,
                              per_page: perPage,
                              total: 0,
                              links: [],
                              from: 0,
                              to: 0,
                          }
                }
                onFetch={(params) => {
                    // Clearing the sort falls back to the default rather than
                    // asking the server for an unordered page.
                    setSort(params?.sort ? String(params.sort) : DEFAULT_SORT);
                    setPage(Number(params?.page ?? 1));
                    setPerPage(Number(params?.per_page ?? perPage));
                }}
            />
        </div>
    );
}
