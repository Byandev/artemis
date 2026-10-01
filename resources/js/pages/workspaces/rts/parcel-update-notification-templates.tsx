import PageHeader from '@/components/common/PageHeader';
import ParcelJourneyShopTable from '@/components/rts/ParcelJourneyShopTable';
import ParcelJourneyStatCards from '@/components/rts/ParcelJourneyStatCards';
import TemplateForm from '@/components/rts/template-form';
import { Button } from '@/components/ui/button';
import { DataTable } from '@/components/ui/data-table';
import DatePicker from '@/components/ui/date-picker';
import { PERMISSIONS } from '@/constants/permissions';
import { usePermission } from '@/hooks/use-permission';
import AppLayout from '@/layouts/app-layout';
import workspaces from '@/routes/workspaces';
import { PaginatedData } from '@/types';
import { ParcelJourneyNotificationTemplate } from '@/types/models/ParcelJourneyNotificationTemplate';
import { Workspace } from '@/types/models/Workspace';
import { Head, router } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import { formatDate } from 'date-fns';
import flatpickr from 'flatpickr';
import { omit, startCase } from 'lodash';
import moment from 'moment';
import { useMemo, useState } from 'react';
import DateOption = flatpickr.Options.DateOption;

type Props = {
    workspace: Workspace;
    templates: PaginatedData<ParcelJourneyNotificationTemplate>;
    query?: {
        start_date?: string;
        end_date?: string;
    };
};

const ParcelUpdateNotificationTemplates = ({
    workspace,
    templates,
    query,
}: Props) => {
    const [openForm, setOpenForm] = useState(false);
    const [selected, setSelected] = useState<
        ParcelJourneyNotificationTemplate | undefined
    >(undefined);
    const canManageTemplates = usePermission(
        PERMISSIONS.ManageParcelJourneyTemplates,
    );

    const [dateRange, setDateRange] = useState([
        query?.start_date ?? moment().startOf('month').format('YYYY-MM-DD'),
        query?.end_date ?? moment().endOf('month').format('YYYY-MM-DD'),
    ]);

    const url = workspaces.rts.parcelJourneys.url(workspace.slug);

    const handleDateChange = (dates: Date[]) => {
        if (dates.length !== 2) return;
        const start = moment(dates[0]).format('YYYY-MM-DD');
        const end = moment(dates[1]).format('YYYY-MM-DD');
        setDateRange([start, end]);
        // Nothing on the page render depends on the range any more — the cards
        // and the shop table refetch off `dateRange` — so this only puts the
        // window in the URL, for reloads and shared links.
        router.get(
            url,
            { start_date: start, end_date: end },
            {
                preserveState: true,
                replace: true,
                preserveScroll: true,
                only: ['query'],
            },
        );
    };

    const templateColumns = useMemo<
        ColumnDef<ParcelJourneyNotificationTemplate>[]
    >(
        () =>
            [
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
                        <div className="max-w-3xl truncate font-mono text-[11px] text-gray-500 dark:text-gray-400">
                            {row.original.message}
                        </div>
                    ),
                },
                {
                    accessorKey: 'is_enabled',
                    header: 'Status',
                    cell: ({ row }) =>
                        row.original.is_enabled ? (
                            <span className="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 font-mono text-[10px] font-medium text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400">
                                Enabled
                            </span>
                        ) : (
                            <span className="inline-flex items-center rounded-full bg-stone-100 px-2 py-0.5 font-mono text-[10px] font-medium text-gray-500 dark:bg-zinc-800 dark:text-gray-400">
                                Disabled
                            </span>
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
            ].filter((column) => canManageTemplates || column.id !== 'actions'),
        [canManageTemplates],
    );

    return (
        <AppLayout>
            <Head title={`${workspace.name} — Parcel Journey Templates`} />
            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
                <PageHeader
                    title="Parcel Journey Templates"
                    description={`${formatDate(new Date(dateRange[0]), 'MMM d')} – ${formatDate(new Date(dateRange[1]), 'MMM d, yyyy')}`}
                    stackActionsOnMobile
                >
                    <DatePicker
                        id="parcel-journey-date-range"
                        mode="range"
                        onChange={handleDateChange}
                        defaultDate={dateRange as never as DateOption}
                    />
                </PageHeader>

                <ParcelJourneyStatCards
                    workspaceSlug={workspace.slug}
                    startDate={dateRange[0]}
                    endDate={dateRange[1]}
                />

                <ParcelJourneyShopTable
                    workspaceSlug={workspace.slug}
                    startDate={dateRange[0]}
                    endDate={dateRange[1]}
                />

                <div className="rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
                    <DataTable
                        columns={templateColumns}
                        data={templates.data || []}
                        enableInternalPagination={false}
                        meta={{ ...omit(templates, ['data']) }}
                        onFetch={(params) => {
                            router.get(
                                url,
                                {
                                    page: params?.page ?? 1,
                                    per_page: params?.per_page,
                                    start_date: dateRange[0],
                                    end_date: dateRange[1],
                                },
                                {
                                    preserveState: true,
                                    replace: true,
                                    preserveScroll: true,
                                    only: ['templates'],
                                },
                            );
                        }}
                    />
                </div>

                {canManageTemplates && (
                    <TemplateForm
                        open={openForm}
                        onOpenChange={(open) => {
                            setOpenForm(open);
                            if (!open) setSelected(undefined);
                        }}
                        workspace={workspace}
                        initialValue={selected}
                    />
                )}
            </div>
        </AppLayout>
    );
};

export default ParcelUpdateNotificationTemplates;
