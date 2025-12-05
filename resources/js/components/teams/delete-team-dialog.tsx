import { router } from '@inertiajs/react';
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
import workspaces from '@/routes/workspaces';

interface Team {
    id: number;
    name: string;
}

interface DeleteTeamDialogProps {
    workspaceSlug: string;
    team: Team | null;
    onClose: () => void;
}

export function DeleteTeamDialog({ 
    workspaceSlug, 
    team, 
    onClose 
}: DeleteTeamDialogProps) {
    const handleDelete = () => {
        if (!team) return;

        router.delete(workspaces.teams.destroy.url({ workspace: workspaceSlug, team: team.id }), {
            preserveScroll: true,
            onSuccess: () => onClose(),
        });
    };

    return (
        <AlertDialog open={!!team} onOpenChange={() => onClose()}>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>Delete Team</AlertDialogTitle>
                    <AlertDialogDescription>
                        Are you sure you want to delete "{team?.name}"?
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
    );
}
