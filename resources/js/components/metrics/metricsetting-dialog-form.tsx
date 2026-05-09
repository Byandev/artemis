import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { useForm, usePage } from '@inertiajs/react';
import React, { useEffect } from 'react';
import { metricConfigs } from '@/types/metrics';
import { useRoute } from 'ziggy-js';

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
    const ziggy = (props as any).ziggy;

    // Always prioritize the workspace from the shared page props to ensure we have fresh DB data
    const workspace = (props as any).currentWorkspace || localWorkspace;

    const route = useRoute(ziggy);

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

        if (index === -1) {
            current.push(key);
        } else {
            current.splice(index, 1);
        }

        setData('allowed_metrics', current);
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md p-0 gap-0 overflow-hidden border-none shadow-2xl dark:bg-zinc-900">
                <div className="px-5 pt-5 pb-4 border-b border-black/6 dark:border-white/6">
                    <DialogHeader>
                        <DialogTitle className="text-[15px] font-semibold text-gray-900 dark:text-gray-100">
                            Workspace Metrics
                        </DialogTitle>
                        <DialogDescription className="text-[12px] text-gray-400 dark:text-gray-500 mt-0.5">
                            Select which metrics are enabled for <strong>{workspace?.name}</strong>.
                        </DialogDescription>
                    </DialogHeader>
                </div>

                <form onSubmit={handleSubmit}>
                    <div className="max-h-[60vh] overflow-y-auto px-5 py-4 space-y-4">
                        <Field label="Allowed Metrics" error={errors.allowed_metrics}>
                            <div className="grid gap-2">
                                {metricConfigs.map((metric) => (
                                    <label
                                        key={metric.key}
                                        className="flex items-center gap-3 p-3 rounded-xl border border-black/5 bg-stone-50/50 hover:bg-stone-100/50 dark:border-white/5 dark:bg-white/2 cursor-pointer transition-colors"
                                    >
                                        <input
                                            type="checkbox"
                                            // Check against the current form state
                                            checked={data.allowed_metrics.includes(metric.key)}
                                            onChange={() => toggleMetric(metric.key)}
                                            className="h-4 w-4 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500"
                                        />
                                        <div className="flex flex-col">
                                            <span className="text-[13px] font-medium text-gray-800 dark:text-gray-200">
                                                {metric.name}
                                            </span>
                                        </div>
                                    </label>
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

function Field({ label, error, children }: { label: string; error?: any; children: React.ReactNode }) {
    return (
        <div className="space-y-1.5">
            <label className="block font-mono text-[10px] font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500">
                {label}
            </label>
            {children}
            {error && <p className="font-mono text-[11px] text-red-500 mt-1">{error}</p>}
        </div>
    );
}

function Footer({ processing, onCancel }: { processing: boolean; onCancel: () => void }) {
    return (
        <div className="flex items-center justify-end gap-2 border-t border-black/6 dark:border-white/6 px-5 py-3 bg-stone-50/50 dark:bg-white/2">
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