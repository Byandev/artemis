import { Head, useForm, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
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
import { Badge } from '@/components/ui/badge';
import workspaces from '@/routes/workspaces';
import { useState } from 'react';
import { Workspace } from '@/types/models/Workspace';

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
    workspace: Workspace;
    members: User[];
    pendingInvitations: Invitation[];
    isAdmin: boolean;
}

export default function WorkspaceMembers({ workspace, members, pendingInvitations, isAdmin }: Props) {
    const [inviteDialogOpen, setInviteDialogOpen] = useState(false);
    const [memberToRemove, setMemberToRemove] = useState<User | null>(null);
    const [invitationToRevoke, setInvitationToRevoke] = useState<Invitation | null>(null);

    const inviteForm = useForm({
        email: '',
        role: 'member',
    });

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

    const handleRoleChange = (userId: number, newRole: string) => {
        router.put(
            workspaces.members.update.url({ workspace: workspace.slug, user: userId }),
            { role: newRole },
            { preserveScroll: true }
        );
    };

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

    const getRoleBadgeVariant = (role: string): "default" | "secondary" | "destructive" | "outline" => {
        switch (role) {
            case 'owner':
                return 'default';
            case 'admin':
                return 'secondary';
            default:
                return 'outline';
        }
    };

    return (
        <AppLayout>
            <Head title={`${workspace.name} - Members`} />

            <div className="space-y-6 p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold">Members</h1>
                        <p className="text-muted-foreground">Manage workspace members and invitations</p>
                    </div>
                    {isAdmin && (
                        <Dialog open={inviteDialogOpen} onOpenChange={setInviteDialogOpen}>
                            <DialogTrigger asChild>
                                <Button>Invite Member</Button>
                            </DialogTrigger>
                            <DialogContent>
                                <form onSubmit={handleInvite}>
                                    <DialogHeader>
                                        <DialogTitle>Invite Member</DialogTitle>
                                        <DialogDescription>
                                            Send an invitation to join this workspace
                                        </DialogDescription>
                                    </DialogHeader>
                                    <div className="space-y-4 py-4">
                                        <div className="space-y-2">
                                            <Label htmlFor="email">Email Address</Label>
                                            <Input
                                                id="email"
                                                type="email"
                                                placeholder="colleague@example.com"
                                                value={inviteForm.data.email}
                                                onChange={(e) => inviteForm.setData('email', e.target.value)}
                                                required
                                            />
                                            {inviteForm.errors.email && (
                                                <p className="text-sm text-destructive">{inviteForm.errors.email}</p>
                                            )}
                                        </div>
                                        <div className="space-y-2">
                                            <Label htmlFor="role">Role</Label>
                                            <Select
                                                value={inviteForm.data.role}
                                                onValueChange={(value) => inviteForm.setData('role', value)}
                                            >
                                                <SelectTrigger>
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="member">Member</SelectItem>
                                                    <SelectItem value="admin">Admin</SelectItem>
                                                </SelectContent>
                                            </Select>
                                            <p className="text-sm text-muted-foreground">
                                                Admins can manage members and settings
                                            </p>
                                        </div>
                                    </div>
                                    <DialogFooter>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={() => setInviteDialogOpen(false)}
                                        >
                                            Cancel
                                        </Button>
                                        <Button type="submit" disabled={inviteForm.processing}>
                                            {inviteForm.processing ? 'Sending...' : 'Send Invitation'}
                                        </Button>
                                    </DialogFooter>
                                </form>
                            </DialogContent>
                        </Dialog>
                    )}
                </div>

                {/* Members Table */}
                <Card>
                    <CardHeader>
                        <CardTitle>Workspace Members</CardTitle>
                        <CardDescription>
                            {members.length} {members.length === 1 ? 'member' : 'members'}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Name</TableHead>
                                    <TableHead>Email</TableHead>
                                    <TableHead>Role</TableHead>
                                    <TableHead>Joined</TableHead>
                                    {isAdmin && <TableHead className="text-right">Actions</TableHead>}
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {members.map((member) => (
                                    <TableRow key={member.id}>
                                        <TableCell className="font-medium">{member.name}</TableCell>
                                        <TableCell>{member.email}</TableCell>
                                        <TableCell>
                                            {isAdmin && member.pivot.role !== 'owner' ? (
                                                <Select
                                                    value={member.pivot.role}
                                                    onValueChange={(value) => handleRoleChange(member.id, value)}
                                                >
                                                    <SelectTrigger className="w-[110px]">
                                                        <SelectValue />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem value="member">Member</SelectItem>
                                                        <SelectItem value="admin">Admin</SelectItem>
                                                    </SelectContent>
                                                </Select>
                                            ) : (
                                                <Badge variant={getRoleBadgeVariant(member.pivot.role)}>
                                                    {member.pivot.role}
                                                </Badge>
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            {new Date(member.pivot.created_at).toLocaleDateString()}
                                        </TableCell>
                                        {isAdmin && (
                                            <TableCell className="text-right">
                                                {member.pivot.role !== 'owner' && (
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() => setMemberToRemove(member)}
                                                    >
                                                        Remove
                                                    </Button>
                                                )}
                                            </TableCell>
                                        )}
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>

                {/* Pending Invitations */}
                {pendingInvitations.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Pending Invitations</CardTitle>
                            <CardDescription>
                                {pendingInvitations.length} pending{' '}
                                {pendingInvitations.length === 1 ? 'invitation' : 'invitations'}
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Email</TableHead>
                                        <TableHead>Role</TableHead>
                                        <TableHead>Invited By</TableHead>
                                        <TableHead>Expires</TableHead>
                                        {isAdmin && <TableHead className="text-right">Actions</TableHead>}
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {pendingInvitations.map((invitation) => (
                                        <TableRow key={invitation.id}>
                                            <TableCell className="font-medium">{invitation.email}</TableCell>
                                            <TableCell>
                                                <Badge variant={getRoleBadgeVariant(invitation.role)}>
                                                    {invitation.role}
                                                </Badge>
                                            </TableCell>
                                            <TableCell>{invitation.inviter.name}</TableCell>
                                            <TableCell>
                                                {new Date(invitation.expires_at).toLocaleDateString()}
                                            </TableCell>
                                            {isAdmin && (
                                                <TableCell className="text-right space-x-2">
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() => handleResendInvitation(invitation.id)}
                                                    >
                                                        Resend
                                                    </Button>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() => setInvitationToRevoke(invitation)}
                                                    >
                                                        Revoke
                                                    </Button>
                                                </TableCell>
                                            )}
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>
                )}
            </div>

            {/* Remove Member Confirmation Dialog */}
            <AlertDialog open={!!memberToRemove} onOpenChange={() => setMemberToRemove(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Remove Member</AlertDialogTitle>
                        <AlertDialogDescription>
                            Are you sure you want to remove {memberToRemove?.name} from this workspace?
                            They will lose access to all workspace resources.
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

            {/* Revoke Invitation Confirmation Dialog */}
            <AlertDialog open={!!invitationToRevoke} onOpenChange={() => setInvitationToRevoke(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Revoke Invitation</AlertDialogTitle>
                        <AlertDialogDescription>
                            Are you sure you want to revoke the invitation for {invitationToRevoke?.email}?
                            The invitation link will no longer be valid.
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
