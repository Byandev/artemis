import { TargetChecklistDrawer } from '@/components/checklist/target-checklist-drawer';
import PageHeader from '@/components/common/PageHeader';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { DataTable, SortableHeader } from '@/components/ui/data-table';
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
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { MultiSelect } from '@/components/ui/multi-select';
import { PERMISSIONS } from '@/constants/permissions';
import { usePermission } from '@/hooks/use-permission';
import AppLayout from '@/layouts/app-layout';
import { toFrontendSort } from '@/lib/sort';
import {
    InlineOwner,
    OwnerOption,
} from '@/pages/workspaces/integrations/components/inline-owner';
import workspaces from '@/routes/workspaces';
import { PaginatedData } from '@/types';
import { Page } from '@/types/models/Page';
import { Workspace } from '@/types/models/Workspace';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import clsx from 'clsx';
import { omit } from 'lodash';
import {
    Download,
    Edit,
    ListChecks,
    MoreHorizontal,
    Search,
    Upload,
    Wallet,
} from 'lucide-react';
import {
    type ChangeEvent,
    type FormEvent,
    useEffect,
    useMemo,
    useRef,
    useState,
} from 'react';
import { toast } from 'sonner';

interface PagesProps {
    workspace: Workspace;
    pages: PaginatedData<Page>;
    users: { id: number | string; name: string }[];
    query?: {
        sort?: string | null;
        perPage?: number | string;
        page?: number | string;
        filter?: {
            search?: string;
            owner_id?: string | string[];
        };
    };
}

interface PageProps {
    flash?: {
        success?: string | null;
        error?: string | null;
    };
}

const StatusBadge = ({ status }: { status: 'active' | 'inactive' }) => {
    const isActive = status === 'active';
    return (
        <span
            className={clsx(
                'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 font-mono text-[11px] font-medium tracking-wide uppercase',
                isActive
                    ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400'
                    : 'bg-red-50 text-red-500 dark:bg-red-500/10 dark:text-red-400',
            )}
        >
            <span
                className={clsx(
                    'h-1.5 w-1.5 rounded-full',
                    isActive ? 'bg-emerald-500' : 'bg-red-400',
                )}
            />
            {isActive ? 'Active' : 'Inactive'}
        </span>
    );
};

const EnableBadge = ({ isEnabled }: { isEnabled: boolean }) => {
    return (
        <span
            className={clsx(
                'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-medium',
                isEnabled
                    ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400'
                    : 'bg-red-50 text-red-500 dark:bg-red-500/10 dark:text-red-400',
            )}
        >
            <span
                className={clsx(
                    'h-1.5 w-1.5 rounded-full',
                    isEnabled ? 'bg-emerald-500' : 'bg-red-500',
                )}
            />
            {isEnabled ? 'Enabled' : 'Disabled'}
        </span>
    );
};

const ChecklistsBadge = ({ pending }: { pending: number }) => {
    const hasPending = pending > 0;
    return (
        <span
            className={clsx(
                'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-medium',
                hasPending
                    ? 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400'
                    : 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400',
            )}
        >
            <span
                className={clsx(
                    'h-1.5 w-1.5 rounded-full',
                    hasPending ? 'bg-amber-500' : 'bg-emerald-500',
                )}
            />
            {hasPending ? `${pending} Pending` : 'Complete'}
        </span>
    );
};

const currencyFormatter = new Intl.NumberFormat(undefined, {
    style: 'currency',
    currency: 'PHP',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});

const Pages = ({ pages, workspace, users, query }: PagesProps) => {
    const { flash } = usePage().props as PageProps;
    const initialSorting = useMemo(() => {
        return toFrontendSort(query?.sort ?? null);
    }, [query?.sort]);

    const [searchValue, setSearchValue] = useState(query?.filter?.search ?? '');
    const [selectedOwners, setSelectedOwners] = useState<string[]>(() => {
        const owner = query?.filter?.owner_id;
        if (!owner) return [];
        return Array.isArray(owner) ? owner.map(String) : [String(owner)];
    });
    const ownerOptions = useMemo(
        () => users.map((u) => ({ value: String(u.id), label: u.name })),
        [users],
    );
    // Same people, shaped for the inline owner avatar/picker in the table.
    const ownerUsers = useMemo<OwnerOption[]>(
        () => users.map((u) => ({ id: Number(u.id), name: u.name })),
        [users],
    );
    const [checklistDrawerOpen, setChecklistDrawerOpen] = useState(false);
    const [selectedPage, setSelectedPage] = useState<Page | null>(null);
    const [budgetPage, setBudgetPage] = useState<Page | null>(null);

    const budgetForm = useForm({
        budget: '0',
    });
    const canCreatePages = usePermission(PERMISSIONS.CreatePages);
    const canViewPages = usePermission(PERMISSIONS.ViewPages);
    const canEditPages = usePermission(PERMISSIONS.EditPages);
    const fileInputRef = useRef<HTMLInputElement>(null);
    const [importing, setImporting] = useState(false);
    const canEditPageBudget = usePermission(
        PERMISSIONS.EditPageDailyBudgetRecords,
    );
    const canViewChecklist = usePermission(PERMISSIONS.ViewChecklist);
    const canUsePageActions =
        canViewChecklist || canEditPages || canEditPageBudget;

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }

        if (flash?.error) {
            toast.error(flash.error);
        }
    }, [flash?.success, flash?.error]);

    useEffect(() => {
        const timer = setTimeout(() => {
            const hasFilter = !!searchValue || selectedOwners.length > 0;
            router.get(
                workspaces.pages.index({ workspace }),
                {
                    sort: query?.sort,
                    'filter[search]': searchValue || undefined,
                    'filter[owner_id]': selectedOwners.length
                        ? selectedOwners
                        : undefined,
                    page: hasFilter ? 1 : (query?.page ?? 1),
                },
                {
                    preserveState: true,
                    replace: true,
                    preserveScroll: true,
                    only: ['pages'],
                },
            );
        }, 500);

        return () => clearTimeout(timer);
    }, [searchValue, selectedOwners]);

    const handleEdit = (page: Page) => {
        router.get(`/workspaces/${workspace.slug}/pages/${page.id}/edit`);
    };

    const handleExport = () => {
        window.location.href = `/workspaces/${workspace.slug}/pages/export`;
    };

    const handleImportFile = (event: ChangeEvent<HTMLInputElement>) => {
        const file = event.target.files?.[0];
        if (!file) return;

        setImporting(true);
        router.post(
            `/workspaces/${workspace.slug}/pages/import`,
            { file },
            {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: () => router.reload({ only: ['pages'] }),
                onError: () => toast.error('Failed to import pages.'),
                onFinish: () => {
                    setImporting(false);
                    if (fileInputRef.current) fileInputRef.current.value = '';
                },
            },
        );
    };


    const openChecklist = (page: Page) => {
        setSelectedPage(page);
        setChecklistDrawerOpen(true);
    };

    const openBudgetDialog = (page: Page) => {
        setBudgetPage(page);
        budgetForm.setData('budget', String(page.latest_budget?.budget ?? 0));
        budgetForm.clearErrors();
    };

    const closeBudgetDialog = () => {
        setBudgetPage(null);
        budgetForm.reset();
        budgetForm.clearErrors();
    };

    const updateBudget = (e: FormEvent) => {
        e.preventDefault();
        if (!budgetPage) return;

        budgetForm.put(
            `/workspaces/${workspace.slug}/pages/${budgetPage.id}/budget`,
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success('Page budget updated.');
                    closeBudgetDialog();
                },
                onError: () => toast.error('Failed to update page budget.'),
            },
        );
    };

    const columns: ColumnDef<Page>[] = [
        {
            accessorKey: 'name',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader column={column} title={'Name'} />
            ),
        },
        {
            accessorKey: 'shop_name',
            header: ({ column }) => (
                <SortableHeader column={column} title={'Shop'} />
            ),
            cell: ({ row }) => row.original.shop?.name || '-',
        },
        {
            accessorKey: 'owner_name',
            header: ({ column }) => (
                <SortableHeader column={column} title={'Owner'} />
            ),
            cell: ({ row }) => (
                <PageOwnerAssign
                    workspace={workspace}
                    page={row.original}
                    users={ownerUsers}
                    canEdit={canEditPages}
                />
            ),
        },
        {
            accessorKey: 'latest_budget',
            header: ({ column }) => (
                <SortableHeader
                    column={column}
                    title={'Budget'}
                    enabled={false}
                />
            ),
            cell: ({ row }) => {
                const latestBudget = row.original.latest_budget;
                return (
                    <div className="flex flex-col gap-0.5">
                        <span>
                            {currencyFormatter.format(
                                Number(latestBudget?.budget ?? 0),
                            )}
                        </span>
                        {latestBudget?.date && (
                            <span className="text-[11px] text-gray-400 dark:text-gray-500">
                                {new Date(
                                    latestBudget.date,
                                ).toLocaleDateString()}
                            </span>
                        )}
                    </div>
                );
            },
        },
        {
            accessorKey: 'deleted_at',
            header: ({ column }) => (
                <SortableHeader column={column} title={'Sync Status'} />
            ),
            cell: ({ row }) => <StatusBadge status={row.original.status} />,
        },
        {
            accessorKey: 'parcel_journey_enabled',
            header: ({ column }) => (
                <SortableHeader column={column} title={'Parcel Journey'} />
            ),
            cell: ({ row }) => {
                const isEnabled = Boolean(row.original.parcel_journey_enabled);

                return <EnableBadge isEnabled={isEnabled} />;
            },
        },
        {
            accessorKey: 'pending_required_checklists_count',
            header: ({ column }) => (
                <SortableHeader column={column} title={'Checklists'} />
            ),
            cell: ({ row }) => (
                <ChecklistsBadge
                    pending={Number(
                        row.original.pending_required_checklists_count ?? 0,
                    )}
                />
            ),
        },
        ...(canUsePageActions
            ? [
                  {
                      id: 'actions',
                      cell: ({ row }) => {
                          const page = row.original;

                          return (
                              <DropdownMenu>
                                  <DropdownMenuTrigger asChild>
                                      <button className="flex h-7 w-7 items-center justify-center rounded-lg border border-black/6 bg-stone-50 text-gray-400 transition-all hover:border-black/12 hover:bg-stone-100 hover:text-gray-600 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-500 dark:hover:border-white/12 dark:hover:bg-zinc-700 dark:hover:text-gray-300">
                                          <MoreHorizontal className="h-3.5 w-3.5" />
                                      </button>
                                  </DropdownMenuTrigger>
                                  <DropdownMenuContent
                                      align="end"
                                      className="w-44"
                                  >
                                      {canViewChecklist && (
                                          <DropdownMenuItem
                                              onClick={() =>
                                                  openChecklist(page)
                                              }
                                          >
                                              <ListChecks />
                                              View Checklist
                                          </DropdownMenuItem>
                                      )}
                                      {canEditPages && (
                                          <DropdownMenuItem
                                              onClick={() => handleEdit(page)}
                                          >
                                              <Edit />
                                              Edit
                                          </DropdownMenuItem>
                                      )}
                                      {canEditPageBudget && (
                                          <DropdownMenuItem
                                              onClick={() =>
                                                  openBudgetDialog(page)
                                              }
                                          >
                                              <Wallet />
                                              Update Budget
                                          </DropdownMenuItem>
                                      )}
                                  </DropdownMenuContent>
                              </DropdownMenu>
                          );
                      },
                  } as ColumnDef<Page>,
              ]
            : []),
    ];

    return (
        <AppLayout>
            <Head title={`${workspace.name} - Pages`} />
            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
                <PageHeader
                    title="Pages"
                    description="Manage your shop pages and their connected stores"
                    stackActionsOnMobile
                >
                    {/*{canViewPages && (*/}
                    {/*    <Button*/}
                    {/*        size="sm"*/}
                    {/*        variant="outline"*/}
                    {/*        onClick={handleExport}*/}
                    {/*    >*/}
                    {/*        <Download className="h-4 w-4" />*/}
                    {/*        Export*/}
                    {/*    </Button>*/}
                    {/*)}*/}
                    {/*{canCreatePages && (*/}
                    {/*    <>*/}
                    {/*        <input*/}
                    {/*            ref={fileInputRef}*/}
                    {/*            type="file"*/}
                    {/*            accept=".xlsx,.xls,.csv"*/}
                    {/*            className="hidden"*/}
                    {/*            onChange={handleImportFile}*/}
                    {/*        />*/}
                    {/*        <Button*/}
                    {/*            size="sm"*/}
                    {/*            variant="outline"*/}
                    {/*            disabled={importing}*/}
                    {/*            onClick={() => fileInputRef.current?.click()}*/}
                    {/*        >*/}
                    {/*            <Upload className="h-4 w-4" />*/}
                    {/*            {importing ? 'Importing…' : 'Import'}*/}
                    {/*        </Button>*/}
                    {/*    </>*/}
                    {/*)}*/}
                </PageHeader>

                <Dialog
                    open={!!budgetPage}
                    onOpenChange={(open) => {
                        if (!open) closeBudgetDialog();
                    }}
                >
                    <DialogContent>
                        <form onSubmit={updateBudget}>
                            <DialogHeader>
                                <DialogTitle>Update Budget</DialogTitle>
                                <DialogDescription>
                                    Set today&apos;s budget for{' '}
                                    {budgetPage?.name}.
                                </DialogDescription>
                            </DialogHeader>
                            <div className="space-y-2 py-4">
                                <Label htmlFor="page-budget">Budget</Label>
                                <Input
                                    id="page-budget"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    value={budgetForm.data.budget}
                                    onChange={(event) =>
                                        budgetForm.setData(
                                            'budget',
                                            event.target.value,
                                        )
                                    }
                                />
                                <InputError
                                    message={budgetForm.errors.budget}
                                />
                                {budgetPage?.latest_budget?.date && (
                                    <p className="text-[11px] text-gray-400 dark:text-gray-500">
                                        Last set on{' '}
                                        {new Date(
                                            budgetPage.latest_budget.date,
                                        ).toLocaleDateString()}
                                    </p>
                                )}
                            </div>
                            <DialogFooter>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={closeBudgetDialog}
                                >
                                    Cancel
                                </Button>
                                <Button
                                    type="submit"
                                    disabled={budgetForm.processing}
                                >
                                    {budgetForm.processing
                                        ? 'Saving...'
                                        : 'Save Budget'}
                                </Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>

                <div className="mb-3 flex flex-wrap items-center gap-2">
                    <div className="relative w-full max-w-xs">
                        <Search className="pointer-events-none absolute top-1/2 left-3 h-3.5 w-3.5 -translate-y-1/2 text-gray-400 dark:text-gray-500" />
                        <input
                            className="h-9 w-full rounded-[10px] border border-black/6 bg-stone-100 pr-3 pl-8 font-mono! text-[12px]! text-gray-800 transition-all outline-none placeholder:text-gray-400 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-100 dark:placeholder:text-gray-600 dark:focus:border-emerald-400"
                            placeholder="Search page name..."
                            value={searchValue}
                            onChange={(e) => setSearchValue(e.target.value)}
                        />
                    </div>
                    <MultiSelect
                        compact
                        options={ownerOptions}
                        selected={selectedOwners}
                        onChange={setSelectedOwners}
                        placeholder="All owners"
                        className="w-48"
                    />
                </div>

                <div className="rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
                    <DataTable
                        columns={columns}
                        enableInternalPagination={false}
                        data={pages.data || []}
                        initialSorting={initialSorting}
                        meta={{ ...omit(pages, ['data']) }}
                        onFetch={(params) => {
                            router.get(
                                workspaces.pages.index({ workspace }),
                                {
                                    sort: params?.sort,
                                    'filter[search]': searchValue || undefined,
                                    'filter[owner_id]': selectedOwners.length
                                        ? selectedOwners
                                        : undefined,
                                    page: params?.page ?? 1,
                                    per_page: params?.per_page,
                                },
                                {
                                    preserveState: true,
                                    replace: true,
                                    preserveScroll: true,
                                },
                            );
                        }}
                    />
                </div>

                {canViewChecklist && (
                    <TargetChecklistDrawer
                        open={checklistDrawerOpen}
                        onOpenChange={(open) => {
                            setChecklistDrawerOpen(open);
                            if (!open) {
                                setSelectedPage(null);
                                router.reload({ only: ['pages'] });
                            }
                        }}
                        workspace={workspace}
                        target="page"
                        targetId={selectedPage?.id ?? null}
                        targetName={selectedPage?.name ?? ''}
                    />
                )}
            </div>
        </AppLayout>
    );
};

/**
 * Inline owner picker for one page row (mirrors the interns assignee UI).
 * Optimistically sets the owner, PATCHes the server, and reverts on error.
 */
function PageOwnerAssign({
    workspace,
    page,
    users,
    canEdit,
}: {
    workspace: Workspace;
    page: Page;
    users: OwnerOption[];
    canEdit: boolean;
}) {
    const [owner, setOwner] = useState<OwnerOption | null>(
        page.owner ? { id: page.owner.id, name: page.owner.name } : null,
    );
    const [saving, setSaving] = useState(false);

    // Reconcile when server data changes (paging/sorting/reload).
    useEffect(() => {
        setOwner(
            page.owner ? { id: page.owner.id, name: page.owner.name } : null,
        );
    }, [page.owner]);

    const assign = (ownerId: number | null) => {
        const prev = owner;
        const next = ownerId
            ? (users.find((u) => u.id === ownerId) ?? null)
            : null;
        setOwner(next);
        router.patch(
            `/workspaces/${workspace.slug}/pages/${page.id}/assign-owner`,
            { owner_id: ownerId },
            {
                preserveScroll: true,
                preserveState: true,
                onStart: () => setSaving(true),
                onFinish: () => setSaving(false),
                onError: () => {
                    setOwner(prev);
                    toast.error('Failed to update owner.');
                },
            },
        );
    };

    return (
        <InlineOwner
            owner={owner}
            users={users}
            canEdit={canEdit}
            saving={saving}
            onAssign={assign}
        />
    );
}

export default Pages;
