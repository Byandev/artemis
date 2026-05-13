import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { useForm, usePage } from '@inertiajs/react';
import React, { useEffect } from 'react';
import { groupedMetrics } from '@/types/metrics';

interface Workspace {
    id: number;
    name: string;
    slug: string;
    metric_settings?: { metric_key: string }[];
}

interface Props {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    workspace: Workspace | null;
}

export function MetricSettingDialog({ open, onOpenChange, workspace: localWorkspace }: Props) {
    const { props } = usePage();

    // Always prioritize the workspace from the shared page props to ensure we have fresh DB data
    const workspace = (props as any).currentWorkspace || localWorkspace;

    const { data, setData, put, processing, errors } = useForm({
        allowed_metrics: [] as string[],
        default_metrics: [] as string[],
    });

    useEffect(() => {
        if (open && workspace) {
            // Check both snake_case and camelCase to match your Middleware/Model naming
            const setting = (workspace as any).metric_setting || (workspace as any).metricSetting;

            if (setting?.allowed_metrics) {
                setData({
                    allowed_metrics: setting.allowed_metrics,
                    default_metrics: setting.default_metrics || setting.allowed_metrics,
                });
            } else {
                setData({
                    allowed_metrics: [],
                    default_metrics: [],
                });
            }
        }
    }, [open, workspace]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!workspace) return;

        put(`/admin/workspaces/${workspace.slug}/metrics`, {
            preserveScroll: true,
            onSuccess: () => {
                // The onOpenChange(false) will close the modal, 
                // and the 'back()' redirect from Laravel will refresh the page props.
                onOpenChange(false);
            },
        });
    };

    const toggleMetric = (key: string) => {
        const current = [...data.allowed_metrics];
        const index = current.indexOf(key);
        let defaults = [...data.default_metrics];

        if (index === -1) {
            current.push(key);
            defaults.push(key);
        } else {
            current.splice(index, 1);
            defaults = defaults.filter((metricKey) => metricKey !== key);
        }

        setData({
            allowed_metrics: current,
            default_metrics: Array.from(new Set(defaults)),
        });
    };

    const toggleGroup = (keys: string[], checked: boolean) => {
        if (checked) {
            const allowed = Array.from(new Set([...data.allowed_metrics, ...keys]));
            const defaults = Array.from(new Set([...data.default_metrics, ...keys]));

            setData({
                allowed_metrics: allowed,
                default_metrics: defaults,
            });
            return;
        }

        setData({
            allowed_metrics: data.allowed_metrics.filter((key) => !keys.includes(key)),
            default_metrics: data.default_metrics.filter((key) => !keys.includes(key)),
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[92vh] gap-0 overflow-hidden border-none p-0 shadow-2xl sm:max-w-5xl dark:bg-zinc-900">
                <div className="border-b border-black/6 px-8 pb-6 pt-7 dark:border-white/6">
                    <DialogHeader>
                        <DialogTitle className="text-[24px] font-semibold text-gray-900 dark:text-gray-100">
                            Workspace Metrics
                        </DialogTitle>
                        <DialogDescription className="mt-1.5 text-[15px] leading-relaxed text-gray-500 dark:text-gray-400">
                            Select which metrics are enabled for <strong>{workspace?.name}</strong>.
                        </DialogDescription>
                    </DialogHeader>
                </div>

                <form onSubmit={handleSubmit}>
                    <div className="max-h-[66vh] space-y-6 overflow-y-auto px-8 py-7">
                        {errors.allowed_metrics && (
                            <p className="rounded-lg border border-red-200 bg-red-50 px-4 py-3 font-mono text-[12px] text-red-600 dark:border-red-500/20 dark:bg-red-500/10 dark:text-red-300">
                                {errors.allowed_metrics}
                            </p>
                        )}

                        {groupedMetrics.map((group) => {
                            const groupMetricKeys = group.metrics.map((metric) => metric.key);
                            const selectedCount = groupMetricKeys.filter((key) => data.allowed_metrics.includes(key)).length;
                            const allSelected = selectedCount === groupMetricKeys.length;

                            return (
                                <section
                                    key={group.key}
                                    className="overflow-hidden rounded-2xl border border-black/8 bg-white shadow-sm dark:border-white/8 dark:bg-zinc-950/40"
                                >
                                    <div className="flex flex-col gap-4 border-b border-black/6 bg-stone-50 px-6 py-5 sm:flex-row sm:items-center sm:justify-between dark:border-white/6 dark:bg-zinc-900/70">
                                        <div>
                                            <h3 className="text-[18px] font-semibold text-gray-900 dark:text-gray-100">
                                                {group.label}
                                            </h3>
                                            <p className="mt-1 text-[13px] text-gray-500 dark:text-gray-400">
                                                {selectedCount} of {group.metrics.length} metrics enabled
                                            </p>
                                        </div>

                                        <button
                                            type="button"
                                            onClick={() => toggleGroup(groupMetricKeys, !allSelected)}
                                            className="h-10 rounded-lg border border-black/8 bg-white px-5 font-mono text-[13px] font-medium text-gray-700 transition-all hover:bg-stone-100 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-200 dark:hover:bg-zinc-700"
                                        >
                                            {allSelected ? 'Clear Group' : 'Select Group'}
                                        </button>
                                    </div>

                                    <div className="grid gap-4 p-5 md:grid-cols-2 xl:grid-cols-3">
                                        {group.metrics.map((metric) => {
                                            const checked = data.allowed_metrics.includes(metric.key);

                                            return (
                                                <label
                                                    key={metric.key}
                                                    className="flex min-h-[132px] cursor-pointer gap-4 rounded-xl border border-black/6 bg-stone-50/70 p-5 transition-all hover:border-emerald-300 hover:bg-emerald-50/60 dark:border-white/6 dark:bg-white/3 dark:hover:border-emerald-500/40 dark:hover:bg-emerald-500/10"
                                                >
                                                    <input
                                                        type="checkbox"
                                                        checked={checked}
                                                        onChange={() => toggleMetric(metric.key)}
                                                        className="mt-1 h-6 w-6 shrink-0 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500"
                                                    />
                                                    <span className="flex min-w-0 flex-col">
                                                        <span className="text-[16px] font-semibold leading-snug text-gray-900 dark:text-gray-100">
                                                            {metric.name}
                                                        </span>
                                                        <span className="mt-2 text-[13px] leading-relaxed text-gray-500 dark:text-gray-400">
                                                            {metric.description}
                                                        </span>
                                                    </span>
                                                </label>
                                            );
                                        })}
                                    </div>
                                </section>
                            );
                        })}
                    </div>

                    <Footer
                        processing={processing}
                        onCancel={() => onOpenChange(false)}
                    />
                </form>
            </DialogContent>
        </Dialog>
    );
}

function Footer({ processing, onCancel }: { processing: boolean; onCancel: () => void }) {
    return (
        <div className="flex items-center justify-end gap-3 border-t border-black/6 bg-stone-50/80 px-8 py-5 dark:border-white/6 dark:bg-white/2">
            <button
                type="button"
                onClick={onCancel}
                className="flex h-11 items-center rounded-lg border border-black/8 bg-white px-6 font-mono text-[13px] font-medium text-gray-600 transition-all hover:bg-stone-100 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-300"
            >
                Cancel
            </button>
            <button
                type="submit"
                disabled={processing}
                className="flex h-11 items-center rounded-lg bg-emerald-600 px-6 font-mono text-[13px] font-medium text-white transition-all hover:bg-emerald-700 disabled:opacity-50"
            >
                {processing ? 'Saving...' : 'Save Changes'}
            </button>
        </div>
    );
}
