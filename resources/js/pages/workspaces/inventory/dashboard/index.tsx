import PageHeader from '@/components/common/PageHeader';
import HighUnfulfilledTable from '@/components/inventory/dashboard/high-unfulfilled-table';
import KpiCards from '@/components/inventory/dashboard/kpi-cards';
import LowStockTable from '@/components/inventory/dashboard/low-stock-table';
import MovementChart from '@/components/inventory/dashboard/movement-chart';
import AgingPanel from '@/components/inventory/dashboard/po-flow/aging-panel';
import BottleneckPanel from '@/components/inventory/dashboard/po-flow/bottleneck-panel';
import PipelinePanel from '@/components/inventory/dashboard/po-flow/pipeline-panel';
import StageTimingsPanel from '@/components/inventory/dashboard/po-flow/stage-timings-panel';
import SupplierDeliveriesPanel from '@/components/inventory/dashboard/po-flow/supplier-deliveries-panel';
import UnfulfilledSplitPanel from '@/components/inventory/dashboard/po-flow/unfulfilled-split-panel';
import WorklistPanel from '@/components/inventory/dashboard/po-flow/worklist-panel';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Workspace } from '@/types/models/Workspace';
import { Head } from '@inertiajs/react';

interface Props {
    workspace: Workspace;
}

/**
 * Ordered as a diagnosis, not a gallery: the verdict first, then the evidence
 * behind it, then the lists someone actually works from.
 *
 * The items list's waiting-for-delivery column counts every raised purchase
 * order — right, since the quantity is genuinely committed — so an item can
 * read weeks of cover while its stock sits unpaid in an approval queue. Nothing
 * on that page is wrong, but it cannot warn you. These panels are where that
 * risk shows up, measured as time rather than quantity.
 */
export default function InventoryDashboard({ workspace }: Props) {
    const slug = workspace.slug;

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'Inventory Dashboard',
            href: `/workspaces/${slug}/inventory/dashboard`,
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${workspace.name} - Inventory Dashboard`} />
            {/* The layout adds no padding of its own, so the last panel would
                otherwise sit against the bottom of the scroll area. */}
            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 pb-6 md:p-6 md:pb-6">
                <PageHeader
                    title="Inventory Dashboard"
                    description="Where ordered stock is sitting, how long it has been there, and what to clear first."
                />

                {/* gap-6 rather than a margin on each card: same 24px between
                    panels, without a dangling one below the last. */}
                <div className="flex flex-col gap-6">
                    {/* 1. The answer, before any of the evidence. */}
                    <BottleneckPanel slug={slug} />

                    {/* 2. The standing figures every other panel refers back to. */}
                    <KpiCards slug={slug} />

                    {/* 3. Where the stock is, and how stale each pile has got. */}
                    <PipelinePanel slug={slug} />
                    <div className="grid gap-x-4 gap-y-6 xl:grid-cols-2">
                        <AgingPanel slug={slug} />
                        <StageTimingsPanel slug={slug} />
                    </div>

                    {/* 4. The two worklists: ours to clear, then theirs to chase. */}
                    <WorklistPanel slug={slug} />
                    <SupplierDeliveriesPanel slug={slug} />

                    {/* 5. What the shortfall is actually made of. */}
                    <UnfulfilledSplitPanel slug={slug} />
                    <div className="grid gap-x-4 gap-y-6 lg:grid-cols-2">
                        <HighUnfulfilledTable slug={slug} />
                        <LowStockTable slug={slug} />
                    </div>

                    {/* Below the fold: throughput is context, not a signal. It
                        reports what already happened, and answered none of the
                        questions above. */}
                    <div className="mt-2 flex items-center gap-3 text-[10px] font-medium tracking-[0.12em] text-gray-400 uppercase dark:text-gray-500">
                        <span className="h-px flex-1 bg-black/6 dark:bg-white/6" />
                        Background
                        <span className="h-px flex-1 bg-black/6 dark:bg-white/6" />
                    </div>
                    <MovementChart slug={slug} />
                </div>
            </div>
        </AppLayout>
    );
}
