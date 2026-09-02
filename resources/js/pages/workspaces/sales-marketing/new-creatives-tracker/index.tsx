import PageHeader from '@/components/common/PageHeader';
import AppLayout from '@/layouts/app-layout';
import { Workspace } from '@/types/models/Workspace';
import { Head } from '@inertiajs/react';

interface Props {
    workspace: Workspace;
}

/**
 * New Creatives Tracker — scaffold. The shell matches its siblings in the
 * Sales & Marketing group: the standard page padding and a PageHeader, whose
 * rule separates the title from whatever lands below it.
 */
export default function NewCreativesTracker({ workspace }: Props) {
    return (
        <AppLayout>
            <Head title={`${workspace.name} - New Creatives Tracker`} />
            <div className="p-4 md:p-6">
                <PageHeader
                    title="New Creatives Tracker"
                    description="Track newly launched creatives"
                    stackActionsOnMobile
                />
            </div>
        </AppLayout>
    );
}
