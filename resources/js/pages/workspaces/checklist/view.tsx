import PageHeader from '@/components/common/PageHeader';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { Workspace } from '@/types/models/Workspace';
import { Head, router } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { ChecklistProgressItem } from '@/components/checklist/types';

type ProgressSummary = {
    completed: number;
    total: number;
    percent: number;
};

interface Props {
    workspace: Workspace;
    items: ChecklistProgressItem[];
    progress: ProgressSummary;
}

export default function ChecklistViewPage({ workspace, items, progress }: Props) {
    return (
        <AppLayout>
            <Head title={`${workspace.name} - Checklist View`} />

            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
                <PageHeader
                    title={`${workspace.name.toUpperCase()} CHECKLIST`}
                    description="Manage your tasks efficiently and never miss a requirement."
                >
                    <Button
                        type="button"
                        onClick={() => router.get(`/workspaces/${workspace.slug}/checklist`)}
                        className="h-8 rounded-lg bg-emerald-600 px-3.5 text-[12px] font-medium text-white hover:bg-emerald-700"
                    >
                        <ArrowLeft className="mr-1.5 h-3.5 w-3.5" />
                        Go Back
                    </Button>
                </PageHeader>

                <div className="overflow-hidden rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
                    <div className="border-b border-black/6 px-5 py-3 dark:border-white/6">
                        <div className="grid grid-cols-[220px_1fr_220px] items-center gap-4">
                            <p className="font-mono text-[10px] font-medium uppercase tracking-wider text-gray-300 dark:text-gray-600">
                                Progress
                            </p>
                            <div className="h-2 overflow-hidden rounded-full bg-emerald-100 dark:bg-emerald-500/15">
                                <div
                                    className="h-full rounded-full bg-emerald-600 transition-all dark:bg-emerald-500"
                                    style={{ width: `${progress.percent}%` }}
                                />
                            </div>
                            <p className="text-right font-mono text-[10px] uppercase tracking-wider text-gray-400 dark:text-gray-500">
                                <span className="mr-1 text-gray-700 dark:text-gray-200">{progress.percent}%</span>
                                completed
                            </p>
                        </div>
                    </div>

                    <div className="divide-y divide-black/6 dark:divide-white/6">
                        {items.length > 0 ? (
                            items.map((item, index) => (
                                <div
                                    key={item.id}
                                    className={[
                                        'grid grid-cols-[220px_1fr_130px] items-center px-5 py-2.5',
                                        item.is_completed
                                            ? 'bg-emerald-500/10 dark:bg-emerald-500/10'
                                            : 'bg-transparent',
                                    ].join(' ')}
                                >
                                    <p className="font-mono text-[11px] font-semibold uppercase tracking-wider text-gray-800 dark:text-gray-100">
                                        {`Checklist ${index + 1} :`}
                                    </p>
                                    <p className="font-mono text-[11px] font-semibold uppercase tracking-wide text-gray-800 dark:text-gray-100">
                                        {item.title}
                                    </p>
                                    <p
                                        className={[
                                            'text-right font-mono text-[10px] uppercase tracking-wider',
                                            item.is_completed
                                                ? 'text-emerald-700 dark:text-emerald-300'
                                                : 'text-gray-400 dark:text-gray-500',
                                        ].join(' ')}
                                    >
                                        {item.is_completed ? 'Done' : 'Pending'}
                                    </p>
                                </div>
                            ))
                        ) : (
                            <div className="px-5 py-8 text-center">
                                <p className="font-mono text-[12px] text-gray-500 dark:text-gray-400">No checklist requirements yet.</p>
                                <p className="mt-1 font-mono text-[11px] text-gray-400 dark:text-gray-500">Go back and add checklist items first.</p>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
