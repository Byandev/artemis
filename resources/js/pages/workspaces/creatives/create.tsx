import PageHeader from '@/components/common/PageHeader';
import AppLayout from '@/layouts/app-layout';
import { Workspace } from '@/types/models/Workspace';
import { Head, router } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { CreativeForm } from './components/creative-form';
import { Product } from './types';

export default function CreativeCreate({
    workspace,
    products,
}: {
    workspace: Workspace;
    products: Product[];
}) {
    const baseUrl = `/workspaces/${workspace.slug}/creatives`;

    return (
        <AppLayout>
            <Head title="New Creative" />
            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
                <PageHeader
                    title="New Creative"
                    description="Add a new creative to track through review and launch"
                >
                    <button
                        onClick={() => router.visit(baseUrl)}
                        className="flex items-center gap-1.5 font-mono! text-[12px]! text-gray-400 transition-colors hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300"
                    >
                        <ArrowLeft className="h-3.5 w-3.5" /> Back to Creatives
                    </button>
                </PageHeader>
                <CreativeForm workspace={workspace} products={products} />
            </div>
        </AppLayout>
    );
}
