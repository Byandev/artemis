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
import { useState, useMemo } from 'react';
import { X, Search, Users } from 'lucide-react';
import workspaces from '@/routes/workspaces';

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

interface Props {
    workspace: Workspace;
    teams: Team[];
    workspaceMembers: User[];
    isAdmin: boolean;
}

export default function TeamsIndex({ workspace, teams, workspaceMembers, isAdmin }: Props) {
    const [createDialogOpen, setCreateDialogOpen] = useState(false);
    const [editingTeam, setEditingTeam] = useState<Team | null>(null);
    const [teamToDelete, setTeamToDelete] = useState<Team | null>(null);
    const [searchQuery, setSearchQuery] = useState('');
    const [memberSearch, setMemberSearch] = useState('');

    const createForm = useForm({
        name: '',
        members: [] as number[],
    });

    const editForm = useForm({
        name: '',
        members: [] as number[],
    });

    // Filter teams based on search query
    const filteredTeams = useMemo(() => {
        if (!searchQuery.trim()) return teams;
        return teams.filter(team =>
            team.name.toLowerCase().includes(searchQuery.toLowerCase())
        );
    }, [teams, searchQuery]);

    // Filter workspace members for dropdown (exclude already selected)
    const availableMembers = useMemo(() => {
        const selectedIds = createDialogOpen ? createForm.data.members : editForm.data.members;
        return workspaceMembers.filter(member => {
            const matchesSearch = !memberSearch.trim() ||
                member.name.toLowerCase().includes(memberSearch.toLowerCase()) ||
                member.email.toLowerCase().includes(memberSearch.toLowerCase());
            const notSelected = !selectedIds.includes(member.id);
            return matchesSearch && notSelected;
        });
    }, [workspaceMembers, memberSearch, createDialogOpen, createForm.data.members, editForm.data.members]);

    // Get selected members details
    const getSelectedMembers = (memberIds: number[]) => {
        return workspaceMembers.filter(member => memberIds.includes(member.id));
    };

    const handleCreate = (e: React.FormEvent) => {
        e.preventDefault();
        createForm.post(workspaces.teams.store.url(workspace.slug), {
            preserveScroll: true,
            onSuccess: () => {
                createForm.reset();
                setMemberSearch('');
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
                setMemberSearch('');
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

    const addMember = (memberId: number, isCreate: boolean) => {
        if (isCreate) {
            createForm.setData('members', [...createForm.data.members, memberId]);
        } else {
            editForm.setData('members', [...editForm.data.members, memberId]);
        }
        setMemberSearch('');
    };

    const removeMember = (memberId: number, isCreate: boolean) => {
        if (isCreate) {
            createForm.setData('members', createForm.data.members.filter(id => id !== memberId));
        } else {
            editForm.setData('members', editForm.data.members.filter(id => id !== memberId));
        }
    };

    const MemberSelector = ({ isCreate }: { isCreate: boolean }) => {
        const form = isCreate ? createForm : editForm;
        const selectedMembers = getSelectedMembers(form.data.members);

        return (
            <div className="space-y-3">
                <Label>Members</Label>

                {/* Search Input */}
                <div className="relative">
                    <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        type="text"
                        placeholder="Search team members..."
                        value={memberSearch}
                        onChange={(e) => setMemberSearch(e.target.value)}
                        className="pl-9"
                    />
                </div>

                {/* Dropdown Results */}
                {memberSearch.trim() && availableMembers.length > 0 && (
                    <div className="border rounded-md max-h-40 overflow-y-auto">
                        {availableMembers.map(member => (
                            <button
                                key={member.id}
                                type="button"
                                onClick={() => addMember(member.id, isCreate)}
                                className="w-full px-3 py-2 text-left hover:bg-accent flex items-center gap-3"
                            >
                                <div className="h-8 w-8 rounded-full bg-primary/10 flex items-center justify-center text-sm font-medium">
                                    {member.name.charAt(0).toUpperCase()}
                                </div>
                                <div>
                                    <div className="font-medium text-sm">{member.name}</div>
                                    <div className="text-xs text-muted-foreground">{member.email}</div>
                                </div>
                            </button>
                        ))}
                    </div>
                )}

                {memberSearch.trim() && availableMembers.length === 0 && (
                    <div className="text-sm text-muted-foreground py-2">
                        No members found
                    </div>
                )}

                {/* Selected Members */}
                {selectedMembers.length > 0 && (
                    <div className="space-y-2">
                        {selectedMembers.map(member => (
                            <div
                                key={member.id}
                                className="flex items-center justify-between p-2 border rounded-md bg-muted/50"
                            >
                                <div className="flex items-center gap-3">
                                    <div className="h-8 w-8 rounded-full bg-primary/10 flex items-center justify-center text-sm font-medium">
                                        {member.name.charAt(0).toUpperCase()}
                                    </div>
                                    <div>
                                        <div className="font-medium text-sm">{member.name}</div>
                                        <div className="text-xs text-muted-foreground">{member.email}</div>
                                    </div>
                                </div>
                                <button
                                    type="button"
                                    onClick={() => removeMember(member.id, isCreate)}
                                    className="h-6 w-6 rounded-full hover:bg-destructive/10 flex items-center justify-center"
                                >
                                    <X className="h-4 w-4 text-muted-foreground hover:text-destructive" />
                                </button>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        );
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
                                setMemberSearch('');
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
                                                placeholder="Search"
                                                value={createForm.data.name}
                                                onChange={(e) => createForm.setData('name', e.target.value)}
                                                required
                                            />
                                            {createForm.errors.name && (
                                                <p className="text-sm text-destructive">{createForm.errors.name}</p>
                                            )}
                                        </div>

                                        <MemberSelector isCreate={true} />
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
                <div className="max-w-sm">
                    <Input
                        type="text"
                        placeholder="Search..."
                        value={searchQuery}
                        onChange={(e) => setSearchQuery(e.target.value)}
                    />
                </div>

                {/* Teams Table */}
                <div className="border rounded-lg">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Name</TableHead>
                                <TableHead># Members</TableHead>
                                <TableHead className="text-right">Action</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {filteredTeams.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={3} className="text-center py-8 text-muted-foreground">
                                        No results.
                                    </TableCell>
                                </TableRow>
                            ) : (
                                filteredTeams.map((team) => (
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
            </div>

            {/* Edit Dialog */}
            <Dialog open={!!editingTeam} onOpenChange={(open) => {
                if (!open) {
                    setEditingTeam(null);
                    editForm.reset();
                    setMemberSearch('');
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

                            <MemberSelector isCreate={false} />
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
