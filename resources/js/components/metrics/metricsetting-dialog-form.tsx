import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { SharedData } from '@/types';
import { groupedMetrics } from '@/types/metrics';
import { useForm, usePage } from '@inertiajs/react';
import React, { useEffect } from 'react';

interface MetricSetting {
    allowed_metrics?: string[];
    default_metrics?: string[];
}

interface Workspace {
    id: number;
    name: string;
    slug: string;
    metric_settings?: { metric_key: string }[];
    metric_setting?: MetricSetting;
    metricSetting?: MetricSetting;
}

interface MetricSettingsPageProps extends SharedData {
    currentWorkspace?: Workspace;
}

interface Props {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    workspace: Workspace | null;
}

export function MetricSettingDialog({
                                        open,
                                        onOpenChange,
                                        workspace: localWorkspace,
                                    }: Props) {
    const { currentWorkspace } = usePage<MetricSettingsPageProps>().props;

    // Always prioritize the workspace from the shared page props to ensure we have fresh DB data
    const workspace = currentWorkspace || localWorkspace;

    const { data, setData, put, processing, errors } = useForm({
        allowed_metrics: [] as string[],
        default_metrics: [] as string[],
    });

    useEffect(() => {
        if (open && workspace) {
            // Check both snake_case and camelCase to match your Middleware/Model naming
            const setting = workspace.metric_setting || workspace.metricSetting;

            if (setting?.allowed_metrics) {
                setData({
                    allowed_metrics: setting.allowed_metrics,
                    default_metrics:
                        setting.default_metrics || setting.allowed_metrics,
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
            onSuccess: () => {
                onOpenChange(false);
            },
        });
    };

    const toggleMetric = (key: string) => {
        const current = [...data.allowed_metrics];
        const index = current.indexOf(key);

        if (index === -1) {
            current.push(key);
        } else {
            current.splice(index, 1);
        }

        setData('allowed_metrics', current);
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="gap-0 overflow-hidden border-none p-0 shadow-2xl sm:max-w-6xl dark:bg-zinc-900">
                <div className="border-b border-black/6 px-5 pt-4 pb-3 dark:border-white/6">
                    <DialogHeader>
                        <DialogTitle className="text-[15px] font-semibold text-gray-900 dark:text-gray-100">
                            Workspace Metrics
                        </DialogTitle>
                        <DialogDescription className="mt-0.5 text-[12px] text-gray-400 dark:text-gray-500">
                            Select which metrics are enabled for{' '}
                            <strong>{workspace?.name}</strong>.
                        </DialogDescription>
                    </DialogHeader>
                </div>

                <form onSubmit={handleSubmit}>
                    <div className="px-5 py-3">
                        <Field
                            label="Allowed Metrics"
                            error={errors.allowed_metrics}
                        >
                            <div className="columns-1 gap-3 sm:columns-2 lg:columns-3">
                                {groupedMetrics.map((group) => (
                                    <section
                                        key={group.key}
                                        className="mb-3 break-inside-avoid rounded-lg border border-black/5 bg-stone-50/50 p-2.5 dark:border-white/5 dark:bg-white/2"
                                    >
                                        <h3 className="mb-1.5 font-mono text-[9px] font-medium tracking-wider text-gray-400 uppercase dark:text-gray-500">
                                            {group.label}
                                        </h3>
                                        <div className="grid gap-1">
                                            {group.metrics.map((metric) => {
                                                const checked =
                                                    data.allowed_metrics.includes(
                                                        metric.key,
                                                    );

                                                return (
                                                    <label
                                                        key={metric.key}
                                                        className={[
                                                            'flex min-h-7 cursor-pointer items-center gap-2 rounded-md px-2 py-1 transition-colors',
                                                            checked
                                                                ? 'bg-emerald-500/[0.08] dark:bg-emerald-500/[0.10]'
                                                                : 'hover:bg-black/[0.03] dark:hover:bg-white/[0.04]',
                                                        ].join(' ')}
                                                    >
                                                        <input
                                                            type="checkbox"
                                                            checked={checked}
                                                            onChange={() =>
                                                                toggleMetric(
                                                                    metric.key,
                                                                )
                                                            }
                                                            className="h-3.5 w-3.5 shrink-0 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500"
                                                        />
                                                        <span
                                                            className={[
                                                                'text-[11px] leading-tight',
                                                                checked
                                                                    ? 'font-medium text-emerald-700 dark:text-emerald-400'
                                                                    : 'font-medium text-gray-700 dark:text-gray-300',
                                                            ].join(' ')}
                                                        >
                                                            {metric.name}
                                                        </span>
                                                    </label>
                                                );
                                            })}
                                        </div>
                                    </section>
                                ))}
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

function Field({
                   label,
                   error,
                   children,
               }: {
    label: string;
    error?: string;
    children: React.ReactNode;
}) {
    return (
        <div className="space-y-1.5">
            <label className="block font-mono text-[10px] font-medium tracking-wider text-gray-400 uppercase dark:text-gray-500">
                {label}
            </label>
            {children}
            {error && (
                <p className="mt-1 font-mono text-[11px] text-red-500">
                    {error}
                </p>
            )}
        </div>
    );
}

function Footer({
                    processing,
                    onCancel,
                }: {
    processing: boolean;
    onCancel: () => void;
}) {
    return (
        <div className="flex items-center justify-end gap-2 border-t border-black/6 bg-stone-50/50 px-5 py-3 dark:border-white/6 dark:bg-white/2">
            <button
                type="button"
                onClick={onCancel}
                className="flex h-9 items-center rounded-lg border border-black/8 bg-white px-4 font-mono text-[12px] font-medium text-gray-600 transition-all hover:bg-stone-100 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-300"
            >
                Cancel
            </button>
            <button
                type="submit"
                disabled={processing}
                className="flex h-9 items-center rounded-lg bg-emerald-600 px-4 font-mono text-[12px] font-medium text-white transition-all hover:bg-emerald-700 disabled:opacity-50"
            >
                {processing ? 'Saving...' : 'Save Changes'}
            </button>
        </div>
    );
}
