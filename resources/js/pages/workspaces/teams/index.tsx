import { Head, router } from '@inertiajs/react';
import { useMemo, useState, useEffect, useCallback } from 'react';
import { ColumnDef } from '@tanstack/react-table';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { DataTable } from '@/components/ui/data-table';
import { Search, ArrowUpDown, ArrowUp, ArrowDown, X, Loader2 } from 'lucide-react';
import { TeamFormDialog } from '@/components/teams/team-form-dialog';
import { DeleteTeamDialog } from '@/components/teams/delete-team-dialog';

interface User {
    id: number;
    name: string;
    email: string;
}

interface Team {
    id: number;
    name: string;
    members_count: number;
    members: User[];
}

interface Workspace {
    id: number;
    name: string;
    slug: string;
}

interface Filters {
    search: string;
    sort: string;
    direction: string;
}

interface PaginationLinks {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedTeams {
    data: Team[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: PaginationLinks[];
    from: number;
    to: number;
}

interface Props {
    workspace: Workspace;
    teams: PaginatedTeams;
    workspaceMembers: User[];
    isAdmin: boolean;
    filters: Filters;
}

export default function TeamsIndex({ workspace, teams, workspaceMembers, isAdmin, filters }: Props) {
    const [createDialogOpen, setCreateDialogOpen] = useState(false);
    const [editingTeam, setEditingTeam] = useState<Team | null>(null);
    const [teamToDelete, setTeamToDelete] = useState<Team | null>(null);
    const [searchValue, setSearchValue] = useState(filters.search);
    const [isSearching, setIsSearching] = useState(false);

    // Debounced search - automatically search after user stops typing
    const debouncedSearch = useCallback((value: string) => {
        setIsSearching(true);
        router.get(
            `/workspaces/${workspace.slug}/teams`,
            { ...filters, search: value, page: 1 },
            { 
                preserveState: true, 
                preserveScroll: true,
                onFinish: () => setIsSearching(false),
            }
        );
    }, [filters, workspace.slug]);

    useEffect(() => {
        // Don't search on initial mount if search value matches filter
        if (searchValue === filters.search) return;

        const timer = setTimeout(() => {
            debouncedSearch(searchValue);
        }, 300);

        return () => clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [searchValue]);

    const clearSearch = () => {
        setSearchValue('');
        // Immediately trigger search with empty value
        router.get(
            `/workspaces/${workspace.slug}/teams`,
            { ...filters, search: '', page: 1 },
            { preserveState: true, preserveScroll: true }
        );
    };

    // Sorting handler
    const handleSort = (field: string) => {
        const newDirection = filters.sort === field && filters.direction === 'asc' ? 'desc' : 'asc';
        router.get(
            `/workspaces/${workspace.slug}/teams`,
            { ...filters, sort: field, direction: newDirection, page: 1 },
            { preserveState: true, preserveScroll: true }
        );
    };

    const getSortIcon = (field: string) => {
        if (filters.sort !== field) return <ArrowUpDown className="ml-2 h-4 w-4" />;
        return filters.direction === 'asc' 
            ? <ArrowUp className="ml-2 h-4 w-4" /> 
            : <ArrowDown className="ml-2 h-4 w-4" />;
    };

    // Define columns for DataTable
    const columns: ColumnDef<Team>[] = useMemo(() => [
        {
            accessorKey: 'name',
            header: () => (
                <Button
                    variant="ghost"
                    onClick={() => handleSort('name')}
                    className="h-8 px-2"
                >
                    Name
                    {getSortIcon('name')}
                </Button>
            ),
            cell: ({ row }) => <span className="font-medium">{row.original.name}</span>,
        },
        {
            accessorKey: 'members_count',
            header: () => (
                <Button
                    variant="ghost"
                    onClick={() => handleSort('members_count')}
                    className="h-8 px-2"
                >
                    # Members
                    {getSortIcon('members_count')}
                </Button>
            ),
            cell: ({ row }) => `${row.original.members_count} Members`,
        },
        {
            id: 'actions',
            header: () => <div className="text-right">Action</div>,
            cell: ({ row }) => {
                const team = row.original;

                if (!isAdmin) return null;

                return (
                    <div className="text-right space-x-2">
                        <Button
                            variant="link"
                            size="sm"
                            onClick={() => setEditingTeam(team)}
                        >
                            Edit
                        </Button>
                        <Button
                            variant="link"
                            size="sm"
                            className="text-destructive"
                            onClick={() => setTeamToDelete(team)}
                        >
                            Delete
                        </Button>
                    </div>
                );
            },
        },
    ], [filters.sort, filters.direction, isAdmin]);

    return (
        <AppLayout>
            <Head title={`${workspace.name} - Teams`} />

            <div className="space-y-6 p-4">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold">Teams</h1>
                        <p className="text-muted-foreground">Manage your workspace teams</p>
                    </div>

                    {isAdmin && (
                        <TeamFormDialog
                            workspaceSlug={workspace.slug}
                            workspaceMembers={workspaceMembers}
                            open={createDialogOpen}
                            onOpenChange={setCreateDialogOpen}
                            showTrigger={true}
                        />
                    )}
                </div>

                {/* Search */}
                <div className="relative max-w-sm">
                    <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                    <Input
                        type="text"
                        placeholder="Search teams..."
                        value={searchValue}
                        onChange={(e) => setSearchValue(e.target.value)}
                        className="pl-8"
                    />
                    {(searchValue || isSearching) && (
                        <span className="absolute right-2.5 top-2.5">
                            {isSearching ? (
                                <Loader2 className="h-4 w-4 animate-spin text-muted-foreground" />
                            ) : (
                                <button
                                    type="button"
                                    onClick={clearSearch}
                                >
                                    <X className="h-4 w-4 text-muted-foreground hover:text-foreground" />
                                </button>
                            )}
                        </span>
                    )}
                </div>

                {/* Teams Table using DataTable */}
                <DataTable columns={columns} data={teams.data} />

                {/* Pagination - always show results count */}
                <div className="flex items-center justify-between">
                    <div className="text-sm text-muted-foreground">
                        {teams.total > 0 ? (
                            <>Showing {teams.from} to {teams.to} of {teams.total} results</>
                        ) : (
                            <>No results found</>
                        )}
                    </div>
                    {teams.last_page > 1 && (
                        <div className="flex gap-2">
                            {teams.links.map((link, index) => (
                                <Button
                                    key={index}
                                    variant={link.active ? 'default' : 'outline'}
                                    size="sm"
                                    disabled={!link.url}
                                    onClick={() => {
                                        if (link.url) {
                                            router.get(link.url, {}, { preserveState: true, preserveScroll: true });
                                        }
                                    }}
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            ))}
                        </div>
                    )}
                </div>
            </div>

            {/* Edit Dialog - uses same TeamFormDialog with team prop */}
            <TeamFormDialog
                workspaceSlug={workspace.slug}
                workspaceMembers={workspaceMembers}
                open={!!editingTeam}
                onOpenChange={(open) => !open && setEditingTeam(null)}
                team={editingTeam}
                showTrigger={false}
            />

            {/* Delete Confirmation Dialog */}
            <DeleteTeamDialog
                workspaceSlug={workspace.slug}
                team={teamToDelete}
                onClose={() => setTeamToDelete(null)}
            />
        </AppLayout>
    );
}
