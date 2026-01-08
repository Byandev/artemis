import ComponentCard from '@/components/common/ComponentCard';
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
import AppLayout from '@/layouts/app-layout';
import { toFrontendSort } from '@/lib/sort';
import { PaginatedData } from '@/types';
import { OptimizationRule, OptimizationRuleCondition } from '@/types/models/OptimizationRule';
import { Workspace } from '@/types/models/Workspace';
import { Head, router } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import axios from 'axios';
import clsx from 'clsx';
import { omit } from 'lodash';
import { Edit2, Trash2 } from 'lucide-react';
import moment from 'moment';
import { useEffect, useMemo, useState } from 'react';
import { type DateRange } from 'react-day-picker';
import { OptimizationRuleDialog } from './OptimizationRuleDialog';
import AdsManagerLayout from './partials/Layout';
import OptimizationRulesFilters from './partials/OptimizationRulesFilters';

type OptimizationRuleStatus = {
    label: string;
    className: string;
};

const OPTIMIZATION_RULE_STATUS: Record<string, OptimizationRuleStatus> = {
    active: {
        label: 'ACTIVE',
        className: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
    },
    paused: {
        label: 'PAUSED',
        className: 'bg-amber-50 text-amber-800 ring-amber-200',
    },
};

const StatusBadge = ({ status }: { status: string }) => {
    const item =
        OPTIMIZATION_RULE_STATUS[status] ??
        { label: `UNKNOWN (${status})`, className: 'bg-red-50 text-red-700 ring-red-200' };

    return (
        <span
            className={clsx(
                'inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-bold tracking-wide ring-1 ring-inset',
                item.className
            )}
        >
            {item.label}
        </span>
    );
};

interface PageProps {
    workspace: Workspace;
    rules: PaginatedData<OptimizationRule>;
    query?: {
        sort?: string | null;
        perPage?: number | string;
        page?: number | string;
        filter?: {
            search?: string;
            status?: string;
        };
        start_date?: string;
        end_date?: string;
    };
}

const OptimizationRulesPage = ({ workspace, rules, query }: PageProps) => {
    const initialSorting = useMemo(() => {
        return toFrontendSort(query?.sort ?? null);
    }, [query?.sort]);

    const [searchValue, setSearchValue] = useState(query?.filter?.search ?? '');
    const [statusFilter, setStatusFilter] = useState(query?.filter?.status ?? '');
    const [dateRange, setDateRange] = useState<DateRange | undefined>({
        from: query?.start_date ? new Date(query.start_date) : moment().startOf('month').toDate(),
        to: query?.end_date ? new Date(query.end_date) : moment().toDate(),
    });
    const [isDialogOpen, setIsDialogOpen] = useState(false);
    const [editingRule, setEditingRule] = useState<OptimizationRule | null>(null);
    const [ruleToDelete, setRuleToDelete] = useState<OptimizationRule | null>(null);

    const dateRangeStr = useMemo(() => ({
        to: moment(dateRange?.to).format('YYYY-MM-DD'),
        from: moment(dateRange?.from).format('YYYY-MM-DD'),
    }), [dateRange]);

    // Debounce search
    useEffect(() => {
        const currentSearchParam = query?.filter?.search ?? '';

        if (searchValue === currentSearchParam) {
            return;
        }

        const timer = setTimeout(() => {
            router.get(
                `/workspaces/${workspace.slug}/ads-manager/optimization-rules`,
                {
                    sort: query?.sort,
                    'filter[search]': searchValue || undefined,
                    'filter[status]': statusFilter || undefined,
                    start_date: dateRange?.from ? dateRangeStr.from : undefined,
                    end_date: dateRange?.to ? dateRangeStr.to : undefined,
                    page: 1,
                },
                {
                    preserveState: true,
                    replace: true,
                    preserveScroll: true,
                    only: ['rules'],
                },
            );
        }, 500);

        return () => clearTimeout(timer);
    }, [searchValue, query?.filter?.search]);

    const handleStatusFilterChange = (value: string) => {
        setStatusFilter(value);

        router.get(
            `/workspaces/${workspace.slug}/ads-manager/optimization-rules`,
            {
                sort: query?.sort,
                'filter[search]': searchValue || undefined,
                'filter[status]': value || undefined,
                start_date: dateRange?.from ? dateRangeStr.from : undefined,
                end_date: dateRange?.to ? dateRangeStr.to : undefined,
                page: 1,
            },
            {
                preserveState: true,
                replace: true,
                preserveScroll: true,
                only: ['rules'],
            },
        );
    };

    // Update data when date range changes
    useEffect(() => {
        router.get(
            `/workspaces/${workspace.slug}/ads-manager/optimization-rules`,
            {
                sort: query?.sort,
                'filter[search]': searchValue || undefined,
                'filter[status]': statusFilter || undefined,
                start_date: dateRange?.from ? dateRangeStr.from : undefined,
                end_date: dateRange?.to ? dateRangeStr.to : undefined,
                page: 1,
            },
            {
                preserveState: true,
                replace: true,
                preserveScroll: true,
                only: ['rules'],
            },
        );
    }, [dateRange, dateRangeStr]);

    const handleDelete = async () => {
        if (!ruleToDelete) return;

        try {
            await axios.delete(`/workspaces/${workspace.slug}/api/optimization-rules/${ruleToDelete.id}`);
            setRuleToDelete(null);
            router.reload({ only: ['rules'] });
        } catch (error) {
            console.error('Error deleting optimization rule:', error);
            alert('Failed to delete optimization rule');
        }
    };

    const handleEdit = (rule: OptimizationRule) => {
        setEditingRule(rule);
        setIsDialogOpen(true);
    };

    const handleDialogClose = () => {
        setIsDialogOpen(false);
        setEditingRule(null);
    };

    const handleDialogSuccess = () => {
        router.reload({ only: ['rules'] });
        handleDialogClose();
    };

    const getActionLabel = (action: string, actionValue: number | null) => {
        const labels: Record<string, string> = {
            increase_budget_fixed: 'Increase Budget (Fixed)',
            decrease_budget_fixed: 'Decrease Budget (Fixed)',
            increase_budget_percentage: 'Increase Budget (%)',
            decrease_budget_percentage: 'Decrease Budget (%)',
        };
        const label = labels[action] || action;
        return actionValue ? `${label}: ${actionValue}` : label;
    };

    const getConditionsLabel = (conditions: OptimizationRuleCondition[]) => {
        if (!conditions || conditions.length === 0) return 'No conditions';
        return conditions.map(c => {
            const operatorLabels: Record<string, string> = {
                greater_than: '>',
                less_than: '<',
                equal: '=',
                greater_than_or_equal: '≥',
                less_than_or_equal: '≤',
            };
            return `${c.metric} ${operatorLabels[c.operator] || c.operator} ${c.value}`;
        }).join(', ');
    };

    const columns: ColumnDef<OptimizationRule>[] = [
        {
            accessorKey: 'name',
            header: ({ column }) => <SortableHeader column={column} title="Name" />,
            cell: ({ row }) => (
                <div className="font-medium text-gray-900 dark:text-white/90">
                    {row.original.name}
                </div>
            ),
        },
        {
            accessorKey: 'description',
            header: ({ column }) => <SortableHeader column={column} title="Description" />,
            cell: ({ row }) => (
                <div className="text-sm text-gray-600 dark:text-gray-400 max-w-xs truncate">
                    {row.original.description || '-'}
                </div>
            ),
        },
        {
            accessorKey: 'target',
            header: ({ column }) => <SortableHeader column={column} title="Target" />,
            cell: ({ row }) => (
                <div className="text-sm">
                    {row.original.target === 'campaign' ? 'Campaign' : 'Ad Set'}
                </div>
            ),
        },
        {
            accessorKey: 'action',
            header: ({ column }) => <SortableHeader column={column} title="Action" />,
            cell: ({ row }) => (
                <div className="text-sm">
                    {getActionLabel(row.original.action, row.original.action_value)}
                </div>
            ),
        },
        {
            accessorKey: 'conditions',
            header: ({ column }) => <SortableHeader column={column} title="Conditions" enabled={false} />,
            cell: ({ row }) => (
                <div className="text-sm text-gray-600 dark:text-gray-400 max-w-xs truncate">
                    {getConditionsLabel(row.original.conditions)}
                </div>
            ),
        },
        {
            accessorKey: 'status',
            header: ({ column }) => <SortableHeader column={column} title="Status" />,
            cell: ({ row }) => <StatusBadge status={row.original.status} />,
        },
        {
            id: 'actions',
            cell: ({ row }) => (
                <div className="flex items-center gap-2">
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => handleEdit(row.original)}
                    >
                        <Edit2 className="h-4 w-4" />
                    </Button>
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => setRuleToDelete(row.original)}
                    >
                        <Trash2 className="h-4 w-4 text-red-500" />
                    </Button>
                </div>
            ),
        },
    ];

    return (
        <AppLayout>
            <Head title={`${workspace.name} - Optimization Rules`} />
            <AdsManagerLayout
                workspace={workspace}
                activeTab="optimizationRules"
            >
                <div className="space-y-5 sm:space-y-6">
                    <ComponentCard desc="Manage your advertising campaigns and ad sets">
                        <div>
                            <OptimizationRulesFilters
                                searchValue={searchValue}
                                onSearchChange={setSearchValue}
                                statusFilter={statusFilter}
                                onStatusChange={handleStatusFilterChange}
                                dateRange={dateRange}
                                onDateRangeChange={setDateRange}
                                onAddRule={() => setIsDialogOpen(true)}
                            />

                            <DataTable
                                columns={columns}
                                data={rules?.data || []}
                                enableInternalPagination={false}
                                initialSorting={initialSorting}
                                meta={{ ...omit(rules, ['data']) }}
                                onFetch={(params) => {
                                    router.get(
                                        `/workspaces/${workspace.slug}/ads-manager/optimization-rules`,
                                        {
                                            sort: params?.sort,
                                            'filter[search]': searchValue || undefined,
                                            'filter[status]': statusFilter || undefined,
                                            start_date: dateRange?.from ? dateRangeStr.from : undefined,
                                            end_date: dateRange?.to ? dateRangeStr.to : undefined,
                                            page: params?.page ?? 1,
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
                    </ComponentCard>
                </div>

                <OptimizationRuleDialog
                    workspace={workspace}
                    open={isDialogOpen}
                    onClose={handleDialogClose}
                    onSuccess={handleDialogSuccess}
                    editingRule={editingRule}
                />

                {/* Delete Confirmation Dialog */}
                <AlertDialog open={!!ruleToDelete} onOpenChange={() => setRuleToDelete(null)}>
                    <AlertDialogContent>
                        <AlertDialogHeader>
                            <AlertDialogTitle>Delete Optimization Rule</AlertDialogTitle>
                            <AlertDialogDescription>
                                Are you sure you want to delete "{ruleToDelete?.name}"?
                                This action cannot be undone.
                            </AlertDialogDescription>
                        </AlertDialogHeader>
                        <AlertDialogFooter>
                            <AlertDialogCancel>Cancel</AlertDialogCancel>
                            <AlertDialogAction onClick={handleDelete}>
                                Delete Rule
                            </AlertDialogAction>
                        </AlertDialogFooter>
                    </AlertDialogContent>
                </AlertDialog>
            </AdsManagerLayout>
        </AppLayout>
    );
};

export default OptimizationRulesPage;
