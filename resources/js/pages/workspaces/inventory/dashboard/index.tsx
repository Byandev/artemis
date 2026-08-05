import PageHeader from '@/components/common/PageHeader';
import HighUnfulfilledTable from '@/components/inventory/dashboard/high-unfulfilled-table';
import KpiCards from '@/components/inventory/dashboard/kpi-cards';
import LowStockTable from '@/components/inventory/dashboard/low-stock-table';
import MovementChart from '@/components/inventory/dashboard/movement-chart';
import OpenPosTable from '@/components/inventory/dashboard/open-pos-table';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Workspace } from '@/types/models/Workspace';
import { Head } from '@inertiajs/react';

interface Props {
    workspace: Workspace;
}

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
            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
                <PageHeader
                    title="Inventory Dashboard"
                    description="Stock health, movement, purchase orders and audit at a glance."
                />

                {/* Every figure is a snapshot of the current ledger, so there is
                    no date filter — each tile owns its own fetch. */}
                {/* gap-6 rather than a margin on each card: same 24px between
                    panels, without a dangling one below the last. */}
                <div className="flex flex-col gap-6">
                    <KpiCards slug={slug} />
                    <MovementChart slug={slug} />
                    <OpenPosTable slug={slug} />
                    {/* Two half-width tables side by side from lg up — both are
                        narrow SKU/count lists that read worse stretched across
                        the page. They stack full width on narrow screens. */}
                    {/* Tighter between the pair than the 6 separating panels
                        vertically; stacked on narrow screens they fall back to
                        the page's rhythm. */}
                    <div className="grid gap-x-4 gap-y-6 lg:grid-cols-2">
                        <HighUnfulfilledTable slug={slug} />
                        <LowStockTable slug={slug} />
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
