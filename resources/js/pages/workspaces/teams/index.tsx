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
import { PERMISSIONS } from '@/constants/permissions';
import { usePermission } from '@/hooks/use-permission';
import AppLayout from '@/layouts/app-layout';
import { toFrontendSort } from '@/lib/sort';
import teamsApi from '@/routes/api/workspaces/teams';
import { PaginatedData, User } from '@/types';
import { Workspace } from '@/types/models/Workspace';
import { Head, Link } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import axios from 'axios';
import { omit } from 'lodash';
import {
    Calendar,
    LayoutGrid,
    Megaphone,
    MoreHorizontal,
    Pencil,
    Search,
    Trash2,
} from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { toast } from 'sonner';

interface Team {
    id: number;
    name: string;
    members_count: number;
    members: User[];
}

type FetchParams = {
    sort?: string | null;
    search?: string;
    page?: number | string;
    per_page?: number | string;
};

interface Props {
    workspace: Workspace;
    workspaceMembers: User[];
    query?: {
        sort?: string | null;
        per_page?: number | string;
        page?: number | string;
        filter?: { search?: string };
    };
}

export default function TeamsIndex({
    workspace,
    workspaceMembers,
    query,
}: Props) {
    const initialSorting = useMemo(
        () => toFrontendSort(query?.sort ?? null),
        [query?.sort],
    );

    const [createDialogOpen, setCreateDialogOpen] = useState(false);
    const [editingTeam, setEditingTeam] = useState<Team | null>(null);
    const [teamToDelete, setTeamToDelete] = useState<Team | null>(null);
    const [searchValue, setSearchValue] = useState(query?.filter?.search ?? '');

    const [teams, setTeams] = useState<PaginatedData<Team> | null>(null);
    const [loading, setLoading] = useState(true);

    // The params of the last fetch, so a refetch after a save or delete keeps
    // the page, sort and search the user was looking at.
    const paramsRef = useRef<FetchParams>({
        sort: query?.sort ?? null,
        search: query?.filter?.search ?? '',
        page: query?.page ?? 1,
        per_page: query?.per_page,
    });
    // Drops responses from fetches a newer one has superseded.
    const requestIdRef = useRef(0);

    const fetchTeams = useCallback(
        async (next: FetchParams = {}) => {
            const params = { ...paramsRef.current, ...next };
            paramsRef.current = params;

            const queryParams = {
                sort: params.sort || undefined,
                'filter[search]': params.search || undefined,
                page: params.page || undefined,
                per_page: params.per_page || undefined,
            };

            // Mirror the params into the address bar so a reload lands on the
            // same view.
            const pageUrl = new URL(window.location.href);
            pageUrl.search = '';
            Object.entries(queryParams).forEach(([key, value]) => {
                if (value !== undefined) {
                    pageUrl.searchParams.set(key, String(value));
                }
            });
            window.history.replaceState(window.history.state, '', pageUrl);

            const requestId = ++requestIdRef.current;
            setLoading(true);
            try {
                const res = await axios.get<PaginatedData<Team>>(
                    teamsApi.index.url({ workspace }),
                    { params: queryParams },
                );
                if (requestId === requestIdRef.current) {
                    setTeams(res.data);
                }
            } catch {
                if (requestId === requestIdRef.current) {
                    toast.error('Failed to load teams.');
                }
            } finally {
                if (requestId === requestIdRef.current) {
                    setLoading(false);
                }
            }
        },
        [workspace],
    );

    const isFirstSearch = useRef(true);
    useEffect(() => {
        // The first run loads the initial page; later runs are the debounced
        // search, which resets to page 1.
        if (isFirstSearch.current) {
            isFirstSearch.current = false;
            fetchTeams();
            return;
        }
        const timer = setTimeout(() => {
            fetchTeams({ search: searchValue, page: 1 });
        }, 500);
        return () => clearTimeout(timer);
    }, [searchValue, fetchTeams]);

    const canCreateTeams = usePermission(PERMISSIONS.CreateTeams);
    const canEditTeams = usePermission(PERMISSIONS.EditTeams);
    const canDeleteTeams = usePermission(PERMISSIONS.DeleteTeams);
    const canManageSchedule = usePermission(PERMISSIONS.ManageSchedule);
    const showActions = canManageSchedule || canEditTeams || canDeleteTeams;

    const columns: ColumnDef<Team>[] = [
        {
            accessorKey: 'name',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader column={column} title="Team Name" />
            ),
            cell: ({ row }) => (
                <span className="text-[12px] font-medium text-gray-800 dark:text-gray-200">
                    {row.original.name}
                </span>
            ),
        },
        {
            accessorKey: 'members_count',
            header: ({ column }) => (
                <SortableHeader column={column} title="Members" />
            ),
            cell: ({ row }) => {
                const count = row.original.members_count;
                const names = row.original.members
                    ?.map((m) => m.name)
                    .join(', ');
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
        ...(showActions
            ? [
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
                                      <DropdownMenuContent
                                          align="end"
                                          className="w-36"
                                      >
                                          {canManageSchedule && (
                                              <DropdownMenuItem asChild>
                                                  <Link
                                                      href={`/workspaces/${workspace.slug}/teams/${team.id}/schedule`}
                                                  >
                                                      <Calendar />
                                                      Schedule
                                                  </Link>
                                              </DropdownMenuItem>
                                          )}
                                          {canEditTeams && (
                                              <DropdownMenuItem asChild>
                                                  <Link
                                                      href={`/workspaces/${workspace.slug}/teams/${team.id}/shops`}
                                                  >
                                                      <LayoutGrid />
                                                      Shops
                                                  </Link>
                                              </DropdownMenuItem>
                                          )}
                                          {canEditTeams && (
                                              <DropdownMenuItem asChild>
                                                  <Link
                                                      href={`/workspaces/${workspace.slug}/teams/${team.id}/ad-accounts`}
                                                  >
                                                      <Megaphone />
                                                      Ad Accounts
                                                  </Link>
                                              </DropdownMenuItem>
                                          )}
                                          {canEditTeams && (
                                              <DropdownMenuItem
                                                  onClick={() =>
                                                      setEditingTeam(team)
                                                  }
                                              >
                                                  <Pencil />
                                                  Edit
                                              </DropdownMenuItem>
                                          )}
                                          {canEditTeams && canDeleteTeams && (
                                              <DropdownMenuSeparator />
                                          )}
                                          {canDeleteTeams && (
                                              <DropdownMenuItem
                                                  variant="destructive"
                                                  onClick={() =>
                                                      setTeamToDelete(team)
                                                  }
                                              >
                                                  <Trash2 />
                                                  Delete
                                              </DropdownMenuItem>
                                          )}
                                      </DropdownMenuContent>
                                  </DropdownMenu>
                              </div>
                          );
                      },
                  } as ColumnDef<Team>,
              ]
            : []),
    ];

    return (
        <AppLayout>
            <Head title={`${workspace.name} - Teams`} />
            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
                <PageHeader
                    title="Teams"
                    description="Organize members into teams for better collaboration"
                >
                    {canCreateTeams && (
                        <button
                            onClick={() => setCreateDialogOpen(true)}
                            className="flex h-8 items-center rounded-lg bg-emerald-600 px-3.5 font-mono! text-[12px]! font-medium text-white transition-all hover:bg-emerald-700"
                        >
                            Create Team
                        </button>
                    )}
                </PageHeader>

                <div className="mb-3 flex items-center gap-2">
                    <div className="relative w-full max-w-xs">
                        <Search className="pointer-events-none absolute top-1/2 left-3 h-3.5 w-3.5 -translate-y-1/2 text-gray-400 dark:text-gray-500" />
                        <input
                            className="h-9 w-full rounded-[10px] border border-black/6 bg-stone-100 pr-3 pl-8 font-mono! text-[12px]! text-gray-800 transition-all outline-none placeholder:text-gray-400 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-100 dark:placeholder:text-gray-600 dark:focus:border-emerald-400"
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
                        data={teams?.data ?? []}
                        loading={loading}
                        initialSorting={initialSorting}
                        meta={teams ? omit(teams, ['data']) : undefined}
                        onFetch={(params) => {
                            fetchTeams({
                                sort: params?.sort as string | null,
                                page: params?.page ?? 1,
                                per_page: params?.per_page ?? undefined,
                            });
                        }}
                    />
                </div>

                {(canCreateTeams || canEditTeams) && (
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
                        onSaved={() => fetchTeams()}
                    />
                )}

                {canDeleteTeams && (
                    <DeleteTeamDialog
                        team={teamToDelete}
                        workspace={workspace}
                        onClose={() => setTeamToDelete(null)}
                        onDeleted={() =>
                            // Step back a page when the last row on it went.
                            fetchTeams(
                                teams &&
                                    teams.data.length === 1 &&
                                    teams.current_page > 1
                                    ? { page: teams.current_page - 1 }
                                    : {},
                            )
                        }
                    />
                )}
            </div>
        </AppLayout>
    );
}
