import { useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { MemberSelector } from '@/components/teams/member-selector';
import workspaces from '@/routes/workspaces';
import { useEffect } from 'react';

interface User {
    id: number;
    name: string;
    email: string;
}

interface Team {
    id: number;
    name: string;
    members: User[];
}

interface EditTeamDialogProps {
    workspaceSlug: string;
    workspaceMembers: User[];
    team: Team | null;
    onClose: () => void;
}

export function EditTeamDialog({ 
    workspaceSlug, 
    workspaceMembers, 
    team,
    onClose,
}: EditTeamDialogProps) {
    const form = useForm({
        name: '',
        members: [] as number[],
    });

    // Update form when team changes
    useEffect(() => {
        if (team) {
            form.setData({
                name: team.name,
                members: team.members.map(m => m.id),
            });
        }
    }, [team]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!team) return;

        form.put(workspaces.teams.update.url({ workspace: workspaceSlug, team: team.id }), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                onClose();
            },
        });
    };

    const handleOpenChange = (open: boolean) => {
        if (!open) {
            form.reset();
            onClose();
        }
    };

    return (
        <Dialog open={!!team} onOpenChange={handleOpenChange}>
            <DialogContent className="sm:max-w-md">
                <form onSubmit={handleSubmit}>
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
                                value={form.data.name}
                                onChange={(e) => form.setData('name', e.target.value)}
                                required
                            />
                            {form.errors.name && (
                                <p className="text-sm text-destructive">{form.errors.name}</p>
                            )}
                        </div>

                        <MemberSelector
                            workspaceMembers={workspaceMembers}
                            selectedMemberIds={form.data.members}
                            onAddMember={(id) => form.setData('members', [...form.data.members, id])}
                            onRemoveMember={(id) => form.setData('members', form.data.members.filter(m => m !== id))}
                        />
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onClose}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing ? 'Saving...' : 'Save Changes'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
