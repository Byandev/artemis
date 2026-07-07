import PageHeader from '@/components/common/PageHeader';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import { DataTable, SortableHeader } from '@/components/ui/data-table';
import DatePicker from '@/components/ui/date-picker';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { toFrontendSort } from '@/lib/sort';
import { PaginatedData } from '@/types';
import { Page } from '@/types/models/Page';
import { PageDailyBudgetRecord } from '@/types/models/PageDailyBudgetRecord';
import { Workspace } from '@/types/models/Workspace';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import flatpickr from 'flatpickr';
import { omit } from 'lodash';
import { Edit, MoreHorizontal, Plus, Search, Trash2 } from 'lucide-react';
import moment from 'moment';
import { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';
import DateOption = flatpickr.Options.DateOption;

interface Props {
    workspace: Workspace;
    records: PaginatedData<PageDailyBudgetRecord>;
    pages: Pick<Page, 'id' | 'name'>[];
    query?: {
        sort?: string | null;
        perPage?: number | string;
        page?: number | string;
        filter?: {
            search?: string;
            page_id?: string;
            date_from?: string;
            date_to?: string;
        };
    };
}

interface PageProps {
    flash?: {
        success?: string | null;
    };
}

const Index = ({ workspace, records, pages, query }: Props) => {
    const { flash } = usePage().props as PageProps;
    const initialSorting = useMemo(
        () => toFrontendSort(query?.sort ?? null),
        [query?.sort],
    );

    const [searchValue, setSearchValue] = useState(query?.filter?.search ?? '');
    const [pageIdFilter, setPageIdFilter] = useState(
        query?.filter?.page_id ?? '',
    );
    const [dateRange, setDateRange] = useState<string[]>(() => {
        const from = query?.filter?.date_from;
        const to = query?.filter?.date_to;
        if (from && to) return [from, to];
        return [
            moment().startOf('month').format('YYYY-MM-DD'),
            moment().format('YYYY-MM-DD'),
        ];
    });

    const [showCreateDialog, setShowCreateDialog] = useState(false);
    const [editingRecord, setEditingRecord] =
        useState<PageDailyBudgetRecord | null>(null);
    const [recordToDelete, setRecordToDelete] =
        useState<PageDailyBudgetRecord | null>(null);

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
    }, [flash?.success]);

    const buildFilterParams = (overrides: Record<string, unknown> = {}) => ({
        sort: query?.sort,
        'filter[search]': searchValue || undefined,
        'filter[page_id]': pageIdFilter || undefined,
        'filter[date_from]': dateRange[0] || undefined,
        'filter[date_to]': dateRange[1] || undefined,
        page: 1,
        per_page: query?.perPage ?? records.per_page,
        ...overrides,
    });

    const navigateWithFilters = (overrides: Record<string, unknown> = {}) => {
        router.get(
            `/workspaces/${workspace.slug}/page-daily-budget-records`,
            buildFilterParams(overrides),
            { preserveState: false, replace: true, preserveScroll: true },
        );
    };

    useEffect(() => {
        const timer = setTimeout(() => {
            router.get(
                `/workspaces/${workspace.slug}/page-daily-budget-records`,
                buildFilterParams(),
                {
                    preserveState: true,
                    replace: true,
                    preserveScroll: true,
                    only: ['records'],
                },
            );
        }, 500);
        return () => clearTimeout(timer);
    }, [searchValue]);

    const handlePageIdFilterChange = (value: string) => {
        setPageIdFilter(value === 'all' ? '' : value);
        setTimeout(() => {
            router.get(
                `/workspaces/${workspace.slug}/page-daily-budget-records`,
                {
                    ...buildFilterParams(),
                    'filter[page_id]': value === 'all' ? undefined : value,
                },
                { preserveState: false, replace: true, preserveScroll: true },
            );
        }, 0);
    };

    const handleDateRangeChange = (dates: Date[]) => {
        if (dates.length === 2) {
            const range = [
                moment(dates[0]).format('YYYY-MM-DD'),
                moment(dates[1]).format('YYYY-MM-DD'),
            ];
            setDateRange(range);
            router.get(
                `/workspaces/${workspace.slug}/page-daily-budget-records`,
                {
                    ...buildFilterParams(),
                    'filter[date_from]': range[0],
                    'filter[date_to]': range[1],
                },
                { preserveState: false, replace: true, preserveScroll: true },
            );
        } else if (dates.length === 0) {
            setDateRange(['', '']);
            router.get(
                `/workspaces/${workspace.slug}/page-daily-budget-records`,
                {
                    ...buildFilterParams(),
                    'filter[date_from]': undefined,
                    'filter[date_to]': undefined,
                },
                { preserveState: false, replace: true, preserveScroll: true },
            );
        }
    };

    const columns: ColumnDef<PageDailyBudgetRecord>[] = [
        {
            accessorKey: 'page.name',
            header: 'Page',
            cell: ({ row }) => row.original.page?.name ?? '-',
        },
        {
            accessorKey: 'date',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader column={column} title="Date" />
            ),
            cell: ({ row }) => new Date(row.original.date).toLocaleDateString(),
        },
        {
            accessorKey: 'budget',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader column={column} title="Budget" />
            ),
            cell: ({ row }) =>
                parseFloat(row.original.budget).toLocaleString(undefined, {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2,
                }),
        },
        {
            id: 'actions',
            cell: ({ row }) => {
                const record = row.original;
                return (
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <button className="flex h-7 w-7 items-center justify-center rounded-lg border border-black/6 bg-stone-50 text-gray-400 transition-all hover:border-black/12 hover:bg-stone-100 hover:text-gray-600 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-500 dark:hover:border-white/12 dark:hover:bg-zinc-700 dark:hover:text-gray-300">
                                <MoreHorizontal className="h-3.5 w-3.5" />
                            </button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" className="w-36">
                            <DropdownMenuItem
                                onClick={() => setEditingRecord(record)}
                            >
                                <Edit />
                                Edit
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem
                                variant="destructive"
                                onClick={() => setRecordToDelete(record)}
                            >
                                <Trash2 />
                                Delete
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                );
            },
        },
    ];

    return (
        <AppLayout>
            <Head title={`${workspace.name} - Page Daily Budget Records`} />
            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
                <PageHeader
                    title="Page Daily Budget Records"
                    description="Track daily budgets for each page"
                >
                    <Button size="sm" onClick={() => setShowCreateDialog(true)}>
                        <Plus className="mr-1 h-3.5 w-3.5" />
                        Add Record
                    </Button>
                </PageHeader>

                <div className="mb-3 flex flex-wrap items-end gap-2">
                    <div className="relative w-full max-w-xs">
                        <Search className="pointer-events-none absolute top-1/2 left-3 h-3.5 w-3.5 -translate-y-1/2 text-gray-400 dark:text-gray-500" />
                        <input
                            className="h-9 w-full rounded-[10px] border border-black/6 bg-stone-100 pr-3 pl-8 font-mono! text-[12px]! text-gray-800 transition-all outline-none placeholder:text-gray-400 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-100 dark:placeholder:text-gray-600 dark:focus:border-emerald-400"
                            placeholder="Search page name..."
                            value={searchValue}
                            onChange={(e) => setSearchValue(e.target.value)}
                        />
                    </div>
                    <Select
                        value={pageIdFilter || 'all'}
                        onValueChange={handlePageIdFilterChange}
                    >
                        <SelectTrigger className="h-9 w-[180px]">
                            <SelectValue placeholder="All Pages" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Pages</SelectItem>
                            {pages.map((p) => (
                                <SelectItem key={p.id} value={String(p.id)}>
                                    {p.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <DatePicker
                        id="budget-records-date-range"
                        mode="range"
                        onChange={handleDateRangeChange}
                        defaultDate={dateRange as never as DateOption}
                    />
                </div>

                <div className="rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
                    <DataTable
                        columns={columns}
                        enableInternalPagination={false}
                        data={records.data || []}
                        initialSorting={initialSorting}
                        meta={{ ...omit(records, ['data']) }}
                        onFetch={(params) => {
                            router.get(
                                `/workspaces/${workspace.slug}/page-daily-budget-records`,
                                {
                                    sort: params?.sort,
                                    'filter[search]': searchValue || undefined,
                                    'filter[page_id]':
                                        pageIdFilter || undefined,
                                    'filter[date_from]':
                                        dateRange[0] || undefined,
                                    'filter[date_to]':
                                        dateRange[1] || undefined,
                                    page: params?.page ?? 1,
                                    per_page:
                                        params?.per_page ??
                                        query?.perPage ??
                                        records.per_page,
                                },
                                {
                                    preserveState: false,
                                    replace: true,
                                    preserveScroll: true,
                                },
                            );
                        }}
                    />
                </div>
            </div>

            <RecordFormDialog
                open={showCreateDialog}
                onClose={() => setShowCreateDialog(false)}
                workspace={workspace}
                pages={pages}
            />

            <RecordFormDialog
                open={!!editingRecord}
                onClose={() => setEditingRecord(null)}
                workspace={workspace}
                pages={pages}
                record={editingRecord ?? undefined}
            />

            <DeleteRecordDialog
                record={recordToDelete}
                workspace={workspace}
                onClose={() => setRecordToDelete(null)}
            />
        </AppLayout>
    );
};

function RecordFormDialog({
    open,
    onClose,
    workspace,
    pages,
    record,
}: {
    open: boolean;
    onClose: () => void;
    workspace: Workspace;
    pages: Pick<Page, 'id' | 'name'>[];
    record?: PageDailyBudgetRecord;
}) {
    const isEdit = !!record;

    const form = useForm({
        page_id: record?.page_id ? String(record.page_id) : '',
        date: record?.date ? record.date.split('T')[0] : '',
        budget: record?.budget ? String(record.budget) : '',
    });

    useEffect(() => {
        if (open) {
            form.setData({
                page_id: record?.page_id ? String(record.page_id) : '',
                date: record?.date ? record.date.split('T')[0] : '',
                budget: record?.budget ? String(record.budget) : '',
            });
            form.clearErrors();
        }
    }, [open, record]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (isEdit && record) {
            form.put(
                `/workspaces/${workspace.slug}/page-daily-budget-records/${record.id}`,
                {
                    onSuccess: () => onClose(),
                },
            );
        } else {
            form.post(
                `/workspaces/${workspace.slug}/page-daily-budget-records`,
                {
                    onSuccess: () => onClose(),
                },
            );
        }
    };

    return (
        <Dialog open={open} onOpenChange={(v) => !v && onClose()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {isEdit ? 'Edit' : 'Add'} Daily Budget Record
                    </DialogTitle>
                    <DialogDescription>
                        {isEdit
                            ? 'Update the budget record details.'
                            : 'Create a new daily budget record for a page.'}
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={handleSubmit} className="space-y-4">
                    <div className="space-y-2">
                        <Label htmlFor="page_id">Page</Label>
                        <Select
                            value={form.data.page_id}
                            onValueChange={(v) => form.setData('page_id', v)}
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Select a page" />
                            </SelectTrigger>
                            <SelectContent>
                                {pages.map((p) => (
                                    <SelectItem key={p.id} value={String(p.id)}>
                                        {p.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {form.errors.page_id && (
                            <p className="text-xs text-red-500">
                                {form.errors.page_id}
                            </p>
                        )}
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="date">Date</Label>
                        <Input
                            id="date"
                            type="date"
                            value={form.data.date}
                            onChange={(e) =>
                                form.setData('date', e.target.value)
                            }
                        />
                        {form.errors.date && (
                            <p className="text-xs text-red-500">
                                {form.errors.date}
                            </p>
                        )}
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="budget">Budget</Label>
                        <Input
                            id="budget"
                            type="number"
                            step="0.01"
                            min="0"
                            value={form.data.budget}
                            onChange={(e) =>
                                form.setData('budget', e.target.value)
                            }
                            placeholder="0.00"
                        />
                        {form.errors.budget && (
                            <p className="text-xs text-red-500">
                                {form.errors.budget}
                            </p>
                        )}
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onClose}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing
                                ? isEdit
                                    ? 'Saving...'
                                    : 'Creating...'
                                : isEdit
                                  ? 'Save'
                                  : 'Create'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function DeleteRecordDialog({
    record,
    workspace,
    onClose,
}: {
    record: PageDailyBudgetRecord | null;
    workspace: Workspace;
    onClose: () => void;
}) {
    const { delete: destroy, processing } = useForm({});

    const handleDelete = () => {
        if (!record) return;
        destroy(
            `/workspaces/${workspace.slug}/page-daily-budget-records/${record.id}`,
            {
                onSuccess: () => onClose(),
            },
        );
    };

    return (
        <AlertDialog
            open={!!record}
            onOpenChange={(open) => !open && onClose()}
        >
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>Delete Record</AlertDialogTitle>
                    <AlertDialogDescription>
                        Are you sure you want to delete this budget record for{' '}
                        <strong>{record?.page?.name}</strong> on{' '}
                        {record?.date
                            ? new Date(record.date).toLocaleDateString()
                            : ''}
                        ? This action cannot be undone.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel disabled={processing}>
                        Cancel
                    </AlertDialogCancel>
                    <AlertDialogAction
                        onClick={handleDelete}
                        disabled={processing}
                        className="bg-red-600 text-white hover:bg-red-700"
                    >
                        {processing ? 'Deleting...' : 'Delete'}
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}

export default Index;
