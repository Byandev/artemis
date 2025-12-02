import { Head, useForm, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useState } from 'react';
import { Search, ArrowUpDown, ArrowUp, ArrowDown, X } from 'lucide-react';
import workspaces from '@/routes/workspaces';
import { MemberSelector } from '@/components/teams/member-selector';

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

    const createForm = useForm({
        name: '',
        members: [] as number[],
    });

    const editForm = useForm({
        name: '',
        members: [] as number[],
    });

    // Backend search handler
    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(
            `/workspaces/${workspace.slug}/teams`,
            { ...filters, search: searchValue, page: 1 },
            { preserveState: true, preserveScroll: true }
        );
    };

    const clearSearch = () => {
        setSearchValue('');
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

    const handleCreate = (e: React.FormEvent) => {
        e.preventDefault();
        createForm.post(workspaces.teams.store.url(workspace.slug), {
            preserveScroll: true,
            onSuccess: () => {
                createForm.reset();
                setCreateDialogOpen(false);
            },
        });
    };

    const handleEdit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!editingTeam) return;

        editForm.put(workspaces.teams.update.url({ workspace: workspace.slug, team: editingTeam.id }), {
            preserveScroll: true,
            onSuccess: () => {
                editForm.reset();
                setEditingTeam(null);
            },
        });
    };

    const handleDelete = () => {
        if (!teamToDelete) return;

        router.delete(workspaces.teams.destroy.url({ workspace: workspace.slug, team: teamToDelete.id }), {
            preserveScroll: true,
            onSuccess: () => setTeamToDelete(null),
        });
    };

    const openEditDialog = (team: Team) => {
        editForm.setData({
            name: team.name,
            members: team.members.map(m => m.id),
        });
        setEditingTeam(team);
    };

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
                        <Dialog open={createDialogOpen} onOpenChange={(open) => {
                            setCreateDialogOpen(open);
                            if (!open) {
                                createForm.reset();
                            }
                        }}>
                            <DialogTrigger asChild>
                                <Button>Create new Team</Button>
                            </DialogTrigger>
                            <DialogContent className="sm:max-w-md">
                                <form onSubmit={handleCreate}>
                                    <DialogHeader>
                                        <DialogTitle>Create new Team</DialogTitle>
                                        <DialogDescription>
                                            Create a new team and add members from your workspace
                                        </DialogDescription>
                                    </DialogHeader>

                                    <div className="space-y-4 py-4">
                                        <div className="space-y-2">
                                            <Label htmlFor="name">Team Name</Label>
                                            <Input
                                                id="name"
                                                placeholder="Enter team name"
                                                value={createForm.data.name}
                                                onChange={(e) => createForm.setData('name', e.target.value)}
                                                required
                                            />
                                            {createForm.errors.name && (
                                                <p className="text-sm text-destructive">{createForm.errors.name}</p>
                                            )}
                                        </div>

                                        <MemberSelector
                                            workspaceMembers={workspaceMembers}
                                            selectedMemberIds={createForm.data.members}
                                            onAddMember={(id) => createForm.setData('members', [...createForm.data.members, id])}
                                            onRemoveMember={(id) => createForm.setData('members', createForm.data.members.filter(m => m !== id))}
                                        />
                                    </div>

                                    <DialogFooter>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={() => setCreateDialogOpen(false)}
                                        >
                                            Cancel
                                        </Button>
                                        <Button type="submit" disabled={createForm.processing}>
                                            {createForm.processing ? 'Creating...' : 'Create Team'}
                                        </Button>
                                    </DialogFooter>
                                </form>
                            </DialogContent>
                        </Dialog>
                    )}
                </div>

                {/* Search */}
                <form onSubmit={handleSearch} className="flex gap-2 max-w-sm">
                    <div className="relative flex-1">
                        <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                        <Input
                            type="text"
                            placeholder="Search teams..."
                            value={searchValue}
                            onChange={(e) => setSearchValue(e.target.value)}
                            className="pl-8"
                        />
                        {searchValue && (
                            <button
                                type="button"
                                onClick={clearSearch}
                                className="absolute right-2.5 top-2.5"
                            >
                                <X className="h-4 w-4 text-muted-foreground hover:text-foreground" />
                            </button>
                        )}
                    </div>
                    <Button type="submit" size="sm" variant="secondary">
                        Search
                    </Button>
                </form>

                {/* Teams Table */}
                <div className="border rounded-lg">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>
                                    <Button
                                        variant="ghost"
                                        onClick={() => handleSort('name')}
                                        className="h-8 px-2"
                                    >
                                        Name
                                        {getSortIcon('name')}
                                    </Button>
                                </TableHead>
                                <TableHead>
                                    <Button
                                        variant="ghost"
                                        onClick={() => handleSort('members_count')}
                                        className="h-8 px-2"
                                    >
                                        # Members
                                        {getSortIcon('members_count')}
                                    </Button>
                                </TableHead>
                                <TableHead className="text-right">Action</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {teams.data.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={3} className="text-center py-8 text-muted-foreground">
                                        No results.
                                    </TableCell>
                                </TableRow>
                            ) : (
                                teams.data.map((team) => (
                                    <TableRow key={team.id}>
                                        <TableCell className="font-medium">{team.name}</TableCell>
                                        <TableCell>{team.members_count} Members</TableCell>
                                        <TableCell className="text-right space-x-2">
                                            {isAdmin && (
                                                <>
                                                    <Button
                                                        variant="link"
                                                        size="sm"
                                                        onClick={() => openEditDialog(team)}
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
                                                </>
                                            )}
                                        </TableCell>
                                    </TableRow>
                                ))
                            )}
                        </TableBody>
                    </Table>
                </div>

                {/* Pagination */}
                {teams.last_page > 1 && (
                    <div className="flex items-center justify-between">
                        <div className="text-sm text-muted-foreground">
                            Showing {teams.from} to {teams.to} of {teams.total} results
                        </div>
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
                    </div>
                )}
            </div>

            {/* Edit Dialog */}
            <Dialog open={!!editingTeam} onOpenChange={(open) => {
                if (!open) {
                    setEditingTeam(null);
                    editForm.reset();
                }
            }}>
                <DialogContent className="sm:max-w-md">
                    <form onSubmit={handleEdit}>
                        <DialogHeader>
                            <DialogTitle>Edit Team</DialogTitle>
                            <DialogDescription>
                                Update team name and members
                            </DialogDescription>
                        </DialogHeader>

                        <div className="space-y-4 py-4">
                            <div className="space-y-2">
                                <Label htmlFor="edit-name">Team Name</Label>
                                <Input
                                    id="edit-name"
                                    value={editForm.data.name}
                                    onChange={(e) => editForm.setData('name', e.target.value)}
                                    required
                                />
                                {editForm.errors.name && (
                                    <p className="text-sm text-destructive">{editForm.errors.name}</p>
                                )}
                            </div>

                            <MemberSelector
                                workspaceMembers={workspaceMembers}
                                selectedMemberIds={editForm.data.members}
                                onAddMember={(id) => editForm.setData('members', [...editForm.data.members, id])}
                                onRemoveMember={(id) => editForm.setData('members', editForm.data.members.filter(m => m !== id))}
                            />
                        </div>

                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setEditingTeam(null)}
                            >
                                Cancel
                            </Button>
                            <Button type="submit" disabled={editForm.processing}>
                                {editForm.processing ? 'Saving...' : 'Save Changes'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Delete Confirmation Dialog */}
            <AlertDialog open={!!teamToDelete} onOpenChange={() => setTeamToDelete(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Delete Team</AlertDialogTitle>
                        <AlertDialogDescription>
                            Are you sure you want to delete "{teamToDelete?.name}"?
                            This action cannot be undone.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction 
                            onClick={handleDelete} 
                            className="bg-destructive text-white hover:bg-destructive/90"
                        >
                            Delete
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </AppLayout>
    );
}
