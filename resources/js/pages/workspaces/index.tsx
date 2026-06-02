import WorkspaceCard from '@/components/workspaces/WorkspaceCard';
import AuthLayout from '@/layouts/auth-layout';
import { Workspace } from '@/types/models/Workspace';
import { Head } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';

interface WorkspaceWithCount extends Workspace {
    users_count: number;
}

interface WorkSpacesProps {
    workspaces: WorkspaceWithCount[];
}

const WorkSpaces = ({ workspaces }: WorkSpacesProps) => {
    return (
        <AuthLayout
            title="Choose a workspace"
            description="Select one of your workspaces to continue."
        >
            <Head title="WorkSpaces" />
            <button
                onClick={() => window.history.back()}
                className="mb-4 inline-flex items-center gap-1.5 text-[13px] text-gray-500 transition-colors hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100"
            >
                <ArrowLeft className="h-3.5 w-3.5" />
                Go back
            </button>
            <div className="max-h-[50vh] space-y-3 overflow-y-auto">
                {workspaces?.map((workspace) => (
                    <WorkspaceCard key={workspace.id} workspace={workspace} />
                ))}
            </div>
        </AuthLayout>
    );
};

export default WorkSpaces;
