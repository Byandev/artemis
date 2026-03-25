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
import { PaginatedData } from '@/types';
import { Workspace } from '@/types/models/Workspace';
import { Link, Head, router, useForm } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import clsx from 'clsx';
import { omit } from 'lodash';
import { MoreHorizontal, Send, Trash2, UserMinus, CopyleftIcon, Settings2, ArrowRight, ShieldAlert } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';

interface Role {
    id: number;
    name: string;
    display_name: string;
    role: string;
    description: string;
}

interface User {
    id: number;
    name: string;
    email: string;
    pivot: {
        role: string;
        created_at: string;
    };
}

interface Invitation {
    id: number;
    email: string;
    role: string;
    token: string;
    expires_at: string;
    inviter: {
        name: string;
    };
}

interface Props {
    workspace: Workspace | Workspace[];
    members: PaginatedData<User>;
    pendingInvitations: PaginatedData<Invitation>;
    roles: Role[];
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

const ROLE_BADGE_CONFIG: Record<string, { label: string; className: string }> = {
    owner: { label: 'OWNER', className: 'bg-indigo-50 text-indigo-700 ring-indigo-200' },
    admin: { label: 'ADMIN', className: 'bg-emerald-50 text-emerald-700 ring-emerald-200' },
    member: { label: 'MEMBER', className: 'bg-zinc-50 text-green-700 ring-green-200' },
};

const RoleBadge = ({ role, children }: { role: string; children?: React.ReactNode }) => {
    const config = ROLE_BADGE_CONFIG[role] ?? {
        label: role.toUpperCase(),
        className: 'bg-gray-50 text-gray-700 ring-gray-200'
    };

    return (
        <span className={clsx(
            'inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-bold tracking-wide ring-1 ring-inset',
            config.className
        )}>
            {children || config.label}
        </span>
    );
};

export default function WorkspaceMembers({
    workspace,
    members = { data: [] } as any,
    pendingInvitations = { data: [] } as any,
    roles = [],
    isAdmin,
    query
}: Props) {
    const isSelectionMode = Array.isArray(workspace);
    const activeWorkspace = !isSelectionMode ? workspace : null;

    const initialSorting = useMemo(() => toFrontendSort(query?.sort ?? null), [query?.sort]);
    const [memberToEdit, setMemberToEdit] = useState<User | null>(null);
    const [inviteDialogOpen, setInviteDialogOpen] = useState(false);
    const [memberToRemove, setMemberToRemove] = useState<User | null>(null);
    const [invitationToRevoke, setInvitationToRevoke] = useState<Invitation | null>(null);
    const [searchValue, setSearchValue] = useState(query?.filter?.search ?? '');

    const editRoleForm = useForm({ role: '' });
    const inviteForm = useForm({
        email: '',
        role: (roles || []).find(r => r.name === 'member')?.name || roles?.[0]?.name || '',
    });

    useEffect(() => {
        if (isSelectionMode || !activeWorkspace?.slug) return;
        const timer = setTimeout(() => {
            router.get(
                `/workspaces/${activeWorkspace.slug}/members`,
                { sort: query?.sort, 'filter[search]': searchValue || undefined, page: 1 },
                { preserveState: true, replace: true, preserveScroll: true }
            );
        }, 500);
        return () => clearTimeout(timer);
    }, [searchValue, activeWorkspace?.slug, isSelectionMode]);

    const handleInvite = (e: React.FormEvent) => {
        e.preventDefault();
        if (!activeWorkspace) return;
        inviteForm.post(workspaces.invitations.store.url(activeWorkspace.slug), {
            preserveScroll: true,
            onSuccess: () => {
                toast.success(`Invitation sent to ${inviteForm.data.email}`);
                setInviteDialogOpen(false);
                inviteForm.reset();
            },
        });
    };

    const handleUpdateRole = (e: React.FormEvent) => {
        e.preventDefault();
        if (!memberToEdit || !activeWorkspace) return;

        editRoleForm.put(workspaces.members.update.url({ workspace: activeWorkspace.slug, user: memberToEdit.id }), {
            preserveScroll: true,
            onSuccess: () => {
                toast.success(`Role updated for ${memberToEdit.name}`);
                setMemberToEdit(null);
                editRoleForm.reset();
            },
        });
    };

    const copyInviteUrl = async (invitation: Invitation) => {
        try {
            await navigator.clipboard.writeText(`${window.location.origin}/workspaces/invitations/${invitation.token}/accept`);
            toast.success('Link copied to clipboard');
        } catch (error) { console.error(error); }
    };

    const handleRemoveMember = () => {
        if (!memberToRemove || !activeWorkspace) return;
        router.delete(workspaces.members.destroy.url({ workspace: activeWorkspace.slug, user: memberToRemove.id }), {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Member removed');
                setMemberToRemove(null);
            },
        });
    };

    const handleRevokeInvitation = () => {
        if (!invitationToRevoke) return;
        router.delete(workspaces.invitations.destroy.url(invitationToRevoke.id), {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Invitation revoked');
                setInvitationToRevoke(null);
            },
        });
    };

    const handleResendInvitation = (invitationId: number) => {
        router.post(workspaces.invitations.resend.url(invitationId), {}, {
            preserveScroll: true,
            onSuccess: () => toast.success('Invitation resent'),
        });
    };

    const membersColumns = useMemo<ColumnDef<User>[]>(() => [
        { accessorKey: 'id', header: ({ column }) => <SortableHeader column={column} title={'ID'} /> },
        { accessorKey: 'name', enableSorting: true, header: ({ column }) => <SortableHeader column={column} title={'Name'} /> },
        { accessorKey: 'email', enableSorting: true, header: ({ column }) => <SortableHeader column={column} title={'Email'} /> },
        {
            accessorKey: 'pivot.role',
            header: ({ column }) => (
                <div className="flex justify-center">
                    <SortableHeader column={column} title={'Role'} />
                </div>
            ),
            cell: ({ row }) => {
                const roleSlug = row.original.pivot.role;
                const roleData = roles.find(r => r.name === roleSlug);

                return (
                    <div className="flex justify-center">
                        <RoleBadge role={roleSlug}>
                            {roleData?.display_name || roleSlug.toUpperCase()}
                        </RoleBadge>
                    </div>
                );
            },
        },
        {
            accessorKey: 'pivot.created_at',
            header: ({ column }) => (
                <div className="flex justify-center">
                    <SortableHeader column={column} title={'Joined'} />
                </div>
            ),
            cell: ({ row }) => (
                <div className="text-center">
                    {new Date(row.original.pivot.created_at).toLocaleDateString()}
                </div>
            ),
        },
        {
            id: 'actions',
            header: () => (
                <div className="text-center text-xs font-semibold lowercase tracking-wider text-gray-800">
                    Action
                </div>
            ),
            cell: ({ row }) => {
                const member = row.original;
                if (!isAdmin) return null;

                const isOwner = member.id === (activeWorkspace as Workspace)?.owner_id;

                return (
                    <div className="text-center">
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button variant="ghost" size="sm" className="h-8 w-8 p-0">
                                    <MoreHorizontal className="h-4 w-4" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                <DropdownMenuItem
                                    onClick={() => {
                                        if (isOwner) {
                                            toast.error("Action Prohibited", {
                                                description: "The Workspace Owner role is permanent and cannot be modified.",
                                            });
                                            return;
                                        }
                                        setMemberToEdit(member);
                                        editRoleForm.setData('role', member.pivot.role);
                                    }}
                                    className={clsx(isOwner && "opacity-50 cursor-not-allowed")}
                                >
                                    {isOwner ? <ShieldAlert className="mr-2 h-4 w-4" /> : <Settings2 className="mr-2 h-4 w-4" />}
                                    Edit Role
                                </DropdownMenuItem>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem
                                    onClick={() => {
                                        if (isOwner) {
                                            toast.error("Action Prohibited", {
                                                description: "The Workspace Owner cannot be removed from the workspace.",
                                            });
                                            return;
                                        }
                                        setMemberToRemove(member);
                                    }}
                                    className={clsx("text-destructive", isOwner && "opacity-50 cursor-not-allowed")}
                                >
                                    <UserMinus className="mr-2 h-4 w-4" /> Remove Member
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                );
            },
        },
    ], [isAdmin, roles, editRoleForm, activeWorkspace]);

    const invitationsColumns: ColumnDef<Invitation>[] = [
        { accessorKey: 'id', header: ({ column }) => <SortableHeader column={column} title={'ID'} /> },
        { accessorKey: 'email', enableSorting: true, header: ({ column }) => <SortableHeader column={column} title={'Email'} /> },
        { accessorKey: 'role', header: ({ column }) => <SortableHeader column={column} title={'Role'} />, cell: ({ row }) => <RoleBadge role={row.original.role} /> },
        { accessorKey: 'inviter.name', enableSorting: true, header: ({ column }) => <SortableHeader column={column} title={'Invited By'} /> },
        { accessorKey: 'expires_at', header: ({ column }) => <SortableHeader column={column} title={'Expires'} />, cell: ({ row }) => new Date(row.original.expires_at).toLocaleDateString() },
        {
            id: 'actions',
            cell: ({ row }) => {
                const invitation = row.original;
                if (!isAdmin) return null;
                return (
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild><Button variant="ghost" size="sm"><MoreHorizontal className="h-4 w-4" /></Button></DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuItem onClick={() => handleResendInvitation(invitation.id)}><Send className="mr-2 h-4 w-4" /> Resend</DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem onClick={() => setInvitationToRevoke(invitation)} className="text-destructive"><Trash2 className="mr-2 h-4 w-4" /> Revoke</DropdownMenuItem>
                            <DropdownMenuItem onClick={() => copyInviteUrl(invitation)}><CopyleftIcon className="mr-2 h-4 w-4" /> Copy Link</DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                );
            },
        },
    ];

    return (
        <AppLayout>
            <Head title={isSelectionMode ? "Choose Workspace" : `${activeWorkspace?.name} - Members`} />
            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
                {isSelectionMode ? (
                    <div className="flex min-h-[80vh] flex-col items-center justify-center bg-gray-50/50 p-4">
                        <div className="mb-8">
                            <img src="/img/logo/artemis.png" alt="Artemis Logo" className="h-16 w-auto opacity-80" />
                        </div>
                        <div className="w-full max-w-2xl rounded-3xl border border-gray-100 bg-white p-8 md:p-12">
                            <div className="mb-10 text-center">
                                <h1 className="text-3xl font-bold tracking-tight text-gray-900">Choose a workspace</h1>
                                <p className="mt-2 text-gray-500">Select one of your workspaces to continue.</p>
                            </div>
                            <div className="space-y-4">
                                {(workspace as Workspace[]).map((ws) => (
                                    <div key={ws.id} className="group flex items-center justify-between rounded-2xl border border-gray-200 p-5 transition-all hover:border-indigo-500 hover:shadow-md">
                                        <div className="flex items-center gap-4">
                                            <div className="flex h-12 w-12 items-center justify-center rounded-full bg-indigo-600 text-lg font-bold text-white shadow-sm transition-transform group-hover:scale-105">
                                                {ws.name.charAt(0).toUpperCase()}
                                            </div>
                                            <div>
                                                <h3 className="text-lg font-bold text-gray-900 group-hover:text-indigo-600">{ws.name}</h3>
                                                <p className="text-sm text-gray-500">Member</p>
                                            </div>
                                        </div>
                                        <Button asChild variant="outline" className="rounded-xl border-gray-200 px-6 py-5 font-semibold hover:bg-indigo-50">
                                            <Link href={`/workspaces/${ws.slug}/members`} className="flex items-center gap-2">
                                                Open <ArrowRight className="h-4 w-4" />
                                            </Link>
                                        </Button>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </div>
                ) : (
                    <>
                        <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                            <h2 className="text-xl font-semibold text-gray-800 dark:text-white/90">{activeWorkspace?.name} - Members</h2>
                            {isAdmin && (
                                <Dialog open={inviteDialogOpen} onOpenChange={setInviteDialogOpen}>
                                    <DialogTrigger asChild><Button size="sm">Invite Member</Button></DialogTrigger>
                                    <DialogContent>
                                        <form onSubmit={handleInvite}>
                                            <DialogHeader>
                                                <DialogTitle>Invite Member</DialogTitle>
                                                <DialogDescription>Send an invitation to join this workspace</DialogDescription>
                                            </DialogHeader>
                                            <div className="space-y-4 py-4">
                                                <div className="space-y-2">
                                                    <Label htmlFor="email">Email Address</Label>
                                                    <Input id="email" type="email" value={inviteForm.data.email} onChange={(e) => inviteForm.setData('email', e.target.value)} required />
                                                </div>
                                                <div className="space-y-2">
                                                    <Label htmlFor="role">Role</Label>
                                                    <Select value={inviteForm.data.role} onValueChange={(value) => inviteForm.setData('role', value)}>
                                                        <SelectTrigger><SelectValue placeholder="Select a role" /></SelectTrigger>
                                                        <SelectContent>
                                                            {roles.map((role) => (
                                                                <SelectItem key={role.id} value={role.role}>{role.display_name}</SelectItem>
                                                            ))}
                                                        </SelectContent>
                                                    </Select>
                                                </div>
                                            </div>
                                            <DialogFooter>
                                                <Button type="button" variant="outline" onClick={() => setInviteDialogOpen(false)}>Cancel</Button>
                                                <Button type="submit" disabled={inviteForm.processing}>{inviteForm.processing ? 'Sending...' : 'Send Invitation'}</Button>
                                            </DialogFooter>
                                        </form>
                                    </DialogContent>
                                </Dialog>
                            )}
                        </div>
                        <div className="space-y-5 sm:space-y-6">
                            <ComponentCard desc="Manage workspace members and their roles">
                                <div className="flex flex-col gap-2 rounded-t-xl border border-b-0 border-gray-100 px-4 py-4 sm:flex-row sm:items-center sm:justify-between dark:border-white/5">
                                    <input
                                        className="max-w-sm border w-full rounded-lg px-4 py-2.5 text-sm dark:bg-gray-900 border-gray-300 focus:border-brand-300 focus:ring-brand-500/20"
                                        placeholder="Search members"
                                        value={searchValue}
                                        onChange={(e) => setSearchValue(e.target.value)}
                                    />
                                </div>
                                <DataTable
                                    columns={membersColumns}
                                    enableInternalPagination={false}
                                    data={members?.data || []}
                                    initialSorting={initialSorting}
                                    meta={{ ...omit(members ?? {}, ['data']) }}
                                    onFetch={(params) => {
                                        if (!activeWorkspace) return;
                                        router.get(`/workspaces/${activeWorkspace.slug}/members`, { sort: params?.sort, 'filter[search]': searchValue || undefined, page: params?.page ?? 1 }, { preserveState: false, replace: true, preserveScroll: true });
                                    }}
                                />
                            </ComponentCard>
                            {pendingInvitations.data.length > 0 && (
                                <ComponentCard desc="Pending workspace invitations">
                                    <DataTable
                                        columns={invitationsColumns}
                                        enableInternalPagination={false}
                                        data={pendingInvitations.data}
                                        initialSorting={initialSorting}
                                        meta={{ ...omit(pendingInvitations ?? {}, ['data']) }}
                                        onFetch={(params) => {
                                            if (!activeWorkspace) return;
                                            router.get(`/workspaces/${activeWorkspace.slug}/members`, { sort: params?.sort, 'filter[search]': searchValue || undefined, page: params?.page ?? 1 }, { preserveState: false, replace: true, preserveScroll: true });
                                        }}
                                    />
                                </ComponentCard>
                            )}
                        </div>
                    </>
                )}
            </div>

            <Dialog open={!!memberToEdit} onOpenChange={() => setMemberToEdit(null)}>
                <DialogContent>
                    <form onSubmit={handleUpdateRole}>
                        <DialogHeader>
                            <DialogTitle>Edit Member Role</DialogTitle>
                            <DialogDescription>Select a new role for this member. Changes will take effect immediately.</DialogDescription>
                        </DialogHeader>
                        <div className="py-4">
                            <Label htmlFor="edit-role">Role</Label>
                            <Select value={editRoleForm.data.role} onValueChange={(value) => editRoleForm.setData('role', value)}>
                                <SelectTrigger id="edit-role" className="w-full">
                                    <SelectValue placeholder="Select a role" />
                                </SelectTrigger>
                                <SelectContent>
                                    {roles.map((r) => (
                                        <SelectItem key={r.id} value={r.name}>{r.display_name}</SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {editRoleForm.errors.role && <p className="text-sm text-red-500 mt-1">{editRoleForm.errors.role}</p>}
                        </div>
                        <DialogFooter>
                            <Button variant="outline" type="button" onClick={() => setMemberToEdit(null)}>Cancel</Button>
                            <Button type="submit" disabled={editRoleForm.processing}>{editRoleForm.processing ? 'Saving...' : 'Save Changes'}</Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <AlertDialog open={!!memberToRemove} onOpenChange={() => setMemberToRemove(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader><AlertDialogTitle>Remove Member</AlertDialogTitle><AlertDialogDescription>Are you sure you want to remove {memberToRemove?.name}?</AlertDialogDescription></AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction onClick={handleRemoveMember}>Remove Member</AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>

            <AlertDialog open={!!invitationToRevoke} onOpenChange={() => setInvitationToRevoke(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader><AlertDialogTitle>Revoke Invitation</AlertDialogTitle><AlertDialogDescription>Are you sure you want to revoke for {invitationToRevoke?.email}?</AlertDialogDescription></AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction onClick={handleRevokeInvitation}>Revoke Invitation</AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </AppLayout>
    );
}
