import AuthLayout from '@/layouts/auth-layout';
import { Workspace } from '@/types/models/Workspace';
import { Head } from '@inertiajs/react';
import WorkspaceCard from './partials/WorkspaceCard';

interface WorkSpacesProps {
  workspaces?: Workspace[];
}

const WorkSpaces = ({ workspaces }: WorkSpacesProps) => {
  return (
    <AuthLayout title="Choose a workspace" description="Select one of your workspaces to continue.">
      <Head title="WorkSpaces" />
      <div className="space-y-4 overflow-y-auto max-h-[40vh]">
        {workspaces?.map((workspace) => (
          <WorkspaceCard key={workspace.id} workspace={workspace} />
        ))}
      </div>
    </AuthLayout>
  )
}

export default WorkSpaces