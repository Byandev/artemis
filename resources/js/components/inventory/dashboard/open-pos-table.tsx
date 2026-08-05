import { Skeleton } from '@/components/ui/skeleton';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import RefreshButton from './refresh-button';
import { useInventoryStat } from './use-inventory-stat';

interface OpenPoLine {
    id: number;
    purchased_order_id: number;
    control_no: string | null;
    cust_po_no: string | null;
    status_label: string;
    sku: string | null;
    product_name: string | null;
    ordered_qty: number;
    delivered_qty: number;
    waiting_qty: number;
}

interface OpenPoData {
    lines: OpenPoLine[];
    total_waiting: number;
}

/** Locale-aware count; em dash for null/undefined. */
const num = (v: number | null | undefined) =>
    v == null ? '—' : Number(v).toLocaleString('en-PH');

/**
 * Sits under the movement chart: the open purchase-order lines the chart's
 * "in" arm is still waiting on, so a flat week of arrivals can be read against
 * what is actually outstanding.
 */
export default function OpenPosTable({ slug }: { slug: string }) {
    const { data, loading, error, refetch } = useInventoryStat<OpenPoData>(
        slug,
        'open-purchase-orders',
    );

    const lines = data?.lines ?? [];

    return (
        <div className="rounded-[14px] border border-black/6 bg-white p-[18px] dark:border-white/6 dark:bg-zinc-900">
            <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h3 className="text-sm font-semibold text-gray-900 dark:text-gray-100">
                        Open Purchase Orders
                    </h3>
                    <p className="mt-0.5 text-[11px] text-gray-400 dark:text-gray-500">
                        Lines still owing stock — ordered, delivered so far, and
                        what is still waiting.
                    </p>
                </div>
                <div className="flex shrink-0 items-center gap-2">
                    {!loading && !error && lines.length > 0 && (
                        <span className="text-[11px] text-gray-500 dark:text-gray-400">
                            {num(data?.total_waiting)} units waiting
                        </span>
                    )}
                    <RefreshButton
                        onClick={refetch}
                        loading={loading}
                        error={error}
                        label="open purchase orders"
                    />
                </div>
            </div>

            {loading ? (
                <TableSkeleton />
            ) : error ? (
                <EmptyState message="Couldn't load open purchase orders." />
            ) : lines.length === 0 ? (
                <EmptyState message="No open purchase orders — everything ordered has landed." />
            ) : (
                // Every open line is listed rather than a top-N, so the panel
                // scrolls internally instead of pushing the page down.
                <div className="max-h-[420px] overflow-auto rounded-[10px] border border-black/6 dark:border-white/6">
                    <Table>
                        <TableHeader className="sticky top-0 z-10 bg-zinc-50 dark:bg-zinc-950 [&_th]:border-b [&_th]:border-black/6 [&_th]:px-4 [&_th]:py-2.5 [&_th]:text-left [&_th]:text-[11px] [&_th]:font-medium [&_th]:text-gray-500 dark:[&_th]:border-white/6 dark:[&_th]:text-gray-400">
                            <TableRow>
                                <TableHead>PO</TableHead>
                                <TableHead>Item</TableHead>
                                <TableHead className="text-right!">
                                    Total PO
                                </TableHead>
                                <TableHead className="text-right!">
                                    Delivered
                                </TableHead>
                                <TableHead className="text-right!">
                                    Waiting
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {lines.map((line) => (
                                <TableRow
                                    key={line.id}
                                    className="border-b border-black/4 transition-colors hover:bg-zinc-50 dark:border-white/4 dark:hover:bg-zinc-800/60"
                                >
                                    <TableCell className="text-xs">
                                        <div className="font-medium text-gray-900 dark:text-gray-100">
                                            {line.control_no ??
                                                line.cust_po_no ??
                                                `#${line.purchased_order_id}`}
                                        </div>
                                        <div className="text-[11px] text-gray-400 dark:text-gray-500">
                                            {line.status_label}
                                        </div>
                                    </TableCell>
                                    <TableCell className="text-xs">
                                        <div className="font-medium text-gray-900 dark:text-gray-100">
                                            {line.sku ?? '—'}
                                        </div>
                                        {line.product_name && (
                                            <div className="text-[11px] text-gray-400 dark:text-gray-500">
                                                {line.product_name}
                                            </div>
                                        )}
                                    </TableCell>
                                    <TableCell className="text-right text-xs text-gray-700 tabular-nums dark:text-gray-300">
                                        {num(line.ordered_qty)}
                                    </TableCell>
                                    <TableCell className="text-right text-xs text-gray-700 tabular-nums dark:text-gray-300">
                                        {num(line.delivered_qty)}
                                    </TableCell>
                                    <TableCell className="text-right text-xs font-medium text-amber-600 tabular-nums dark:text-amber-500">
                                        {num(line.waiting_qty)}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>
            )}
        </div>
    );
}

/** Row placeholders matching the table's five columns. */
function TableSkeleton() {
    return (
        <div className="flex flex-col gap-2">
            {Array.from({ length: 5 }).map((_, i) => (
                <div key={i} className="flex items-center gap-3">
                    <Skeleton className="h-8 flex-[2]" />
                    <Skeleton className="h-8 flex-[3]" />
                    <Skeleton className="h-8 flex-1" />
                    <Skeleton className="h-8 flex-1" />
                    <Skeleton className="h-8 flex-1" />
                </div>
            ))}
        </div>
    );
}

function EmptyState({ message }: { message: string }) {
    return (
        <div className="flex h-[160px] items-center justify-center rounded-[12px] border border-dashed border-zinc-200 dark:border-zinc-800">
            <p className="text-sm text-zinc-500 dark:text-zinc-400">
                {message}
            </p>
        </div>
    );
}
