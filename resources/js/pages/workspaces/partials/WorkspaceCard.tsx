import { Button } from '@/components/ui/button';
import { Workspace } from '@/types/models/Workspace'
import { Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';

interface WorkspaceCardProps {
    workspace: Workspace;
}

const WorkspaceCard = ({ workspace }: WorkspaceCardProps) => {
    return (
        <div className="flex items-center justify-between p-4 border rounded-lg">
            <div className='flex items-center'>
                <div className="flex items-center">
                    <div
                        className="w-10 h-10 mr-3 flex items-center justify-center rounded-full bg-indigo-500 text-white text-sm font-semibold flex-shrink-0"
                        aria-hidden="true"
                        title={workspace.name ?? 'Workspace'}
                    >
                        {workspace.name ? workspace.name.charAt(0).toUpperCase() : 'W'}
                    </div>
                </div>
                <h2 className="text-xl font-semibold">{workspace.name}</h2>
            </div>

            <Button variant="outline">
                <Link
                    href={`/workspaces/${workspace.slug}/switch`}
                    method="post"
                    as="button"
                    className="flex items-center"
                >
                    Open
                    <ArrowRight className="ml-2 h-4 w-4" />
                </Link>
            </Button>

        </div>
    )
}

export default WorkspaceCard