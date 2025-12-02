import { useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { MemberSelector } from '@/components/teams/member-selector';
import workspaces from '@/routes/workspaces';

interface User {
    id: number;
    name: string;
    email: string;
}

interface CreateTeamDialogProps {
    workspaceSlug: string;
    workspaceMembers: User[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
}

export function CreateTeamDialog({ 
    workspaceSlug, 
    workspaceMembers, 
    open, 
    onOpenChange 
}: CreateTeamDialogProps) {
    const form = useForm({
        name: '',
        members: [] as number[],
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post(workspaces.teams.store.url(workspaceSlug), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                onOpenChange(false);
            },
        });
    };

    const handleOpenChange = (open: boolean) => {
        onOpenChange(open);
        if (!open) {
            form.reset();
        }
    };

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <DialogTrigger asChild>
                <Button>Create new Team</Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-md">
                <form onSubmit={handleSubmit}>
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
                            onClick={() => onOpenChange(false)}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing ? 'Creating...' : 'Create Team'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
