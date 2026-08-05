import PageHeader from '@/components/common/PageHeader';
import HighUnfulfilledTable from '@/components/inventory/dashboard/high-unfulfilled-table';
import KpiCards from '@/components/inventory/dashboard/kpi-cards';
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
                <div className="flex flex-col gap-3">
                    <KpiCards slug={slug} />
                    <MovementChart slug={slug} />
                    <OpenPosTable slug={slug} />
                    {/* Half width from lg up — a two-column SKU/count table
                        reads worse stretched across the page than the wide
                        tables above it. Full width on narrow screens. */}
                    <div className="grid gap-3 lg:grid-cols-2">
                        <HighUnfulfilledTable slug={slug} />
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
