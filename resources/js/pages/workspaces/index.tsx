import AppLayout from '@/layouts/app-layout';
import { Workspace } from '@/types/models/Workspace';
import { Head } from '@inertiajs/react';

interface WorkSpacesProps {
    workspaces?: Workspace[];
}

const WorkSpaces = ({ workspaces }: WorkSpacesProps) => {
  return (
    <AppLayout>
        <Head title="WorkSpaces" />
        <div>WorkSpaces</div>
    </AppLayout>
  )
}

export default WorkSpaces