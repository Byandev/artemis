import { PageAccessEditor } from '@/components/access/page-access-editor';
import PageHeader from '@/components/common/PageHeader';
import AppLayout from '@/layouts/app-layout';
import { Workspace } from '@/types/models/Workspace';
import { Head } from '@inertiajs/react';

interface Props {
    workspace: Workspace;
    member: { id: number; name: string; email: string; is_owner: boolean };
    pages: { id: number; name: string }[];
    pageIds: number[];
}

export default function AccessUser({
    workspace,
    member,
    pages,
    pageIds,
}: Props) {
    return (
        <AppLayout>
            <Head title={`${member.name} - Page Access`} />
            <div className="mx-auto w-full max-w-(--breakpoint-2xl) space-y-6 p-4 md:p-6">
                <PageHeader
                    title={`Page access — ${member.name}`}
                    description={member.email}
                />

                {member.is_owner ? (
                    <p className="text-sm text-zinc-500">
                        Workspace owners always have full access.
                    </p>
                ) : (
                    <PageAccessEditor
                        pages={pages}
                        initialPageIds={pageIds}
                        submitUrl={`/workspaces/${workspace.slug}/access/users/${member.id}`}
                        backUrl={`/workspaces/${workspace.slug}/members`}
                    />
                )}
            </div>
        </AppLayout>
    );
}
