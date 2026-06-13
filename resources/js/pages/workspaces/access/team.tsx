import { PageAccessEditor } from '@/components/access/page-access-editor';
import PageHeader from '@/components/common/PageHeader';
import AppLayout from '@/layouts/app-layout';
import { Workspace } from '@/types/models/Workspace';
import { Head } from '@inertiajs/react';

interface Props {
    workspace: Workspace;
    team: { id: number; name: string; members_count: number };
    pages: { id: number; name: string }[];
    pageIds: number[];
}

export default function AccessTeam({ workspace, team, pages, pageIds }: Props) {
    return (
        <AppLayout>
            <Head title={`${team.name} - Page Access`} />
            <div className="mx-auto w-full max-w-(--breakpoint-2xl) space-y-6 p-4 md:p-6">
                <PageHeader
                    title={`Page access — ${team.name}`}
                    description={`Applies to every member of this team (${team.members_count} ${team.members_count === 1 ? 'member' : 'members'}), on top of their personal access.`}
                />

                <PageAccessEditor
                    pages={pages}
                    initialPageIds={pageIds}
                    submitUrl={`/workspaces/${workspace.slug}/access/teams/${team.id}`}
                    backUrl={`/workspaces/${workspace.slug}/teams`}
                />
            </div>
        </AppLayout>
    );
}
