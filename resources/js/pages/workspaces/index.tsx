import WorkspaceCard from '@/components/workspaces/WorkspaceCard';
import AuthLayout from '@/layouts/auth-layout';
import { Workspace } from '@/types/models/Workspace';
import { Head } from '@inertiajs/react';

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
            <div className="max-h-[50vh] space-y-3 overflow-y-auto">
                {workspaces?.map((workspace) => (
                    <WorkspaceCard key={workspace.id} workspace={workspace} />
                ))}
            </div>
        </AuthLayout>
    );
};

export default WorkSpaces;
