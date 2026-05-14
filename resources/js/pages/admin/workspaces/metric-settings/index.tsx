import ComponentCard from '@/components/common/ComponentCard';
import PageHeader from '@/components/common/PageHeader';
import AdminSidebarLayout from '@/layouts/admin/admin-sidebar-layout';
import { Head, useForm } from '@inertiajs/react';
import { toast } from 'sonner';
import { route } from 'ziggy-js';

// Define your metric groups
const METRIC_GROUPS: Record<string, string> = {
    revenueVolume: 'Revenue & Volume',
    fulfillmentLeadTime: 'Fulfillment Lead Time',
    deliveryOutcomes: 'Delivery Outcomes',
    customerQualityRetention: 'Customer Quality & Retention',
    deliveryQualitySignals: 'Delivery Quality Signals',
};

interface MetricConfig {
    key: string;
    groupKey: string;
    name: string;
}

interface Props {
    workspace: {
        id: number;
        name: string;
        slug: string;
    };
    settings: {
        allowed_metrics: string[];
        default_metrics: string[];
    };
    configs: MetricConfig[];
}

export default function MetricSettings({
    workspace,
    settings,
    configs,
}: Props) {
    const { data, setData, patch, processing } = useForm({
        allowed_metrics: settings?.allowed_metrics || [],
        default_metrics: settings?.default_metrics || [],
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();

        (patch(route('workspaces.metric-settings.update', workspace.slug)),
            {
                onSuccess: () =>
                    toast.success('Metric settings updated successfully'),
            });

        const toggleAllowed = (key: string) => {
            const currentAllowed = [...data.allowed_metrics];
            const index = currentAllowed.indexOf(key);

            if (index > -1) {
                currentAllowed.splice(index, 1);
                const currentDefaults = data.default_metrics.filter(
                    (d) => d !== key,
                );
                setData((d) => ({
                    ...d,
                    allowed_metrics: currentAllowed,
                    default_metrics: currentDefaults,
                }));
            } else {
                currentAllowed.push(key);
                setData('allowed_metrics', currentAllowed);
            }
        };

        const toggleDefault = (key: string) => {
            if (!data.allowed_metrics.includes(key)) return;

            const currentDefaults = [...data.default_metrics];
            const index = currentDefaults.indexOf(key);

            if (index > -1) {
                currentDefaults.splice(index, 1);
            } else {
                currentDefaults.push(key);
            }
            setData('default_metrics', currentDefaults);
        };

        const selectGroup = (groupKey: string, all: boolean) => {
            const groupMetricKeys = configs
                .filter((c) => c.groupKey === groupKey)
                .map((c) => c.key);

            if (all) {
                const newAllowed = Array.from(
                    new Set([...data.allowed_metrics, ...groupMetricKeys]),
                );
                setData('allowed_metrics', newAllowed);
            } else {
                const newAllowed = data.allowed_metrics.filter(
                    (k) => !groupMetricKeys.includes(k),
                );
                const newDefaults = data.default_metrics.filter(
                    (k) => !groupMetricKeys.includes(k),
                );
                setData((d) => ({
                    ...d,
                    allowed_metrics: newAllowed,
                    default_metrics: newDefaults,
                }));
            }
        };

        return (
            <AdminSidebarLayout>
                <Head title={`Metrics | ${workspace.name}`} />

                <div className="p-4 md:p-6">
                    <PageHeader
                        title="Metric Settings"
                        description={`Configure which metrics are visible and enabled by default for ${workspace.name}.`}
                    />

                    <form onSubmit={submit} className="mt-6 space-y-6">
                        {Object.entries(METRIC_GROUPS).map(
                            ([groupKey, groupLabel]) => {
                                const groupMetrics = configs.filter(
                                    (c) => c.groupKey === groupKey,
                                );
                                if (groupMetrics.length === 0) return null;

                                return (
                                    <ComponentCard
                                        key={groupKey}
                                        className="overflow-hidden"
                                    >
                                        <div className="flex items-center justify-between border-b border-zinc-100 bg-zinc-50/50 px-6 py-4 dark:border-zinc-800 dark:bg-zinc-900/50">
                                            <h3 className="text-sm font-bold tracking-wider text-zinc-600 uppercase dark:text-zinc-400">
                                                {groupLabel}
                                            </h3>
                                            <div className="flex gap-4">
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        selectGroup(
                                                            groupKey,
                                                            true,
                                                        )
                                                    }
                                                    className="text-xs font-semibold text-brand-600 hover:underline"
                                                >
                                                    Select All
                                                </button>
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        selectGroup(
                                                            groupKey,
                                                            false,
                                                        )
                                                    }
                                                    className="text-xs font-semibold text-zinc-500 hover:underline"
                                                >
                                                    Clear
                                                </button>
                                            </div>
                                        </div>

                                        <div className="divide-y divide-zinc-100 dark:divide-zinc-800">
                                            <div className="grid grid-cols-12 px-6 py-3 text-[11px] font-bold tracking-widest text-zinc-400 uppercase">
                                                <div className="col-span-8">
                                                    Metric Name
                                                </div>
                                                <div className="col-span-2 text-center">
                                                    Allowed
                                                </div>
                                                <div className="col-span-2 text-center">
                                                    Default-On
                                                </div>
                                            </div>

                                            {groupMetrics.map((metric) => (
                                                <div
                                                    key={metric.key}
                                                    className="grid grid-cols-12 items-center px-6 py-4 hover:bg-zinc-50/50 dark:hover:bg-zinc-800/20"
                                                >
                                                    <div className="col-span-8">
                                                        <span className="text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                                            {metric.name}
                                                        </span>
                                                    </div>
                                                    <div className="col-span-2 flex justify-center">
                                                        <input
                                                            type="checkbox"
                                                            checked={data.allowed_metrics.includes(
                                                                metric.key,
                                                            )}
                                                            onChange={() =>
                                                                toggleAllowed(
                                                                    metric.key,
                                                                )
                                                            }
                                                            className="h-4 w-4 rounded border-zinc-300 text-brand-600 focus:ring-brand-500"
                                                        />
                                                    </div>
                                                    <div className="col-span-2 flex justify-center">
                                                        <input
                                                            type="checkbox"
                                                            disabled={
                                                                !data.allowed_metrics.includes(
                                                                    metric.key,
                                                                )
                                                            }
                                                            checked={data.default_metrics.includes(
                                                                metric.key,
                                                            )}
                                                            onChange={() =>
                                                                toggleDefault(
                                                                    metric.key,
                                                                )
                                                            }
                                                            className="h-4 w-4 rounded border-zinc-300 text-brand-600 focus:ring-brand-500 disabled:opacity-30"
                                                        />
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    </ComponentCard>
                                );
                            },
                        )}

                        <div className="sticky bottom-6 flex justify-end">
                            <button
                                type="submit"
                                disabled={processing}
                                className="inline-flex items-center justify-center rounded-md bg-zinc-900 px-8 py-2.5 text-sm font-semibold text-white transition-all hover:bg-zinc-800 focus:ring-2 focus:ring-zinc-900 focus:ring-offset-2 focus:outline-none disabled:opacity-50 dark:bg-zinc-50 dark:text-zinc-900 dark:hover:bg-zinc-200"
                            >
                                {processing
                                    ? 'Saving...'
                                    : 'Save Metric Settings'}
                            </button>
                        </div>
                    </form>
                </div>
            </AdminSidebarLayout>
        );
    };
}
