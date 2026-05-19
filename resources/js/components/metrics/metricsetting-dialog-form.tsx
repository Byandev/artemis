import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { useForm, usePage } from '@inertiajs/react';
import React, { useEffect } from 'react';
import { groupedMetrics, type MetricKey } from '@/types/metrics';

interface Workspace {
    id: number;
    name: string;
    slug: string;
    metric_settings?: { metric_key: string }[];
    metric_setting?: WorkspaceMetricSetting | null;
    metricSetting?: WorkspaceMetricSetting | null;
}

interface Props {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    workspace: Workspace | null;
}

interface WorkspaceMetricSetting {
    allowed_metrics?: MetricKey[];
    default_metrics?: MetricKey[];
}

interface PageProps {
    currentWorkspace?: Workspace | null;
}

export function MetricSettingDialog({ open, onOpenChange, workspace: localWorkspace }: Props) {
    const { props } = usePage<PageProps>();

    // Admin workspace management edits the row workspace, not the user's current workspace.
    const workspace = localWorkspace || props.currentWorkspace;

    const { data, setData, put, processing, errors } = useForm({
        allowed_metrics: [] as MetricKey[],
        default_metrics: [] as MetricKey[],
    });

    useEffect(() => {
        if (!open) return;

        const previousOverflow = document.body.style.overflow;
        const previousDocumentOverflow = document.documentElement.style.overflow;
        document.body.style.overflow = 'hidden';
        document.documentElement.style.overflow = 'hidden';

        return () => {
            document.body.style.overflow = previousOverflow;
            document.documentElement.style.overflow = previousDocumentOverflow;
        };
    }, [open]);

    useEffect(() => {
        if (open && workspace) {
            const setting = workspace.metric_setting || workspace.metricSetting;

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
    }, [open, setData, workspace]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!workspace) return;

        put(`/admin/workspaces/${workspace.slug}/metrics`, {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        });
    };

    const toggleMetric = (key: MetricKey) => {
        const current = [...data.allowed_metrics];
        const index = current.indexOf(key);

        if (index === -1) {
            current.push(key);
            setData('allowed_metrics', current);
            return;
        }

        current.splice(index, 1);
        setData({
            allowed_metrics: current,
            default_metrics: data.default_metrics.filter((metricKey) => metricKey !== key),
        });
    };

    const setGroupMetrics = (keys: MetricKey[], enabled: boolean) => {
        if (enabled) {
            setData('allowed_metrics', Array.from(new Set([...data.allowed_metrics, ...keys])));
            return;
        }

        setData({
            allowed_metrics: data.allowed_metrics.filter((key) => !keys.includes(key)),
            default_metrics: data.default_metrics.filter((key) => !keys.includes(key)),
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="mx-auto h-auto w-full max-w-7xl gap-0 border-none p-6 shadow-2xl sm:max-w-7xl dark:bg-zinc-900">
                <div className="mb-4 border-b border-black/6 pb-3 pr-8 dark:border-white/6">
                    <DialogHeader>
                        <DialogTitle className="text-[15px] font-semibold text-gray-900 dark:text-gray-100">
                            Workspace Metrics
                        </DialogTitle>
                        <DialogDescription className="mt-0.5 text-[12px] text-gray-400 dark:text-gray-500">
                            Select which metrics are enabled for <strong>{workspace?.name}</strong>.
                        </DialogDescription>
                    </DialogHeader>
                </div>

                <form onSubmit={handleSubmit}>
                    <div>
                        <Field label="Allowed Metrics" error={errors.allowed_metrics}>
                            <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
                                {groupedMetrics.map((group) => {
                                    const metricKeys = group.metrics.map((metric) => metric.key);
                                    const selectedCount = metricKeys.filter((key) => data.allowed_metrics.includes(key)).length;
                                    const allSelected = selectedCount === metricKeys.length;

                                    return (
                                        <section
                                            key={group.key}
                                            className="rounded-lg border border-black/6 bg-stone-50/50 dark:border-white/6 dark:bg-white/2"
                                        >
                                            <div className="flex items-start justify-between gap-2 border-b border-black/6 px-2.5 py-2 dark:border-white/6">
                                                <div>
                                                    <h3 className="text-[11px] font-semibold leading-4 text-gray-800 dark:text-gray-100">
                                                        {group.label}
                                                    </h3>
                                                    <p className="font-mono text-[10px] text-gray-400 dark:text-gray-500">
                                                        {selectedCount} of {metricKeys.length} enabled
                                                    </p>
                                                </div>
                                                <button
                                                    type="button"
                                                    onClick={() => setGroupMetrics(metricKeys, !allSelected)}
                                                    className="shrink-0 rounded-md px-1.5 py-0.5 font-mono text-[10px] font-medium text-emerald-600 transition-colors hover:bg-emerald-50 dark:text-emerald-400 dark:hover:bg-emerald-500/10"
                                                >
                                                    {allSelected ? 'Clear' : 'Select all'}
                                                </button>
                                            </div>

                                            <div className="grid gap-1.5 p-2">
                                                {group.metrics.map((metric) => {
                                                    const checked = data.allowed_metrics.includes(metric.key);

                                                    return (
                                                        <label
                                                            key={metric.key}
                                                            className={[
                                                                'flex cursor-pointer items-center gap-1.5 rounded-md border px-1.5 py-1 transition-colors',
                                                                checked
                                                                    ? 'border-emerald-500/20 bg-emerald-500/10 dark:border-emerald-500/20 dark:bg-emerald-500/10'
                                                                    : 'border-transparent hover:bg-white dark:hover:bg-white/5',
                                                            ].join(' ')}
                                                        >
                                                            <Checkbox
                                                                checked={checked}
                                                                onCheckedChange={() => toggleMetric(metric.key)}
                                                                className="size-3.5 border-black/20 data-[state=checked]:border-emerald-600 data-[state=checked]:bg-emerald-600 data-[state=checked]:text-white dark:border-white/20 dark:data-[state=checked]:border-emerald-500 dark:data-[state=checked]:bg-emerald-500"
                                                            />
                                                            <span className="text-[10px] font-medium leading-[14px] text-gray-700 dark:text-gray-300">
                                                                {metric.name}
                                                            </span>
                                                        </label>
                                                    );
                                                })}
                                            </div>
                                        </section>
                                    );
                                })}
                            </div>
                        </Field>
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

function Field({ label, error, children }: { label: string; error?: React.ReactNode; children: React.ReactNode }) {
    return (
        <div className="space-y-1.5">
            <label className="block font-mono text-[10px] font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500">
                {label}
            </label>
            {children}
            {error && <p className="mt-1 font-mono text-[11px] text-red-500">{error}</p>}
        </div>
    );
}

function Footer({ processing, onCancel }: { processing: boolean; onCancel: () => void }) {
    return (
        <div className="mt-4 flex items-center justify-end gap-2 border-t border-black/6 pt-3 dark:border-white/6">
            <button
                type="button"
                onClick={onCancel}
                className="flex h-8 items-center rounded-lg border border-black/8 bg-white px-3.5 font-mono text-[11px] font-medium text-gray-600 transition-all hover:bg-stone-100 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-300"
            >
                Cancel
            </button>
            <button
                type="submit"
                disabled={processing}
                className="flex h-8 items-center rounded-lg bg-emerald-600 px-3.5 font-mono text-[11px] font-medium text-white transition-all hover:bg-emerald-700 disabled:opacity-50"
            >
                {processing ? 'Saving...' : 'Save Changes'}
            </button>
        </div>
    );
}
