import PageHeader from '@/components/common/PageHeader';
import {
    BatchStatusPill,
    ProgressBar,
    SyncBatch,
    describeBatchWindows,
    formatRelative,
    isBatchActive,
    summariseTypes,
} from '@/components/gencys/sync-batch-ui';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { DataTable, SortableHeader } from '@/components/ui/data-table';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
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
import { Workspace } from '@/types/models/Workspace';
import { Head, router, useForm } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import clsx from 'clsx';
import { omit } from 'lodash';
import { Ban, Layers, ListChecks, Plus } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

/** One tickable sync type. */
interface SyncTypeOption {
    value: string;
    label: string;
}

interface Props {
    workspace: Workspace;
    batches: PaginatedData<SyncBatch>;
    syncTypes: SyncTypeOption[];
    queuedCount: number;
    running: SyncBatch | null;
    itemCount: number;
    query?: {
        sort?: string | null;
        perPage?: number | string;
        filter?: { status?: string; sync_type?: string; source?: string };
    };
}

/** How often the page re-reads itself while something is still moving. */
const POLL_MS = 8000;

export default function SyncBatchesIndex({
    workspace,
    batches,
    syncTypes,
    queuedCount,
    running,
    itemCount,
    query,
}: Props) {
    const indexUrl = `/workspaces/${workspace.slug}/gencys/sync-batches`;

    const initialSorting = useMemo(
        () => toFrontendSort(query?.sort ?? null),
        [query?.sort],
    );

    const statusFilter = query?.filter?.status ?? '';
    const typeFilter = query?.filter?.sync_type ?? '';

    const navigate = (overrides: Record<string, unknown> = {}) => {
        router.get(
            indexUrl,
            {
                sort: query?.sort,
                'filter[status]': statusFilter || undefined,
                'filter[sync_type]': typeFilter || undefined,
                page: batches.current_page,
                per_page: batches.per_page,
                ...overrides,
            },
            { preserveState: true, replace: true, preserveScroll: true },
        );
    };

    // A queued or running batch changes without anyone touching the page, so
    // keep re-reading while that's true and stop once everything has settled.
    const hasActive =
        !!running || batches.data.some((b) => isBatchActive(b.status));

    useEffect(() => {
        if (!hasActive) return;

        const timer = setInterval(
            () =>
                router.reload({
                    only: ['batches', 'running', 'queuedCount'],
                }),
            POLL_MS,
        );

        return () => clearInterval(timer);
    }, [hasActive]);

    const cancelBatch = (batch: SyncBatch) => {
        router.post(
            `${indexUrl}/${batch.id}/cancel`,
            {},
            { preserveScroll: true },
        );
    };

    const columns: ColumnDef<SyncBatch>[] = [
        {
            accessorKey: 'id',
            header: ({ column }) => (
                <SortableHeader column={column} title="Batch" />
            ),
            cell: ({ row }) => (
                <a
                    href={`${indexUrl}/${row.original.id}`}
                    className="font-mono text-[12px] font-medium text-gray-700 hover:underline dark:text-gray-200"
                >
                    #{row.original.id}
                </a>
            ),
        },
        {
            id: 'sync_types',
            header: () => (
                <span className="font-mono text-[10px] tracking-wider uppercase">
                    Covers
                </span>
            ),
            cell: ({ row }) => (
                <div
                    className="flex flex-col gap-0.5"
                    title={describeBatchWindows(row.original)
                        .map((w) => `${w.label}: ${w.window}`)
                        .join('\n')}
                >
                    <span className="text-[12px] text-gray-700 dark:text-gray-200">
                        {summariseTypes(row.original)}
                    </span>
                    <span className="font-mono text-[10px] text-gray-400 dark:text-gray-500">
                        {row.original.sync_types.length} sync type
                        {row.original.sync_types.length === 1 ? '' : 's'}
                    </span>
                </div>
            ),
        },
        {
            accessorKey: 'status',
            header: ({ column }) => (
                <SortableHeader column={column} title="Status" />
            ),
            cell: ({ row }) => <BatchStatusPill status={row.original.status} />,
        },
        {
            id: 'progress',
            header: () => (
                <span className="font-mono text-[10px] tracking-wider uppercase">
                    Progress
                </span>
            ),
            cell: ({ row }) => (
                <ProgressBar batch={row.original} className="w-40" />
            ),
        },
        {
            id: 'outcome',
            header: () => (
                <span className="font-mono text-[10px] tracking-wider uppercase">
                    Outcome
                </span>
            ),
            cell: ({ row }) => (
                <div className="flex items-center gap-3 font-mono text-[11px]">
                    <span className="text-emerald-600 dark:text-emerald-400">
                        {row.original.succeeded_runs} ok
                    </span>
                    {row.original.failed_runs > 0 && (
                        <span className="text-red-500 dark:text-red-400">
                            {row.original.failed_runs} failed
                        </span>
                    )}
                </div>
            ),
        },
        {
            accessorKey: 'source',
            header: () => (
                <span className="font-mono text-[10px] tracking-wider uppercase">
                    Raised by
                </span>
            ),
            cell: ({ row }) => (
                <span className="font-mono text-[11px] text-gray-500 dark:text-gray-400">
                    {row.original.source === 'manual'
                        ? (row.original.created_by ?? 'manual')
                        : 'schedule'}
                </span>
            ),
        },
        {
            accessorKey: 'queued_at',
            header: ({ column }) => (
                <SortableHeader column={column} title="Queued" />
            ),
            cell: ({ row }) => (
                <span className="font-mono text-[11px] text-gray-400 dark:text-gray-500">
                    {formatRelative(row.original.queued_at)}
                </span>
            ),
        },
        {
            id: 'actions',
            header: () => null,
            cell: ({ row }) =>
                isBatchActive(row.original.status) ? (
                    <Button
                        variant="ghost"
                        size="sm"
                        className="h-7 text-[11px] text-red-500 hover:text-red-600"
                        onClick={() => cancelBatch(row.original)}
                    >
                        <Ban className="mr-1 h-3 w-3" />
                        Cancel
                    </Button>
                ) : null,
        },
    ];

    return (
        <AppLayout>
            <Head title="Sync Batches" />

            <div className="px-6 py-6">
                <PageHeader
                    title="Sync Batches"
                    description="The Gencys ERP sync queue. One batch holds the ERP at a time and sends its runs in groups, waiting for each group to report back before sending the next."
                >
                    <NewBatchDialog
                        indexUrl={indexUrl}
                        syncTypes={syncTypes}
                        itemCount={itemCount}
                    />
                </PageHeader>

                <div className="mb-6 grid gap-4 sm:grid-cols-2">
                    <RunningCard batch={running} onCancel={cancelBatch} />

                    <div className="rounded-[14px] border border-black/6 bg-white p-5 dark:border-white/6 dark:bg-zinc-900">
                        <div className="flex items-start justify-between">
                            <div>
                                <p className="font-mono text-[10px] font-medium tracking-wider text-gray-400 uppercase dark:text-gray-500">
                                    Waiting their turn
                                </p>
                                <p className="mt-2 text-2xl font-semibold tracking-tight text-gray-700 dark:text-gray-200">
                                    {queuedCount}
                                </p>
                                <p className="mt-1 text-[11px] text-gray-400 dark:text-gray-500">
                                    Queued batches start automatically as the
                                    one ahead finishes.
                                </p>
                            </div>
                            <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-stone-100 text-stone-500 dark:bg-zinc-800 dark:text-zinc-400">
                                <Layers className="h-4 w-4" />
                            </div>
                        </div>
                    </div>
                </div>

                <div className="mb-3 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <h2 className="font-mono text-[10px] font-medium tracking-[0.08em] text-gray-400 uppercase dark:text-gray-500">
                        All batches
                    </h2>
                    <div className="flex items-center gap-2">
                        <Select
                            value={typeFilter || 'all'}
                            onValueChange={(v) =>
                                navigate({
                                    'filter[sync_type]':
                                        v === 'all' ? undefined : v,
                                    page: 1,
                                })
                            }
                        >
                            <SelectTrigger className="h-9 w-[180px] font-mono! text-[11px]!">
                                <SelectValue placeholder="All syncs" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All syncs</SelectItem>
                                {syncTypes.map((t) => (
                                    <SelectItem key={t.value} value={t.value}>
                                        {t.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>

                        <Select
                            value={statusFilter || 'all'}
                            onValueChange={(v) =>
                                navigate({
                                    'filter[status]':
                                        v === 'all' ? undefined : v,
                                    page: 1,
                                })
                            }
                        >
                            <SelectTrigger className="h-9 w-[180px] font-mono! text-[11px]!">
                                <SelectValue placeholder="All statuses" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">
                                    All statuses
                                </SelectItem>
                                <SelectItem value="queued">Queued</SelectItem>
                                <SelectItem value="running">Running</SelectItem>
                                <SelectItem value="completed">
                                    Completed
                                </SelectItem>
                                <SelectItem value="completed_with_failures">
                                    With failures
                                </SelectItem>
                                <SelectItem value="cancelled">
                                    Cancelled
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                <div className="rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
                    <DataTable
                        columns={columns}
                        data={batches.data || []}
                        meta={{ ...omit(batches, ['data']) }}
                        initialSorting={initialSorting}
                        onFetch={(params) =>
                            navigate({
                                page: params?.page ?? 1,
                                per_page: params?.per_page ?? batches.per_page,
                                sort: params?.sort ?? query?.sort,
                            })
                        }
                    />
                </div>
            </div>
        </AppLayout>
    );
}

function RunningCard({
    batch,
    onCancel,
}: {
    batch: SyncBatch | null;
    onCancel: (batch: SyncBatch) => void;
}) {
    if (!batch) {
        return (
            <div className="rounded-[14px] border border-dashed border-black/8 bg-stone-50/60 p-5 dark:border-white/8 dark:bg-zinc-900/40">
                <p className="font-mono text-[10px] font-medium tracking-wider text-gray-400 uppercase dark:text-gray-500">
                    Holding the ERP
                </p>
                <p className="mt-2 text-[13px] text-gray-400 dark:text-gray-500">
                    Nothing running. The ERP is idle.
                </p>
            </div>
        );
    }

    return (
        <div className="rounded-[14px] border border-blue-200/70 bg-blue-50/40 p-5 dark:border-blue-500/20 dark:bg-blue-500/5">
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                    <p className="font-mono text-[10px] font-medium tracking-wider text-gray-400 uppercase dark:text-gray-500">
                        Holding the ERP
                    </p>
                    <p className="mt-1.5 flex items-center gap-2 text-[13px] font-medium text-gray-800 dark:text-gray-100">
                        <span>
                            #{batch.id} · {summariseTypes(batch, 3)}
                        </span>
                        <BatchStatusPill status={batch.status} />
                    </p>
                    <p className="mt-0.5 font-mono text-[10px] text-gray-400 dark:text-gray-500">
                        {describeBatchWindows(batch)
                            .map((w) => `${w.label} ${w.window}`)
                            .join(' · ')}
                    </p>
                    <p className="mt-0.5 font-mono text-[10px] text-gray-400 dark:text-gray-500">
                        started {formatRelative(batch.started_at)}
                    </p>
                </div>
                <Button
                    variant="ghost"
                    size="sm"
                    className="h-7 shrink-0 text-[11px] text-red-500 hover:text-red-600"
                    onClick={() => onCancel(batch)}
                >
                    <Ban className="mr-1 h-3 w-3" />
                    Cancel
                </Button>
            </div>
            <ProgressBar batch={batch} className="mt-3" />
        </div>
    );
}

function NewBatchDialog({
    indexUrl,
    syncTypes,
    itemCount,
}: {
    indexUrl: string;
    syncTypes: SyncTypeOption[];
    itemCount: number;
}) {
    const [open, setOpen] = useState(false);

    const today = new Date().toISOString().slice(0, 10);
    const yesterday = new Date(Date.now() - 86400000)
        .toISOString()
        .slice(0, 10);

    const form = useForm({
        sync_types: syncTypes.map((t) => t.value),
        start_date: yesterday,
        end_date: today,
    });

    const toggleType = (value: string) => {
        form.setData(
            'sync_types',
            form.data.sync_types.includes(value)
                ? form.data.sync_types.filter((t) => t !== value)
                : [...form.data.sync_types, value],
        );
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post(indexUrl, {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm" className="h-9 text-[12px]">
                    <Plus className="mr-1 h-3.5 w-3.5" />
                    New batch
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-[440px]">
                <form onSubmit={submit}>
                    <DialogHeader>
                        <DialogTitle className="text-[15px]">
                            Queue a sync batch
                        </DialogTitle>
                        <DialogDescription className="text-[12px]">
                            One batch covering everything you tick, run in the
                            order shown. It joins the back of the queue and
                            starts when the batch ahead of it finishes. Covers
                            this workspace only.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="mt-4 flex flex-col gap-4">
                        <div className="flex flex-col gap-2">
                            <Label className="text-[11px]">Sync types</Label>
                            <div className="flex flex-col gap-1.5 rounded-[10px] border border-black/6 p-3 dark:border-white/6">
                                {syncTypes.map((t) => (
                                    <label
                                        key={t.value}
                                        className="flex cursor-pointer items-center gap-2.5 text-[12px] text-gray-700 dark:text-gray-200"
                                    >
                                        <Checkbox
                                            checked={form.data.sync_types.includes(
                                                t.value,
                                            )}
                                            onCheckedChange={() =>
                                                toggleType(t.value)
                                            }
                                        />
                                        {t.label}
                                    </label>
                                ))}
                            </div>
                            {form.errors.sync_types && (
                                <p className="text-[11px] text-red-500">
                                    {form.errors.sync_types}
                                </p>
                            )}
                        </div>

                        <div className="grid grid-cols-2 gap-3">
                            <div className="flex flex-col gap-1.5">
                                <Label className="text-[11px]">From</Label>
                                <Input
                                    type="date"
                                    value={form.data.start_date}
                                    onChange={(e) =>
                                        form.setData(
                                            'start_date',
                                            e.target.value,
                                        )
                                    }
                                    className="h-9 text-[12px]!"
                                />
                            </div>
                            <div className="flex flex-col gap-1.5">
                                <Label className="text-[11px]">To</Label>
                                <Input
                                    type="date"
                                    value={form.data.end_date}
                                    onChange={(e) =>
                                        form.setData('end_date', e.target.value)
                                    }
                                    className="h-9 text-[12px]!"
                                />
                            </div>
                        </div>
                        {form.errors.end_date && (
                            <p className="-mt-2 text-[11px] text-red-500">
                                {form.errors.end_date}
                            </p>
                        )}

                        <p
                            className={clsx(
                                'flex items-center gap-1.5 rounded-[10px] bg-stone-50 px-3 py-2 font-mono text-[10px] text-gray-500',
                                'dark:bg-zinc-800/60 dark:text-gray-400',
                            )}
                        >
                            <ListChecks className="h-3 w-3 shrink-0" />
                            {itemCount} active item
                            {itemCount === 1 ? '' : 's'} in this workspace
                        </p>
                    </div>

                    <DialogFooter className="mt-5">
                        <Button
                            type="submit"
                            size="sm"
                            disabled={form.processing}
                            className="h-9 text-[12px]"
                        >
                            {form.processing ? 'Queueing…' : 'Queue batch'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
