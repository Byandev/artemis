import PageHeader from '@/components/common/PageHeader';
import { DataTable, SortableHeader } from '@/components/ui/data-table';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { Head } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import { ArrowDownRight, ArrowUpRight, Minus } from 'lucide-react';

interface BudgetRow {
    id: string;
    name: string;
    today: number;
    yesterday: number;
    difference: number;
}

interface Props {
    dates: { today: string; yesterday: string };
    perPage: BudgetRow[];
    perProduct: BudgetRow[];
    perUser: BudgetRow[];
}

/** Exact Philippine-peso formatting with thousands separators and 2 decimals. */
function peso(value: number): string {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(value);
}

/** Percentage change of today vs yesterday, or null when there is no base. */
function pctChange(today: number, yesterday: number): number | null {
    if (yesterday <= 0) return null;
    return ((today - yesterday) / yesterday) * 100;
}

/** Format a YYYY-MM-DD string as e.g. "Jun 17" without a timezone shift. */
function formatDateLabel(date: string): string {
    const [y, m, d] = date.split('-').map(Number);
    if (!y || !m || !d) return date;
    const months = [
        'Jan',
        'Feb',
        'Mar',
        'Apr',
        'May',
        'Jun',
        'Jul',
        'Aug',
        'Sep',
        'Oct',
        'Nov',
        'Dec',
    ];
    return `${months[m - 1]} ${d}`;
}

function DifferenceCell({ row }: { row: BudgetRow }) {
    const value = row.difference;
    const pct = pctChange(row.today, row.yesterday);
    const Icon = value > 0 ? ArrowUpRight : value < 0 ? ArrowDownRight : Minus;
    const color =
        value > 0
            ? 'text-brand-600 dark:text-brand-400'
            : value < 0
              ? 'text-red-600 dark:text-red-400'
              : 'text-gray-400 dark:text-gray-500';

    return (
        <span
            className={cn(
                'inline-flex items-center gap-1 font-mono text-[12px]',
                color,
            )}
        >
            <Icon className="h-3 w-3" />
            {peso(Math.abs(value))}
            {pct !== null && value !== 0 && (
                <span className="text-[10px] opacity-70">
                    {pct > 0 ? '+' : ''}
                    {pct.toFixed(0)}%
                </span>
            )}
        </span>
    );
}

function budgetColumns(label: string): ColumnDef<BudgetRow>[] {
    return [
        {
            accessorKey: 'name',
            enableSorting: false,
            header: ({ column }) => (
                <SortableHeader column={column} title={label} enabled={false} />
            ),
            cell: ({ row }) => (
                <span className="text-[12px] font-medium text-gray-700 dark:text-gray-200">
                    {row.original.name}
                </span>
            ),
        },
        {
            accessorKey: 'today',
            enableSorting: false,
            header: ({ column }) => (
                <SortableHeader column={column} title="Today" enabled={false} />
            ),
            cell: ({ row }) => (
                <span className="font-mono text-[12px] text-gray-700 dark:text-gray-300">
                    {peso(row.original.today)}
                </span>
            ),
        },
        {
            accessorKey: 'yesterday',
            enableSorting: false,
            header: ({ column }) => (
                <SortableHeader
                    column={column}
                    title="Yesterday"
                    enabled={false}
                />
            ),
            cell: ({ row }) => (
                <span className="font-mono text-[12px] text-gray-500 dark:text-gray-400">
                    {peso(row.original.yesterday)}
                </span>
            ),
        },
        {
            accessorKey: 'difference',
            enableSorting: false,
            header: ({ column }) => (
                <SortableHeader
                    column={column}
                    title="Difference"
                    enabled={false}
                />
            ),
            cell: ({ row }) => <DifferenceCell row={row.original} />,
        },
    ];
}

const pageColumns = budgetColumns('Page');
const productColumns = budgetColumns('Product');
const userColumns = budgetColumns('User');

export default function MetaBudgetTracker({
    dates,
    perPage,
    perProduct,
    perUser,
}: Props) {
    return (
        <AppLayout>
            <Head title="Meta Ads · Ad Spent Budget Tracker" />

            <div className="w-full space-y-6 p-4 md:p-6">
                <PageHeader
                    title="Ad Spent Budget Tracker"
                    description={`Page daily budget — today (${formatDateLabel(dates.today)}) vs yesterday (${formatDateLabel(dates.yesterday)}).`}
                />

                <Tabs defaultValue="page" className="gap-4">
                    <TabsList>
                        <TabsTrigger value="page">Per Page</TabsTrigger>
                        <TabsTrigger value="product">Per Product</TabsTrigger>
                        <TabsTrigger value="user">Per User</TabsTrigger>
                    </TabsList>

                    <TabsContent value="page">
                        <div className="rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
                            <DataTable columns={pageColumns} data={perPage} />
                        </div>
                    </TabsContent>
                    <TabsContent value="product">
                        <div className="rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
                            <DataTable
                                columns={productColumns}
                                data={perProduct}
                            />
                        </div>
                    </TabsContent>
                    <TabsContent value="user">
                        <div className="rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
                            <DataTable columns={userColumns} data={perUser} />
                        </div>
                    </TabsContent>
                </Tabs>
            </div>
        </AppLayout>
    );
}
