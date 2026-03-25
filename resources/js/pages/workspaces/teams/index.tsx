import PageHeader from '@/components/common/PageHeader';
import { DeleteTeamDialog } from '@/components/teams/delete-team-dialog';
import { TeamFormDialog } from '@/components/teams/team-form-dialog';
import { Button } from '@/components/ui/button';
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
import { Head, router } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import { omit } from 'lodash';
import { Edit, MoreHorizontal, Search, Trash2 } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { Workspace } from '@/types/models/Workspace';

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
    isAdmin: boolean;
    query?: {
        sort?: string | null;
        perPage?: number | string;
        page?: number | string;
        filter?: {
            search?: string;
        };
    };
}

export default function TeamsIndex({ workspace, teams, workspaceMembers, isAdmin, query }: Props) {
    const initialSorting = useMemo(() => {
        return toFrontendSort(query?.sort ?? null);
    }, [query?.sort]);

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
                    page: searchValue ? 1 : query?.page ?? 1
                },
                {
                    preserveState: true,
                    replace: true,
                    preserveScroll: true,
                    only: ['teams'],
                },
            );
        }, 500);

        return () => clearTimeout(timer);
    }, [searchValue]);

    const handleEdit = (team: Team) => {
        setEditingTeam(team);
    };

    const handleDelete = (team: Team) => {
        setTeamToDelete(team);
    };

    const columns: ColumnDef<Team>[] = [
        {
            accessorKey: 'id',
            header: ({ column }) => (
                <SortableHeader column={column} title={'ID'} />
            ),
        },
        {
            accessorKey: 'name',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader column={column} title={'Team Name'} />
            ),
        },
        {
            accessorKey: 'members_count',
            header: ({ column }) => (
                <SortableHeader column={column} title={'Members'} />
            ),
            cell: ({ row }) => {
                const count = row.original.members_count;
                const memberNames = row.original.members?.map(m => m.name).join(', ') || 'No members';
                return (
                    <div>
                        <span className="font-medium">{count} {count === 0 || count === 1 ? 'member' : 'members'}</span>
                        {count > 0 && <span className="text-xs text-muted-foreground ml-2">({memberNames.length > 50 ? memberNames.substring(0, 50) + '...' : memberNames})</span>}
                    </div>
                );
            },
        },
        {
            id: 'actions',
            cell: ({ row }) => {
                const team = row.original;

                if (!isAdmin) return null;

                return (
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button variant="ghost" size="sm">
                                <MoreHorizontal className="h-4 w-4" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuItem onClick={() => handleEdit(team)}>
                                <Edit className="mr-2 h-4 w-4" />
                                Edit
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem
                                onClick={() => handleDelete(team)}
                                className="text-destructive focus:text-destructive"
                            >
                                <Trash2 className="mr-2 h-4 w-4" />
                                Delete
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                );
            },
        },
    ];

    return (
        <AppLayout>
            <Head title={`${workspace.name} - Teams`} />
            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
                <PageHeader title="Teams" description="Organize members into teams for better collaboration">
                    {isAdmin && (
                        <Button size="sm" onClick={() => setCreateDialogOpen(true)}>
                            Create Team
                        </Button>
                    )}
                </PageHeader>

                <div className="mb-3 flex items-center gap-2">
                    <div className="relative w-full max-w-xs">
                        <Search className="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-gray-400 dark:text-gray-500" />
                        <input
                            className="h-9 w-full rounded-[10px] border border-black/6 dark:border-white/6 bg-stone-100 dark:bg-zinc-800 pl-8 pr-3 font-[family-name:--font-dm-mono] text-[12px] text-gray-800 dark:text-gray-100 placeholder:text-gray-400 dark:placeholder:text-gray-600 outline-none transition-all focus:border-emerald-500 dark:focus:border-emerald-400 focus:ring-2 focus:ring-emerald-500/15"
                            placeholder="Search team name..."
                            value={searchValue}
                            onChange={(e) => setSearchValue(e.target.value)}
                        />
                    </div>
                </div>

                <div className="rounded-[14px] border border-black/6 dark:border-white/6 bg-white dark:bg-zinc-900">
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
                                    page: params?.page ?? 1
                                },
                                {
                                    preserveState: true,
                                    replace: true,
                                    preserveScroll: true,
                                },
                            );
                        }}
                    />
                </div>

                {/* Create/Edit Team Dialog */}
                <TeamFormDialog
                    open={createDialogOpen || editingTeam !== null}
                    onOpenChange={(open) => {
                        if (!open) {
                            setCreateDialogOpen(false);
                            setEditingTeam(null);
                        }
                    }}
                    team={editingTeam}
                    workspace={workspace}
                    workspaceMembers={workspaceMembers}
                />

                {/* Delete Confirmation Dialog */}
                <DeleteTeamDialog
                    team={teamToDelete}
                    workspace={workspace}
                    onClose={() => setTeamToDelete(null)}
                />
            </div>
        </AppLayout>
    );
}
