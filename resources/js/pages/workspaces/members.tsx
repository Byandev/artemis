import ComponentCard from '@/components/common/ComponentCard';
import PageHeader from '@/components/common/PageHeader';
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
import { PaginatedData, SharedData, User } from '@/types';
import { Workspace } from '@/types/models/Workspace';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import axios from 'axios';
import { ColumnDef } from '@tanstack/react-table';
import { omit } from 'lodash';
import { MoreHorizontal, Send, Trash2, UserMinus, CopyleftIcon, UserCog, KeyRound } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Role } from '@/types/models/Role';
import { toast } from 'sonner';

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
        invitation_sort?: string | null;
        invitation_page?: number | string;
        filter?: {
            search?: string;
        };
    };
}

export default function WorkspaceMembers({ workspace, members, pendingInvitations, isAdmin, query, roles }: Props) {
    const { auth } = usePage<SharedData>().props;
    const canRemoveMembers = auth?.user?.id === workspace.owner_id;
    const initialSorting = useMemo(() => toFrontendSort(query?.sort ?? null), [query?.sort]);
    const initialInvitationSorting = useMemo(() => toFrontendSort(query?.invitation_sort ?? null), [query?.invitation_sort]);

    const [inviteDialogOpen, setInviteDialogOpen] = useState(false);
    const [memberToRemove, setMemberToRemove] = useState<User | null>(null);
    const [memberToUpdateRole, setMemberToUpdateRole] = useState<User | null>(null);
    const [invitationToRevoke, setInvitationToRevoke] = useState<Invitation | null>(null);
    const [copiedMemberId, setCopiedMemberId] = useState<number | null>(null);

    const inviteForm = useForm({
        email: '',
        role_id: '',
    });

    const updateRoleForm = useForm({
        role_id: '',
    });

    const showNoPermissionToast = () => {
        toast.error("You don't have this permission.", {
            duration: 3000,
            closeButton: false,
            classNames: {
                toast: '!w-auto !min-w-0 !max-w-max max-w-[calc(100vw-1rem)] rounded-lg border border-black/8 bg-white px-2.5 py-1.5 shadow-theme-xs data-[state=open]:animate-in data-[state=open]:fade-in-0 data-[state=open]:slide-in-from-right-4 data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=closed]:slide-out-to-top-2 dark:border-white/10 dark:bg-zinc-900',
                title: 'font-mono text-[12px] leading-tight font-semibold text-gray-800 dark:text-gray-100',
            },
        });
    };

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

        if (!canRemoveMembers) {
            showNoPermissionToast();
            setMemberToRemove(null);
            return;
        }

        router.delete(
            workspaces.members.destroy.url({ workspace: workspace.slug, user: memberToRemove.id }),
            {
                preserveScroll: true,
                onSuccess: () => setMemberToRemove(null),
                onError: () => {
                    showNoPermissionToast();
                    setMemberToRemove(null);
                },
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

    const copyResetPasswordUrl = async (member: User) => {
        try {
            const res = await axios.post(
                workspaces.members.resetPassword.url({ workspace: workspace.slug, user: member.id })
            );
            await navigator.clipboard.writeText(res.data.url);
            setCopiedMemberId(member.id);
            setTimeout(() => setCopiedMemberId(null), 2000);
        } catch (error) {
            console.error('Failed to generate reset link:', error);
        }
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
            id: 'role',
            accessorFn: (row) => row.pivot?.role,
            enableSorting: true,
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

                if (member.id === workspace.owner_id) return null;

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
                            <DropdownMenuItem onClick={() => copyResetPasswordUrl(member)}>
                                <KeyRound className="mr-2 h-4 w-4" />
                                {copiedMemberId === member.id ? 'Copied!' : 'Copy Reset Link'}
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem
                                onClick={() => {
                                    if (!canRemoveMembers) {
                                        showNoPermissionToast();
                                        return;
                                    }

                                    setMemberToRemove(member);
                                }}
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
            id: 'role_name',
            accessorFn: (row) => row.role?.name,
            enableSorting: true,
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
                <PageHeader title="Members" description="Manage workspace members and their roles">

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
                </PageHeader>

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
                                        invitation_sort: query?.invitation_sort,
                                        invitation_page: query?.invitation_page,
                                    },
                                    { preserveState: false, replace: true, preserveScroll: true },
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
                                    initialSorting={initialInvitationSorting}
                                    meta={{ ...omit(pendingInvitations, ['data']) }}
                                    onFetch={(params) => {
                                        router.get(
                                            `/workspaces/${workspace.slug}/members`,
                                            {
                                                sort: query?.sort,
                                                page: query?.page,
                                                invitation_sort: params?.sort,
                                                invitation_page: params?.page ?? 1,
                                            },
                                            { preserveState: false, replace: true, preserveScroll: true },
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
                onOpenChange={(open) => {
                    if (!open) setMemberToRemove(null);
                }}
            >
                <AlertDialogContent className="max-w-md rounded-2xl border border-black/8 p-0 dark:border-white/8">
                    <div className="p-6">
                        <AlertDialogHeader>
                            <AlertDialogTitle className="text-[15px] font-semibold text-gray-900 dark:text-gray-100">
                                Remove Member
                            </AlertDialogTitle>
                            <AlertDialogDescription className="text-[12px] text-gray-500 dark:text-gray-400">
                                Are you sure you want to remove{' '}
                                <span className="font-semibold text-gray-800 dark:text-gray-200">{memberToRemove?.name}</span>{' '}
                                from this workspace?
                            </AlertDialogDescription>
                        </AlertDialogHeader>

                        {memberToRemove && (
                            <div className="mt-4 flex items-center gap-3 rounded-[10px] border border-black/6 bg-stone-50 px-3 py-2.5 text-left dark:border-white/6 dark:bg-zinc-800">
                                <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-red-100 font-mono text-[12px] font-semibold text-red-600 dark:bg-red-900/40 dark:text-red-400">
                                    {memberToRemove.name?.charAt(0).toUpperCase()}
                                </div>
                                <div className="min-w-0">
                                    <p className="truncate text-[13px] font-medium text-gray-800 dark:text-gray-100">{memberToRemove.name}</p>
                                    <p className="truncate font-mono text-[11px] text-gray-400 dark:text-gray-500">{memberToRemove.email}</p>
                                </div>
                            </div>
                        )}

                        <div className="mt-4 rounded-xl border border-red-100 bg-red-50/60 p-3 dark:border-red-900/30 dark:bg-red-950/20">
                            <p className="text-[11px] font-medium text-red-700 dark:text-red-400">
                                They will lose access to all workspace resources immediately.
                            </p>
                        </div>

                        <AlertDialogFooter className="mt-5">
                            <AlertDialogCancel className="h-9 rounded-lg border border-black/8 bg-stone-100 font-mono text-[12px] font-medium text-gray-600 transition-all hover:bg-stone-200 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-300 dark:hover:bg-zinc-700">
                                Cancel
                            </AlertDialogCancel>
                            <AlertDialogAction
                                onClick={handleRemoveMember}
                                className="h-9 rounded-lg bg-red-600 font-mono text-[12px] font-medium text-white transition-all hover:bg-red-700"
                            >
                                Remove Member
                            </AlertDialogAction>
                        </AlertDialogFooter>
                    </div>
                </AlertDialogContent>
            </AlertDialog>

            {/* Update Member Role Dialog */}
            <Dialog open={!!memberToUpdateRole} onOpenChange={(open) => { if (!open) { setMemberToUpdateRole(null); updateRoleForm.reset(); } }}>
                <DialogContent className="sm:max-w-md p-0 gap-0 overflow-hidden">
                    <div className="px-5 pt-5 pb-4 border-b border-black/6 dark:border-white/6">
                        <DialogHeader>
                            <DialogTitle className="text-[15px] font-semibold text-gray-900 dark:text-gray-100">
                                Change Role
                            </DialogTitle>
                            <DialogDescription className="sr-only">
                                Change role for {memberToUpdateRole?.name}
                            </DialogDescription>
                        </DialogHeader>
                    </div>

                    <form onSubmit={handleUpdateRole}>
                        <div className="px-5 py-4 space-y-4">
                            {/* Member info */}
                            <div className="flex items-center gap-3 rounded-[10px] border border-black/6 bg-stone-50 px-3 py-2.5 dark:border-white/6 dark:bg-zinc-800">
                                <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-emerald-100 font-mono text-[12px] font-semibold text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-400">
                                    {memberToUpdateRole?.name?.charAt(0).toUpperCase()}
                                </div>
                                <div className="min-w-0">
                                    <p className="truncate text-[13px] font-medium text-gray-800 dark:text-gray-100">{memberToUpdateRole?.name}</p>
                                    <p className="truncate font-mono text-[11px] text-gray-400 dark:text-gray-500">{memberToUpdateRole?.email}</p>
                                </div>
                                {memberToUpdateRole?.pivot?.role && (
                                    <span className="ml-auto shrink-0 rounded-full bg-gray-100 px-2 py-0.5 font-mono text-[10px] font-medium text-gray-500 dark:bg-zinc-700 dark:text-gray-400">
                                        {memberToUpdateRole.pivot.role}
                                    </span>
                                )}
                            </div>

                            {/* Role selector */}
                            <div className="space-y-1.5">
                                <label className="block font-mono text-[10px] font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500">
                                    New Role <span className="text-red-400">*</span>
                                </label>
                                <Select
                                    value={updateRoleForm.data.role_id}
                                    onValueChange={(value) => updateRoleForm.setData('role_id', value)}
                                >
                                    <SelectTrigger className="h-10 w-full rounded-[10px] border border-black/8 bg-stone-50 px-3 font-mono! text-[13px]! text-gray-800 outline-none transition-all focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-100">
                                        <SelectValue placeholder="Select a role…" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {roles.map((role) => (
                                            <SelectItem key={role.id} value={role.id.toString()} className="font-mono text-[12px]">
                                                {role.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {updateRoleForm.errors.role_id && (
                                    <p className="font-mono text-[11px] text-red-500">{updateRoleForm.errors.role_id}</p>
                                )}
                            </div>
                        </div>

                        <div className="flex items-center justify-end gap-2 border-t border-black/6 dark:border-white/6 px-5 py-3">
                            <button
                                type="button"
                                onClick={() => { setMemberToUpdateRole(null); updateRoleForm.reset(); }}
                                className="flex h-9 items-center rounded-lg border border-black/8 bg-stone-100 px-4 font-mono! text-[12px]! font-medium text-gray-600 transition-all hover:bg-stone-200 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-300 dark:hover:bg-zinc-700"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                disabled={updateRoleForm.processing}
                                className="flex h-9 items-center rounded-lg bg-emerald-600 px-4 font-mono! text-[12px]! font-medium text-white transition-all hover:bg-emerald-700 disabled:opacity-50"
                            >
                                {updateRoleForm.processing ? 'Saving…' : 'Save Changes'}
                            </button>
                        </div>
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
