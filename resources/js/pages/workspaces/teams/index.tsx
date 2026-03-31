import PageHeader from '@/components/common/PageHeader';
import { DeleteTeamDialog } from '@/components/teams/delete-team-dialog';
import { TeamFormDialog } from '@/components/teams/team-form-dialog';
import { DataTable, SortableHeader } from '@/components/ui/data-table';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import AppLayout from '@/layouts/app-layout';
import { toFrontendSort } from '@/lib/sort';
import { PaginatedData, User } from '@/types';
import { Workspace } from '@/types/models/Workspace';
import { Head, router } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import { omit } from 'lodash';
import { MoreHorizontal, Pencil, Search, Trash2 } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

interface Team {
    id: number;
    name: string;
    members_count: number;
    members: User[];
}

interface Props {
    workspace: Workspace;
    teams: PaginatedData<Team>;
    workspaceMembers: User[];
    query?: {
        sort?: string | null;
        perPage?: number | string;
        page?: number | string;
        filter?: { search?: string };
    };
}

export default function TeamsIndex({ workspace, teams, workspaceMembers, query }: Props) {
    const initialSorting = useMemo(() => toFrontendSort(query?.sort ?? null), [query?.sort]);

    const [createDialogOpen, setCreateDialogOpen] = useState(false);
    const [editingTeam, setEditingTeam] = useState<Team | null>(null);
    const [teamToDelete, setTeamToDelete] = useState<Team | null>(null);
    const [searchValue, setSearchValue] = useState(query?.filter?.search ?? '');

    useEffect(() => {
        const timer = setTimeout(() => {
            router.get(
                `/workspaces/${workspace.slug}/teams`,
                {
                    sort: query?.sort,
                    'filter[search]': searchValue || undefined,
                    page: searchValue ? 1 : query?.page ?? 1,
                },
                { preserveState: true, replace: true, preserveScroll: true, only: ['teams'] },
            );
        }, 500);
        return () => clearTimeout(timer);
    }, [searchValue]);

    const columns: ColumnDef<Team>[] = [
        {
            accessorKey: 'name',
            enableSorting: true,
            header: ({ column }) => <SortableHeader column={column} title="Team Name" />,
            cell: ({ row }) => (
                <span className="text-[12px] font-medium text-gray-800 dark:text-gray-200">{row.original.name}</span>
            ),
        },
        {
            accessorKey: 'members_count',
            header: ({ column }) => <SortableHeader column={column} title="Members" />,
            cell: ({ row }) => {
                const count = row.original.members_count;
                const names = row.original.members?.map((m) => m.name).join(', ');
                return (
                    <div className="space-y-0.5">
                        <span className="inline-flex items-center gap-1.5 rounded-full bg-stone-100 px-2.5 py-1 font-mono text-[11px] font-medium text-gray-600 dark:bg-zinc-800 dark:text-gray-400">
                            {count} {count === 1 ? 'member' : 'members'}
                        </span>
                        {count > 0 && (
                            <p className="max-w-[260px] truncate font-mono text-[11px] text-gray-400 dark:text-gray-500">
                                {names}
                            </p>
                        )}
                    </div>
                );
            },
        },
        {
            id: 'actions',
            cell: ({ row }) => {
                const team = row.original;
                return (
                    <div className="flex justify-end">
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <button className="flex h-7 w-7 items-center justify-center rounded-lg border border-black/6 bg-stone-50 text-gray-400 transition-all hover:border-black/12 hover:bg-stone-100 hover:text-gray-600 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-500 dark:hover:border-white/12 dark:hover:bg-zinc-700 dark:hover:text-gray-300">
                                    <MoreHorizontal className="h-3.5 w-3.5" />
                                </button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" className="w-36">
                                <DropdownMenuItem onClick={() => setEditingTeam(team)}>
                                    <Pencil />
                                    Edit
                                </DropdownMenuItem>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem variant="destructive" onClick={() => setTeamToDelete(team)}>
                                    <Trash2 />
                                    Delete
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                );
            },
        },
    ];

    return (
        <AppLayout>
            <Head title={`${workspace.name} - Teams`} />
            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
                <PageHeader title="Teams" description="Organize members into teams for better collaboration">
                        <button
                            onClick={() => setCreateDialogOpen(true)}
                            className="flex h-8 items-center rounded-lg bg-emerald-600 px-3.5 font-mono! text-[12px]! font-medium text-white transition-all hover:bg-emerald-700"
                        >
                            Create Team
                        </button>
                </PageHeader>

                <div className="mb-3 flex items-center gap-2">
                    <div className="relative w-full max-w-xs">
                        <Search className="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-gray-400 dark:text-gray-500" />
                        <input
                            className="h-9 w-full rounded-[10px] border border-black/6 bg-stone-100 pl-8 pr-3 font-mono! text-[12px]! text-gray-800 outline-none transition-all placeholder:text-gray-400 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-100 dark:placeholder:text-gray-600 dark:focus:border-emerald-400"
                            placeholder="Search teams…"
                            value={searchValue}
                            onChange={(e) => setSearchValue(e.target.value)}
                        />
                    </div>
                </div>

                <div className="rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
                    <DataTable
                        columns={columns}
                        enableInternalPagination={false}
                        data={teams.data || []}
                        initialSorting={initialSorting}
                        meta={{ ...omit(teams, ['data']) }}
                        onFetch={(params) => {
                            router.get(
                                `/workspaces/${workspace.slug}/teams`,
                                {
                                    sort: params?.sort,
                                    'filter[search]': searchValue || undefined,
                                    page: params?.page ?? 1,
                                },
                                { preserveState: true, replace: true, preserveScroll: true },
                            );
                        }}
                    />
                </div>

                <TeamFormDialog
                    open={createDialogOpen || editingTeam !== null}
                    onOpenChange={(open) => {
                        if (!open) { setCreateDialogOpen(false); setEditingTeam(null); }
                    }}
                    team={editingTeam}
                    workspace={workspace}
                    workspaceMembers={workspaceMembers}
                />

                <DeleteTeamDialog
                    team={teamToDelete}
                    workspace={workspace}
                    onClose={() => setTeamToDelete(null)}
                />
            </div>
        </AppLayout>
    );
}
