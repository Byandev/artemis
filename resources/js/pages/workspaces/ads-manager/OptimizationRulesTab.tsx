import { Button } from '@/components/ui/button';
import { DataTable, SortableHeader } from '@/components/ui/data-table';
import { OptimizationRule, OptimizationRuleCondition } from '@/types/models/OptimizationRule';
import { Workspace } from '@/types/models/Workspace';
import { ColumnDef } from '@tanstack/react-table';
import axios from 'axios';
import { Edit2, Plus, Trash2 } from 'lucide-react';
import { forwardRef, useEffect, useImperativeHandle, useState } from 'react';
import { OptimizationRuleDialog } from './OptimizationRuleDialog';

interface PaginationLinks {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedRules {
    data: OptimizationRule[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: PaginationLinks[];
    from: number;
    to: number;
}

interface StatusBadgeProps {
    status: string;
}

const StatusBadge = ({ status }: StatusBadgeProps) => {
    const getStatusColor = (status: string) => {
        switch (status.toLowerCase()) {
            case 'active':
                return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400';
            case 'paused':
                return 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400';
            default:
                return 'bg-gray-100 text-gray-700 dark:bg-gray-900/30 dark:text-gray-400';
        }
    };

    return (
        <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${getStatusColor(status)}`}>
            {status}
        </span>
    );
};

interface OptimizationRulesTabProps {
    workspace: Workspace;
    searchQuery: string;
    statusFilter: string;
    dateRange: { from: Date; to: Date };
    loading: boolean;
    setLoading: (loading: boolean) => void;
}

const OptimizationRulesTab = forwardRef(({
    workspace,
    searchQuery,
    statusFilter,
    dateRange,
    loading,
    setLoading,
}: OptimizationRulesTabProps, ref) => {
    const [rules, setRules] = useState<PaginatedRules | null>(null);
    const [isDialogOpen, setIsDialogOpen] = useState(false);
    const [editingRule, setEditingRule] = useState<OptimizationRule | null>(null);

    const fetchRules = async (page: number = 1, sort?: string) => {
        setLoading(true);
        try {
            const response = await axios.get(`/workspaces/${workspace.id}/api/optimization-rules`, {
                params: {
                    page,
                    sort,
                },
            });
            setRules(response.data);
        } catch (error) {
            console.error('Error fetching optimization rules:', error);
        } finally {
            setLoading(false);
        }
    };

    useImperativeHandle(ref, () => ({
        fetchRules,
    }));

    useEffect(() => {
        fetchRules();
    }, [workspace.id]);

    const handleDelete = async (ruleId: number) => {
        if (!confirm('Are you sure you want to delete this optimization rule?')) {
            return;
        }

        try {
            await axios.delete(`/workspaces/${workspace.id}/api/optimization-rules/${ruleId}`);
            fetchRules(rules?.current_page || 1);
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
        fetchRules(rules?.current_page || 1);
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
            header: ({ column }) => <SortableHeader column={column} title="Actions" enabled={false} />,
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
                        onClick={() => handleDelete(row.original.id)}
                    >
                        <Trash2 className="h-4 w-4 text-red-500" />
                    </Button>
                </div>
            ),
        },
    ];

    return (
        <div>
            <div className="px-4 py-3 border-b border-gray-100 dark:border-white/[0.05] flex justify-between items-center">
                <div className="text-sm text-gray-600 dark:text-gray-400">
                    {rules ? `${rules.total} optimization rule${rules.total !== 1 ? 's' : ''}` : 'Loading...'}
                </div>
                <Button
                    variant="default"
                    size="sm"
                    onClick={() => setIsDialogOpen(true)}
                >
                    <Plus className="h-4 w-4 mr-2" />
                    Add Rule
                </Button>
            </div>

            <DataTable
                columns={columns}
                data={rules?.data || []}
                enableInternalPagination={false}
                meta={rules ? {
                    current_page: rules.current_page,
                    last_page: rules.last_page,
                    per_page: rules.per_page,
                    total: rules.total,
                    from: rules.from,
                    to: rules.to,
                    links: rules.links,
                } : undefined}
                onFetch={(params) => {
                    fetchRules(params?.page || 1, params?.sort);
                }}
            />

            <OptimizationRuleDialog
                workspace={workspace}
                open={isDialogOpen}
                onClose={handleDialogClose}
                onSuccess={handleDialogSuccess}
                editingRule={editingRule}
            />
        </div>
    );
});

OptimizationRulesTab.displayName = 'OptimizationRulesTab';

export default OptimizationRulesTab;
