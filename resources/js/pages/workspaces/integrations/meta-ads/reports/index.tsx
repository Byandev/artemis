import PageHeader from '@/components/common/PageHeader';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { BarChart3, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import CreateReportModal from './create-report-modal';
import { type ReportListItem, reportsUrl } from './types';

interface Props {
    workspace: { id: number; name: string; slug: string };
    reports: ReportListItem[];
}

const KIND_LABELS: Record<ReportListItem['kind'], string> = {
    top_performers: 'Top performers',
    custom_groups: 'Custom groups',
    categorization: 'Categorization',
};

export default function ReportsIndex({ workspace, reports }: Props) {
    const [createOpen, setCreateOpen] = useState(false);
    const [deleteTarget, setDeleteTarget] = useState<ReportListItem | null>(
        null,
    );

    const baseUrl = reportsUrl(workspace.slug);
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Meta Ads', href: `${baseUrl}` },
        { title: 'Reports', href: baseUrl },
    ];

    const confirmDelete = () => {
        if (!deleteTarget) return;
        router.delete(`${baseUrl}/${deleteTarget.id}`, {
            preserveScroll: true,
            onFinish: () => setDeleteTarget(null),
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Reports" />

            <div className="p-4 sm:p-6">
                <PageHeader
                    title="Reports"
                    description="Build and save custom Meta Ads reports — top performers, comparisons, and more."
                >
                    <Button size="sm" onClick={() => setCreateOpen(true)}>
                        <Plus className="mr-1 h-4 w-4" />
                        New report
                    </Button>
                </PageHeader>

                {reports.length === 0 ? (
                    <div className="flex flex-col items-center justify-center rounded-2xl border border-dashed border-black/10 py-20 text-center dark:border-white/10">
                        <div className="mb-4 flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                            <BarChart3 className="h-6 w-6" />
                        </div>
                        <p className="text-sm font-medium text-gray-700 dark:text-gray-200">
                            No reports yet
                        </p>
                        <p className="mt-1 max-w-sm text-xs text-gray-400 dark:text-gray-500">
                            Create a report to explore your top-performing ads
                            and creative with custom breakdowns and metrics.
                        </p>
                        <Button
                            className="mt-5"
                            size="sm"
                            onClick={() => setCreateOpen(true)}
                        >
                            <Plus className="mr-1 h-4 w-4" />
                            Create your first report
                        </Button>
                    </div>
                ) : (
                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        {reports.map((report) => (
                            <div
                                key={report.id}
                                className="group relative flex flex-col rounded-xl border border-black/6 bg-white p-4 transition-colors hover:border-emerald-400/50 dark:border-white/6 dark:bg-zinc-900"
                            >
                                <Link
                                    href={`${baseUrl}/${report.id}`}
                                    className="flex-1"
                                >
                                    <div className="flex items-start gap-3">
                                        <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                                            <BarChart3 className="h-4.5 w-4.5" />
                                        </span>
                                        <div className="min-w-0">
                                            <p className="truncate text-sm font-semibold text-gray-900 dark:text-gray-100">
                                                {report.name}
                                            </p>
                                            <p className="mt-0.5 text-[11px] tracking-wide text-gray-400 uppercase">
                                                {KIND_LABELS[report.kind]}
                                            </p>
                                        </div>
                                    </div>
                                    {report.description && (
                                        <p className="mt-3 line-clamp-2 text-xs text-gray-500 dark:text-gray-400">
                                            {report.description}
                                        </p>
                                    )}
                                </Link>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    className="absolute top-2 right-2 opacity-0 transition-opacity group-hover:opacity-100"
                                    onClick={() => setDeleteTarget(report)}
                                >
                                    <Trash2 className="h-4 w-4 text-gray-400 hover:text-red-500" />
                                </Button>
                            </div>
                        ))}
                    </div>
                )}
            </div>

            <CreateReportModal
                open={createOpen}
                onOpenChange={setCreateOpen}
                slug={workspace.slug}
            />

            <AlertDialog
                open={deleteTarget !== null}
                onOpenChange={(open) => !open && setDeleteTarget(null)}
            >
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Delete report?</AlertDialogTitle>
                        <AlertDialogDescription>
                            “{deleteTarget?.name}” will be permanently removed.
                            This cannot be undone.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction
                            onClick={confirmDelete}
                            className="bg-red-600 hover:bg-red-700"
                        >
                            Delete
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </AppLayout>
    );
}
