import { Workspace } from '@/types/models/Workspace';
import { ApexOptions } from 'apexcharts';
import { useEffect, useMemo, useState } from 'react';
import Chart from 'react-apexcharts';

interface StatusItem {
    status: string;
    count: number;
}

interface Props {
    workspace: Workspace;
}

const STATUS_COLORS: Record<string, string> = {
    PENDING: '#f59e0b',
    'CX RINGING': '#3b82f6',
    'RIDER RINGING': '#8b5cf6',
    RETURNING: '#ef4444',
    DELIVERED: '#10b981',
    UNDELIVERABLE: '#6b7280',
};

export default function StatusBreakdown({ workspace }: Props) {
    const [data, setData] = useState<StatusItem[]>([]);
    const [loading, setLoading] = useState(true);

    console.log(workspace.slug);

    useEffect(() => {
        const controller = new AbortController();
        setLoading(true);



        fetch(
            `/workspaces/${workspace.slug}/rts/rmo-management/analytics/status-breakdown`,
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

    const labels = useMemo(() => data.map((d) => d.status), [data]);
    const series = useMemo(() => data.map((d) => Number(d.count)), [data]);
    const colors = useMemo(
        () => data.map((d) => STATUS_COLORS[d.status] ?? '#94a3b8'),
        [data],
    );

    const options: ApexOptions = {
        labels,
        colors,
        chart: {
            fontFamily: 'DM Sans, sans-serif',
            type: 'donut',
        },
        legend: {
            position: 'bottom',
            fontSize: '12px',
            fontFamily: 'DM Sans, sans-serif',
            labels: { colors: '#6B7280' },
            markers: { size: 6, shape: 'circle', strokeWidth: 0, offsetX: -2 },
            itemMargin: { horizontal: 8, vertical: 4 },
        },
        dataLabels: { enabled: false },
        plotOptions: {
            pie: {
                donut: {
                    size: '70%',
                    labels: {
                        show: true,
                        total: {
                            show: true,
                            label: 'Total',
                            fontFamily: 'DM Sans, sans-serif',
                            fontSize: '13px',
                            fontWeight: 600,
                            color: '#9CA3AF',
                        },
                    },
                },
            },
        },
        stroke: { width: 2, colors: ['#ffffff'] },
        tooltip: {
            y: {
                formatter: (val: number) => val.toLocaleString(),
            },
        },
    };

    return (
        <div className="rounded-[14px] border border-black/6 bg-white p-5 dark:border-white/6 dark:bg-zinc-900">
            <div className="mb-4">
                <h3 className="text-sm font-semibold text-gray-900 dark:text-gray-100">
                    Status Breakdown
                </h3>
                <p className="text-[11px] text-gray-400 dark:text-gray-500">
                    Distribution of orders by current status
                </p>
            </div>

            {loading ? (
                <div className="flex h-60 items-center justify-center">
                    <div className="h-40 w-40 animate-pulse rounded-full bg-gray-200 dark:bg-zinc-800" />
                </div>
            ) : data.length === 0 ? (
                <div className="flex h-60 items-center justify-center">
                    <p className="text-sm text-gray-400 dark:text-gray-500">No data available</p>
                </div>
            ) : (
                <Chart options={options} series={series} type="donut" height={320} />
            )}
        </div>
    );
}
