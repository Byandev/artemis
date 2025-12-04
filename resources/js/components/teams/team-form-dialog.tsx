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

interface TeamFormDialogProps {
    workspaceSlug: string;
    workspaceMembers: User[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
    team?: Team | null; // Optional: pass team for editing, omit for creating
    showTrigger?: boolean; // Control whether to show the trigger button
}

export function TeamFormDialog({
    workspaceSlug,
    workspaceMembers,
    open,
    onOpenChange,
    team,
    showTrigger = false,
}: TeamFormDialogProps) {
    const isEditing = !!team;

    const { data, setData, post, put, processing, errors, reset } = useForm({
        name: '',
        members: [] as number[],
    });

    // Set initial values when team changes (for editing)
    // eslint-disable-next-line react-hooks/exhaustive-deps
    useEffect(() => {
        if (team) {
            setData({
                name: team.name,
                members: team.members.map((m) => m.id),
            });
        } else {
            reset();
        }
    }, [team, open]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (isEditing) {
            put(workspaces.teams.update.url({ workspace: workspaceSlug, team: team.id }), {
                preserveScroll: true,
                onSuccess: () => {
                    reset();
                    onOpenChange(false);
                },
            });
        } else {
            post(workspaces.teams.store.url(workspaceSlug), {
                preserveScroll: true,
                onSuccess: () => {
                    reset();
                    onOpenChange(false);
                },
            });
        }
    };

    const handleOpenChange = (open: boolean) => {
        onOpenChange(open);
        if (!open) {
            reset();
        }
    };

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            {showTrigger && (
                <DialogTrigger asChild>
                    <Button>Create new Team</Button>
                </DialogTrigger>
            )}
            <DialogContent className="sm:max-w-md">
                <form onSubmit={handleSubmit}>
                    <DialogHeader>
                        <DialogTitle>
                            {isEditing ? 'Edit Team' : 'Create new Team'}
                        </DialogTitle>
                        <DialogDescription>
                            {isEditing
                                ? 'Update team name and members'
                                : 'Create a new team and add members from your workspace'}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="space-y-4 py-4">
                        <div className="space-y-2">
                            <Label htmlFor="name">Team Name</Label>
                            <Input
                                id="name"
                                placeholder="Enter team name"
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                required
                            />
                            {errors.name && (
                                <p className="text-sm text-destructive">{errors.name}</p>
                            )}
                        </div>

                        <MemberSelector
                            workspaceMembers={workspaceMembers}
                            selectedMemberIds={data.members}
                            onAddMember={(id) => setData('members', [...data.members, id])}
                            onRemoveMember={(id) =>
                                setData(
                                    'members',
                                    data.members.filter((m) => m !== id),
                                )
                            }
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
                        <Button type="submit" disabled={processing}>
                            {processing
                                ? isEditing
                                    ? 'Saving...'
                                    : 'Creating...'
                                : isEditing
                                  ? 'Save Changes'
                                  : 'Create Team'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
