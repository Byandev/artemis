import ComponentCard from '@/components/common/ComponentCard';
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
import { Button } from '@/components/ui/button';
import { DataTable, SortableHeader } from '@/components/ui/data-table';
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
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { toFrontendSort } from '@/lib/sort';
import workspaces from '@/routes/workspaces';
import { PaginatedData, User } from '@/types';
import { Workspace } from '@/types/models/Workspace';
import { Head, router, useForm } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import { omit } from 'lodash';
import { MoreHorizontal, Send, Trash2, UserMinus, CopyleftIcon, UserCog } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Role } from '@/types/models/Role';

interface Invitation {
    id: number;
    email: string;
    role: Role;
    token: string;
    expires_at: string;
    inviter: {
        name: string;
    };
}

interface Props {
    workspace: Workspace;
    members: PaginatedData<User>;
    roles: Role[];
    pendingInvitations: PaginatedData<Invitation>;
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

export default function WorkspaceMembers({ workspace, members, pendingInvitations, isAdmin, query, roles }: Props) {
    const initialSorting = useMemo(() => {
        return toFrontendSort(query?.sort ?? null);
    }, [query?.sort]);

    const [inviteDialogOpen, setInviteDialogOpen] = useState(false);
    const [memberToRemove, setMemberToRemove] = useState<User | null>(null);
    const [memberToUpdateRole, setMemberToUpdateRole] = useState<User | null>(null);
    const [invitationToRevoke, setInvitationToRevoke] = useState<Invitation | null>(null);

    const inviteForm = useForm({
        email: '',
        role_id: '',
    });

    const updateRoleForm = useForm({
        role_id: '',
    });

    const handleUpdateRole = (e: React.FormEvent) => {
        e.preventDefault();
        if (!memberToUpdateRole) return;
        updateRoleForm.put(
            workspaces.members.update.url({ workspace: workspace.slug, user: memberToUpdateRole.id }),
            {
                preserveScroll: true,
                onSuccess: () => {
                    setMemberToUpdateRole(null);
                    updateRoleForm.reset();
                },
            }
        );
    };

    const handleInvite = (e: React.FormEvent) => {
        e.preventDefault();
        inviteForm.post(workspaces.invitations.store.url(workspace.slug), {
            preserveScroll: true,
            onSuccess: () => {
                inviteForm.reset();
                setInviteDialogOpen(false);
            },
        });
    };

    const copyInviteUrl = async (invitation: Invitation) => {
        try {
            const domain = window.location.origin;

            await navigator.clipboard.writeText(
                `${domain}/workspaces/invitations/${invitation.token}/accept`,
            );
            alert('Copied!');
        } catch (error) {
            console.error('Failed to copy:', error);
        }
    }

    const handleRemoveMember = () => {
        if (!memberToRemove) return;

        router.delete(
            workspaces.members.destroy.url({ workspace: workspace.slug, user: memberToRemove.id }),
            {
                preserveScroll: true,
                onSuccess: () => setMemberToRemove(null),
            }
        );
    };

    const handleRevokeInvitation = () => {
        if (!invitationToRevoke) return;

        router.delete(workspaces.invitations.destroy.url(invitationToRevoke.id), {
            preserveScroll: true,
            onSuccess: () => setInvitationToRevoke(null),
        });
    };

    const handleResendInvitation = (invitationId: number) => {
        router.post(workspaces.invitations.resend.url(invitationId), {}, {
            preserveScroll: true,
        });
    };

    const membersColumns: ColumnDef<User>[] = [
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
                <SortableHeader column={column} title={'Name'} />
            ),
        },
        {
            accessorKey: 'email',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader column={column} title={'Email'} />
            ),
        },
        {
            accessorKey: 'pivot.role',
            header: ({ column }) => (
                <SortableHeader column={column} title={'Role'} />
            ),
            cell: ({ row }) => row.original?.pivot?.role ?? 'Owner',
        },
        {
            accessorKey: 'pivot.created_at',
            header: ({ column }) => (
                <SortableHeader column={column} title={'Joined'} />
            ),
            cell: ({ row }) => {
                return new Date(row.original.pivot?.created_at as string).toLocaleDateString();
            },
        },
        {
            id: 'actions',
            cell: ({ row }) => {
                const member = row.original;

                if (!isAdmin || member.pivot?.role === 'owner') return null;

                return (
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button variant="ghost" size="sm">
                                <MoreHorizontal className="h-4 w-4" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuItem
                                onClick={() => {
                                    setMemberToUpdateRole(member);
                                    updateRoleForm.setData('role_id', member.pivot?.role_id?.toString() ?? '');
                                }}
                            >
                                <UserCog className="mr-2 h-4 w-4" />
                                Change Role
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem
                                onClick={() => setMemberToRemove(member)}
                                className="text-destructive focus:text-destructive"
                            >
                                <UserMinus className="mr-2 h-4 w-4" />
                                Remove
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                );
            },
        },
    ];

    const invitationsColumns: ColumnDef<Invitation>[] = [
        {
            accessorKey: 'id',
            header: ({ column }) => (
                <SortableHeader column={column} title={'ID'} />
            ),
        },
        {
            accessorKey: 'email',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader column={column} title={'Email'} />
            ),
        },
        {
            accessorKey: 'role',
            header: ({ column }) => (
                <SortableHeader column={column} title={'Role'} />
            ),
            cell: ({ row }) => row.original.role?.name,
        },
        {
            id: 'inviter_name',
            accessorKey: 'inviter.name',
            enableSorting: true,
            header: ({ column }) => (
                <SortableHeader column={column} title={'Invited By'} />
            ),
        },
        {
            accessorKey: 'expires_at',
            header: ({ column }) => (
                <SortableHeader column={column} title={'Expires'} />
            ),
            cell: ({ row }) => {
                return new Date(row.original.expires_at).toLocaleDateString();
            },
        },
        {
            id: 'actions',
            cell: ({ row }) => {
                const invitation = row.original;

                if (!isAdmin) return null;

                return (
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button variant="ghost" size="sm">
                                <MoreHorizontal className="h-4 w-4" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuItem
                                onClick={() =>
                                    handleResendInvitation(invitation.id)
                                }
                            >
                                <Send className="mr-2 h-4 w-4" />
                                Resend
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem
                                onClick={() =>
                                    setInvitationToRevoke(invitation)
                                }
                                className="text-destructive focus:text-destructive"
                            >
                                <Trash2 className="mr-2 h-4 w-4" />
                                Revoke
                            </DropdownMenuItem>

                            <DropdownMenuItem
                                onClick={() => copyInviteUrl(invitation)}
                                className="text-destructive focus:text-destructive"
                            >
                                <CopyleftIcon className="mr-2 h-4 w-4" />
                                Copy
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                );
            },
        },
    ];

    return (
        <AppLayout>
            <Head title={`${workspace.name} - Members`} />
            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
                <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                    <h2
                        className="text-xl font-semibold text-gray-800 dark:text-white/90"
                        x-text="pageName"
                    >
                        Members
                    </h2>

                    {isAdmin && (
                        <Dialog
                            open={inviteDialogOpen}
                            onOpenChange={setInviteDialogOpen}
                        >
                            <DialogTrigger asChild>
                                <Button size="sm">Invite Member</Button>
                            </DialogTrigger>
                            <DialogContent>
                                <form onSubmit={handleInvite}>
                                    <DialogHeader>
                                        <DialogTitle>Invite Member</DialogTitle>
                                        <DialogDescription>
                                            Send an invitation to join this
                                            workspace
                                        </DialogDescription>
                                    </DialogHeader>
                                    <div className="space-y-4 py-4">
                                        <div className="space-y-2">
                                            <Label htmlFor="email">
                                                Email Address
                                            </Label>
                                            <Input
                                                id="email"
                                                type="email"
                                                placeholder="colleague@example.com"
                                                value={inviteForm.data.email}
                                                onChange={(e) =>
                                                    inviteForm.setData(
                                                        'email',
                                                        e.target.value,
                                                    )
                                                }
                                                required
                                            />
                                            {inviteForm.errors.email && (
                                                <p className="text-destructive text-sm">
                                                    {inviteForm.errors.email}
                                                </p>
                                            )}
                                        </div>
                                        <div className="space-y-2">
                                            <Label htmlFor="role">Role</Label>

                                            <Select
                                            value={inviteForm.data.role_id}
                                            onValueChange={(value) => inviteForm.setData('role_id', value)}
                                            >
                                                <SelectTrigger>
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {roles.map((role) => (
                                                        <SelectItem
                                                            key={role.id}
                                                            value={role.id.toString()}
                                                        >
                                                            {role.name}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                            <p className="text-sm text-muted-foreground">
                                                Admins can manage members and
                                                settings
                                            </p>
                                        </div>
                                    </div>
                                    <DialogFooter>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={() =>
                                                setInviteDialogOpen(false)
                                            }
                                        >
                                            Cancel
                                        </Button>
                                        <Button
                                            type="submit"
                                            disabled={inviteForm.processing}
                                        >
                                            {inviteForm.processing
                                                ? 'Sending...'
                                                : 'Send Invitation'}
                                        </Button>
                                    </DialogFooter>
                                </form>
                            </DialogContent>
                        </Dialog>
                    )}
                </div>

                <div className="space-y-5 sm:space-y-6">
                    {/* Members Table */}
                    <ComponentCard desc="Manage workspace members and their roles">
                        <DataTable
                            columns={membersColumns}
                            enableInternalPagination={false}
                            data={members.data || []}
                            initialSorting={initialSorting}
                            meta={{ ...omit(members, ['data']) }}
                            onFetch={(params) => {
                                router.get(
                                    `/workspaces/${workspace.slug}/members`,
                                    {
                                        sort: params?.sort,
                                        page: params?.page ?? 1,
                                    },
                                    {
                                        preserveState: false,
                                        replace: true,
                                        preserveScroll: true,
                                    },
                                );
                            }}
                        />
                    </ComponentCard>

                    {/* Pending Invitations */}
                    {pendingInvitations.data &&
                        pendingInvitations.data.length > 0 && (
                            <ComponentCard desc="Pending workspace invitations">
                                <DataTable
                                    columns={invitationsColumns}
                                    enableInternalPagination={false}
                                    data={pendingInvitations.data || []}
                                    initialSorting={initialSorting}
                                    meta={{
                                        ...omit(pendingInvitations, ['data']),
                                    }}
                                    onFetch={(params) => {
                                        router.get(
                                            `/workspaces/${workspace.slug}/members`,
                                            {
                                                sort: params?.sort,
                                                page: params?.page ?? 1,
                                            },
                                            {
                                                preserveState: false,
                                                replace: true,
                                                preserveScroll: true,
                                            },
                                        );
                                    }}
                                />
                            </ComponentCard>
                        )}
                </div>
            </div>

            {/* Remove Member Confirmation Dialog */}
            <AlertDialog
                open={!!memberToRemove}
                onOpenChange={() => setMemberToRemove(null)}
            >
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Remove Member</AlertDialogTitle>
                        <AlertDialogDescription>
                            Are you sure you want to remove{' '}
                            {memberToRemove?.name} from this workspace? They
                            will lose access to all workspace resources.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction onClick={handleRemoveMember}>
                            Remove Member
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>

            {/* Update Member Role Dialog */}
            <Dialog open={!!memberToUpdateRole} onOpenChange={(open) => { if (!open) { setMemberToUpdateRole(null); updateRoleForm.reset(); } }}>
                <DialogContent>
                    <form onSubmit={handleUpdateRole}>
                        <DialogHeader>
                            <DialogTitle>Change Role</DialogTitle>
                            <DialogDescription>
                                Update the role for <strong>{memberToUpdateRole?.name}</strong>
                            </DialogDescription>
                        </DialogHeader>
                        <div className="py-4">
                            <div className="space-y-2">
                                <Label htmlFor="update-role">Role</Label>
                                <Select
                                    value={updateRoleForm.data.role_id}
                                    onValueChange={(value) => updateRoleForm.setData('role_id', value)}
                                >
                                    <SelectTrigger id="update-role">
                                        <SelectValue placeholder="Select a role" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {roles.map((role) => (
                                            <SelectItem key={role.id} value={role.id.toString()}>
                                                {role.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {updateRoleForm.errors.role_id && (
                                    <p className="text-destructive text-sm">{updateRoleForm.errors.role_id}</p>
                                )}
                            </div>
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => { setMemberToUpdateRole(null); updateRoleForm.reset(); }}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={updateRoleForm.processing}>
                                {updateRoleForm.processing ? 'Saving...' : 'Save'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Revoke Invitation Confirmation Dialog */}
            <AlertDialog
                open={!!invitationToRevoke}
                onOpenChange={() => setInvitationToRevoke(null)}
            >
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Revoke Invitation</AlertDialogTitle>
                        <AlertDialogDescription>
                            Are you sure you want to revoke the invitation for{' '}
                            {invitationToRevoke?.email}? The invitation link
                            will no longer be valid.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction onClick={handleRevokeInvitation}>
                            Revoke Invitation
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </AppLayout>
    );
}
