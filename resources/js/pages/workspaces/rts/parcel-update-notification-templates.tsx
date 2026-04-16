import AppLayout from '@/layouts/app-layout';
import PageHeader from '@/components/common/PageHeader';
import { DataTable, SortableHeader } from '@/components/ui/data-table';
import DatePicker from '@/components/ui/date-picker';
import { toFrontendSort } from '@/lib/sort';
import workspaces from '@/routes/workspaces';
import { PaginatedData } from '@/types';
import { Workspace } from '@/types/models/Workspace';
import { ParcelJourneyNotificationTemplate } from '@/types/models/ParcelJourneyNotificationTemplate';
import { Head, router } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import { formatDate } from 'date-fns';
import { omit, startCase } from 'lodash';
import moment from 'moment';
import { useState, useMemo } from 'react';
import { Button } from '@/components/ui/button';
import TemplateForm from '@/components/rts/template-form';
import { MessageSquare, TrendingUp, Package, Send } from 'lucide-react';
import flatpickr from 'flatpickr';
import DateOption = flatpickr.Options.DateOption;

interface Analytics {
    tracked_orders: number;
    sms_sent: number;
    chat_sent: number;
    total_sent: number;
}

interface PageStat {
    id: number;
    page_name: string;
    parcel_journey_started: string | null;
    tracked_orders: number;
    sms_sent: number;
    chat_sent: number;
    rts_rate: number;
}

type Props = {
    workspace: Workspace;
    templates: PaginatedData<ParcelJourneyNotificationTemplate>;
    pageStats: PaginatedData<PageStat>;
    analytics: Analytics;
    query?: {
        start_date?: string;
        end_date?: string;
        sort?: string | null;
        stats_page?: number;
        stats_per_page?: number;
    };
}

const statCards = (analytics: Analytics) => [
    {
        label: 'Tracked Orders',
        value: analytics.tracked_orders.toLocaleString(),
        icon: Package,
        iconClass: 'text-blue-500 dark:text-blue-400',
        bgClass: 'bg-blue-50 dark:bg-blue-500/10',
    },
    {
        label: 'SMS Sent',
        value: analytics.sms_sent.toLocaleString(),
        icon: Send,
        iconClass: 'text-amber-500 dark:text-amber-400',
        bgClass: 'bg-amber-50 dark:bg-amber-500/10',
    },
    {
        label: 'Chat Sent',
        value: analytics.chat_sent.toLocaleString(),
        icon: MessageSquare,
        iconClass: 'text-violet-500 dark:text-violet-400',
        bgClass: 'bg-violet-50 dark:bg-violet-500/10',
    },
    {
        label: 'Total Sent',
        value: analytics.total_sent.toLocaleString(),
        icon: TrendingUp,
        iconClass: 'text-emerald-500 dark:text-emerald-400',
        bgClass: 'bg-emerald-50 dark:bg-emerald-500/10',
    },
];

const ParcelUpdateNotificationTemplates = ({ workspace, templates, pageStats, analytics, query }: Props) => {
    const [openForm, setOpenForm] = useState(false);
    const [selected, setSelected] = useState<ParcelJourneyNotificationTemplate | undefined>(undefined);

    const [dateRange, setDateRange] = useState([
        query?.start_date ?? moment().startOf('month').format('YYYY-MM-DD'),
        query?.end_date ?? moment().endOf('month').format('YYYY-MM-DD'),
    ]);

    const statsInitialSorting = useMemo(() => toFrontendSort(query?.sort ?? null), [query?.sort]);

    const url = workspaces.rts.parcelJourneys.url(workspace.slug);

    const handleDateChange = (dates: Date[]) => {
        if (dates.length !== 2) return;
        const start = moment(dates[0]).format('YYYY-MM-DD');
        const end = moment(dates[1]).format('YYYY-MM-DD');
        setDateRange([start, end]);
        router.get(
            url,
            { start_date: start, end_date: end },
            { preserveState: true, replace: true, preserveScroll: true, only: ['analytics', 'pageStats', 'query'] },
        );
    };

    const templateColumns: ColumnDef<ParcelJourneyNotificationTemplate>[] = [
        {
            accessorKey: 'type',
            header: 'Type',
            cell: ({ row }) => (
                <span className="text-[12px] font-medium text-gray-800 dark:text-gray-200">
                    {startCase(row.original.type)}
                </span>
            ),
        },
        {
            accessorKey: 'activity',
            header: 'Activity',
            cell: ({ row }) => (
                <span className="inline-flex items-center rounded-full bg-stone-100 px-2.5 py-1 font-mono text-[11px] font-medium text-gray-600 dark:bg-zinc-800 dark:text-gray-400">
                    {startCase(row.original.activity)}
                </span>
            ),
        },
        {
            accessorKey: 'receiver',
            header: 'Receiver',
            cell: ({ row }) => (
                <span className="inline-flex items-center rounded-full bg-stone-100 px-2.5 py-1 font-mono text-[11px] font-medium text-gray-600 dark:bg-zinc-800 dark:text-gray-400">
                    {startCase(row.original.receiver)}
                </span>
            ),
        },
        {
            accessorKey: 'message',
            header: 'Message',
            cell: ({ row }) => (
                <div className="truncate max-w-3xl font-mono text-[11px] text-gray-500 dark:text-gray-400">
                    {row.original.message}
                </div>
            ),
        },
        {
            id: 'actions',
            cell: ({ row }) => (
                <div className="flex justify-end">
                    <Button
                        size="sm"
                        variant="outline"
                        onClick={() => {
                            setSelected(row.original);
                            setOpenForm(true);
                        }}
                        className="h-7 cursor-pointer font-mono! text-[11px]!"
                    >
                        Edit
                    </Button>
                </div>
            ),
        },
    ];

    const pageStatsColumns = useMemo<ColumnDef<PageStat>[]>(() => [
        {
            accessorKey: 'page_name',
            header: ({ column }) => <SortableHeader column={column} title="Page" />,
            cell: ({ row }) => (
                <div>
                    <p className="text-[12px] font-medium text-gray-800 dark:text-gray-200">{row.original.page_name}</p>
                    <p className="font-mono text-[10px] text-gray-400">ID: {row.original.id}</p>
                </div>
            ),
            size: 220,
        },
        {
            accessorKey: 'parcel_journey_started',
            header: ({ column }) => <SortableHeader column={column} title="Journey Started" />,
            cell: ({ row }) => row.original.parcel_journey_started
                ? formatDate(new Date(row.original.parcel_journey_started), 'MMM dd, yyyy')
                : '-',
        },
        {
            accessorKey: 'tracked_orders',
            header: ({ column }) => <SortableHeader column={column} title="Tracked Orders" />,
            cell: ({ row }) => Number(row.original.tracked_orders).toLocaleString(),
        },
        {
            accessorKey: 'sms_sent',
            header: ({ column }) => <SortableHeader column={column} title="SMS Sent" />,
            cell: ({ row }) => Number(row.original.sms_sent).toLocaleString(),
        },
        {
            accessorKey: 'chat_sent',
            header: ({ column }) => <SortableHeader column={column} title="Chat Sent" />,
            cell: ({ row }) => Number(row.original.chat_sent).toLocaleString(),
        },
        {
            accessorKey: 'rts_rate',
            header: ({ column }) => <SortableHeader column={column} title="RTS Rate" />,
            cell: ({ row }) => `${Number(row.original.rts_rate).toFixed(2)}%`,
        },
    ], []);

    return (
        <AppLayout>
            <Head title={`${workspace.name} — Parcel Journey Templates`} />
            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
                <PageHeader
                    title="Parcel Journey Templates"
                    description={`${formatDate(new Date(dateRange[0]), 'MMM d')} – ${formatDate(new Date(dateRange[1]), 'MMM d, yyyy')}`}
                >
                    <DatePicker
                        id="parcel-journey-date-range"
                        mode="range"
                        onChange={handleDateChange}
                        defaultDate={dateRange as never as DateOption}
                    />
                </PageHeader>

                <div className="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
                    {statCards(analytics).map((card) => {
                        const Icon = card.icon;
                        return (
                            <div
                                key={card.label}
                                className="rounded-[14px] border border-black/6 bg-white p-4 dark:border-white/6 dark:bg-zinc-900"
                            >
                                <div className="flex items-start justify-between gap-3">
                                    <div>
                                        <p className="font-mono text-[10px] font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500">
                                            {card.label}
                                        </p>
                                        <p className="mt-1.5 text-[22px] font-semibold tracking-tight text-gray-800 dark:text-gray-100">
                                            {card.value}
                                        </p>
                                    </div>
                                    <div className={`rounded-[10px] p-2 ${card.bgClass}`}>
                                        <Icon className={`h-4 w-4 ${card.iconClass}`} />
                                    </div>
                                </div>
                            </div>
                        );
                    })}
                </div>

                <div className="mb-6 rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
                    <div className="border-b border-black/6 px-4 py-3 dark:border-white/6">
                        <p className="font-mono text-[11px] font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500">
                            Per Page Analytics
                        </p>
                    </div>
                    <DataTable
                        columns={pageStatsColumns}
                        data={pageStats.data ?? []}
                        initialSorting={statsInitialSorting}
                        meta={omit(pageStats, ['data'])}
                        onFetch={(params) =>
                            router.get(
                                url,
                                {
                                    sort: params?.sort,
                                    stats_page: params?.page ?? 1,
                                    per_page_stats: params?.per_page ?? query?.stats_per_page ?? pageStats.per_page,
                                    start_date: dateRange[0],
                                    end_date: dateRange[1],
                                },
                                { preserveState: true, replace: true, preserveScroll: true, only: ['pageStats', 'query'] },
                            )
                        }
                    />
                </div>

                <div className="rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
                    <DataTable
                        columns={templateColumns}
                        data={templates.data || []}
                        enableInternalPagination={false}
                        meta={{ ...omit(templates, ['data']) }}
                        onFetch={(params) => {
                            router.get(
                                url,
                                { page: params?.page ?? 1, per_page: params?.per_page, start_date: dateRange[0], end_date: dateRange[1] },
                                { preserveState: true, replace: true, preserveScroll: true, only: ['templates'] },
                            );
                        }}
                    />
                </div>

                <TemplateForm
                    open={openForm}
                    onOpenChange={(open) => {
                        setOpenForm(open);
                        if (!open) setSelected(undefined);
                    }}
                    workspace={workspace}
                    initialValue={selected}
                />
            </div>
        </AppLayout>
    );
};

export default ParcelUpdateNotificationTemplates;
