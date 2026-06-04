import PageHeader from '@/components/common/PageHeader';
import AppLayout from '@/layouts/app-layout';
import { Workspace } from '@/types/models/Workspace';
import { Head, router } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { CreativeForm } from './components/creative-form';
import { Reviewer } from './types';

export default function CreativeCreate({ workspace, reviewers }: { workspace: Workspace; reviewers: Reviewer[] }) {
    const baseUrl = `/workspaces/${workspace.slug}/creatives`;

    return (
        <AppLayout>
            <Head title="New Creative" />
            <div className="mx-auto w-full max-w-3xl p-4 md:p-6">
                <button onClick={() => router.visit(baseUrl)} className="mb-3 inline-flex items-center gap-1.5 font-mono text-[12px] text-gray-500 transition-colors hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200">
                    <ArrowLeft className="h-3.5 w-3.5" /> Back to Creatives
                </button>
                <PageHeader title="New Creative" description="Add a new creative to track through review and launch" />
                <CreativeForm workspace={workspace} reviewers={reviewers} />
            </div>
        </AppLayout>
    );
}
