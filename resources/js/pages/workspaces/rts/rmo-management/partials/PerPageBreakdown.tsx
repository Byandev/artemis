import BarChart from '@/components/charts/BarChart';
import BarChartSkeleton from '@/components/charts/skeletons/BarChartSkeleton';
import { Workspace } from '@/types/models/Workspace';
import { useEffect, useMemo, useState } from 'react';

interface PageItem {
    id: number;
    name: string;
    total_orders: number;
}

interface Props {
    workspace: Workspace;
}

export default function PerPageBreakdown({ workspace }: Props) {
    const [data, setData] = useState<PageItem[]>([]);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        const controller = new AbortController();
        setLoading(true);

        fetch(
            `/workspaces/${workspace.slug}/rts/rmo-management/analytics/per-page`,
            { credentials: 'same-origin', signal: controller.signal },
        )
            .then((res) => res.json())
            .then((result) => setData(result))
            .catch((err) => {
                if (err.name !== 'AbortError') console.error(err);
            })
            .finally(() => {
                if (!controller.signal.aborted) setLoading(false);
            });

        return () => controller.abort();
    }, [workspace.slug]);

    const categories = useMemo(() => data.map((d) => d.name), [data]);
    const series = useMemo(
        () => [{ name: 'Orders', data: data.map((d) => d.total_orders) }],
        [data],
    );

    return (
        <div className="rounded-[14px] border border-black/6 bg-white p-5 dark:border-white/6 dark:bg-zinc-900">
            <div className="mb-4">
                <h3 className="text-sm font-semibold text-gray-900 dark:text-gray-100">
                    Orders per Page
                </h3>
                <p className="text-[11px] text-gray-400 dark:text-gray-500">
                    Top 10 pages by order volume
                </p>
            </div>

            {loading ? (
                <BarChartSkeleton />
            ) : data.length === 0 ? (
                <div className="flex h-60 items-center justify-center">
                    <p className="text-sm text-gray-400 dark:text-gray-500">No data available</p>
                </div>
            ) : (
                <BarChart
                    categories={categories}
                    series={series}
                    formatValue={(v) => v.toLocaleString()}
                />
            )}
        </div>
    );
}