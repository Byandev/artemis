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
import { SimpleDateRangePicker } from '@/components/ui/simple-date-range-picker';
import { toFrontendSort } from '@/lib/sort';
import { PaginatedData } from '@/types';
import { OptimizationRule, OptimizationRuleCondition } from '@/types/models/OptimizationRule';
import { Workspace } from '@/types/models/Workspace';
import { router } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import axios from 'axios';
import clsx from 'clsx';
import { omit } from 'lodash';
import { Edit2, Plus, Trash2 } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { OptimizationRuleDialog } from './OptimizationRuleDialog';

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

interface OptimizationRulesTabProps {
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
    };
    dateRange: { from: Date; to: Date };
}

const OptimizationRulesTab = ({
    workspace,
    rules,
    query,
    dateRange,
}: OptimizationRulesTabProps) => {
    const initialSorting = useMemo(() => {
        return toFrontendSort(query?.sort ?? null);
    }, [query?.sort]);

    const [searchValue, setSearchValue] = useState(query?.filter?.search ?? '');
    const [statusFilter, setStatusFilter] = useState(query?.filter?.status ?? '');
    const [isDialogOpen, setIsDialogOpen] = useState(false);
    const [editingRule, setEditingRule] = useState<OptimizationRule | null>(null);
    const [ruleToDelete, setRuleToDelete] = useState<OptimizationRule | null>(null);

    // Debounce search
    useEffect(() => {
        const currentSearchParam = query?.filter?.search ?? '';

        if (searchValue === currentSearchParam) {
            return;
        }

        const timer = setTimeout(() => {
            router.get(
                `/workspaces/${workspace.slug}/ads-manager`,
                {
                    sort: query?.sort,
                    'filter[search]': searchValue || undefined,
                    'filter[status]': statusFilter || undefined,
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
            `/workspaces/${workspace.slug}/ads-manager`,
            {
                sort: query?.sort,
                'filter[search]': searchValue || undefined,
                'filter[status]': value || undefined,
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
        <div>
            <div className="flex flex-col gap-2 rounded-t-xl border border-b-0 border-gray-100 px-4 py-4 sm:flex-row sm:items-center sm:justify-between dark:border-white/5">
                <input
                    className="max-w-sm border w-full rounded-lg appearance-none px-4 py-2.5 text-sm shadow-theme-xs placeholder:text-gray-400 focus:outline-hidden focus:ring-3 dark:bg-gray-900 dark:placeholder:text-white/30 bg-transparent text-gray-800 border-gray-300 focus:border-brand-300 focus:ring-brand-500/20 dark:border-gray-700 dark:text-white/90 dark:focus:border-brand-800"
                    placeholder="Search optimization rules..."
                    value={searchValue}
                    onChange={(e) => setSearchValue(e.target.value)}
                />
                <div className="flex items-center gap-2 relative z-50">
                    <select
                        value={statusFilter}
                        onChange={(e) => handleStatusFilterChange(e.target.value)}
                        className="h-9 rounded-md border border-gray-300 dark:border-gray-700 bg-transparent px-3 py-2 text-sm text-gray-800 dark:text-white/90 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20 focus:border-brand-300 dark:focus:border-brand-800"
                    >
                        <option value="">All Status</option>
                        <option value="active">Active</option>
                        <option value="paused">Paused</option>
                    </select>
                    <SimpleDateRangePicker
                        value={{ from: dateRange.from, to: dateRange.to }}
                        onChange={(newRange) => {
                            // Date range update logic can be added here if needed
                            console.log('Date range updated:', newRange);
                        }}
                    />
                    <Button
                        variant="default"
                        size="sm"
                        onClick={() => setIsDialogOpen(true)}
                    >
                        <Plus className="h-4 w-4 mr-2" />
                        Add Rule
                    </Button>
                </div>
            </div>

            <div className="border-t border-gray-100 dark:border-white/5 px-4 py-3">
                <div className="text-sm text-gray-600 dark:text-gray-400">
                    {rules ? `${rules.total} optimization rule${rules.total !== 1 ? 's' : ''}` : 'Loading...'}
                </div>
            </div>

            <DataTable
                columns={columns}
                data={rules?.data || []}
                enableInternalPagination={false}
                initialSorting={initialSorting}
                meta={{ ...omit(rules, ['data']) }}
                onFetch={(params) => {
                    router.get(
                        `/workspaces/${workspace.slug}/ads-manager`,
                        {
                            sort: params?.sort,
                            'filter[search]': searchValue || undefined,
                            'filter[status]': statusFilter || undefined,
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
        </div>
    );
};

export default OptimizationRulesTab;
