import PageHeader from '@/components/common/PageHeader';
import AppLayout from '@/layouts/app-layout';
import { Workspace } from '@/types/models/Workspace';
import { Head, router } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { CreativeForm } from './components/creative-form';
import { Creative, Product, Reviewer } from './types';

export default function CreativeEdit({ workspace, creative, reviewers, products }: { workspace: Workspace; creative: Creative; reviewers: Reviewer[]; products: Product[] }) {
    const baseUrl = `/workspaces/${workspace.slug}/creatives`;

    return (
        <AppLayout>
            <Head title={`Edit · ${creative.name}`} />
            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
                <PageHeader title="Edit Creative" description={creative.name}>
                    <button onClick={() => router.visit(baseUrl)} className="flex items-center gap-1.5 font-mono! text-[12px]! text-gray-400 transition-colors hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300">
                        <ArrowLeft className="h-3.5 w-3.5" /> Back to Creatives
                    </button>
                </PageHeader>
                <CreativeForm workspace={workspace} creative={creative} reviewers={reviewers} products={products} />
            </div>
        </AppLayout>
    );
}
