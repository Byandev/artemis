import { router } from '@inertiajs/react';
import { Dialog, DialogContent } from '@/components/ui/dialog';
import { Workspace } from '@/types/models/Workspace';
import { Trash2 } from 'lucide-react';
import workspaces from '@/routes/workspaces';
import { toast } from 'sonner';

interface Team {
    id: number;
    name: string;
}

interface DeleteTeamDialogProps {
    workspace: Workspace;
    team: Team | null;
    onClose: () => void;
}

export function DeleteTeamDialog({ workspace, team, onClose }: DeleteTeamDialogProps) {
    const handleDelete = () => {
        if (!team) return;
        router.delete(workspaces.teams.destroy.url({ workspace, team: team.id }), {
            preserveScroll: true,
            onSuccess: () => {
                toast.success(`Team "${team.name}" has been deleted.`);
                onClose();
            },
        });
    };

    return (
        <Dialog open={!!team} onOpenChange={() => onClose()}>
            <DialogContent className="sm:max-w-sm p-0 gap-0 overflow-hidden">
                <div className="flex flex-col items-center px-6 pt-6 pb-5 text-center">
                    <div className="mb-4 flex h-11 w-11 items-center justify-center rounded-xl bg-red-50 dark:bg-red-500/10">
                        <Trash2 className="h-5 w-5 text-red-500 dark:text-red-400" />
                    </div>
                    <h3 className="text-[15px] font-semibold text-gray-900 dark:text-gray-100">Delete Team</h3>
                    <p className="mt-1.5 text-[12px] text-gray-400 dark:text-gray-500">
                        Are you sure you want to delete{' '}
                        <span className="font-semibold text-gray-700 dark:text-gray-300">"{team?.name}"</span>?
                        This action cannot be undone.
                    </p>
                </div>
                <div className="flex items-center justify-end gap-2 border-t border-black/6 dark:border-white/6 px-5 py-3">
                    <button
                        type="button"
                        onClick={onClose}
                        className="flex h-9 items-center rounded-lg border border-black/8 bg-stone-100 px-4 font-mono! text-[12px]! font-medium text-gray-600 transition-all hover:bg-stone-200 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-300 dark:hover:bg-zinc-700"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        onClick={handleDelete}
                        className="flex h-9 items-center rounded-lg bg-red-600 px-4 font-mono! text-[12px]! font-medium text-white transition-all hover:bg-red-700"
                    >
                        Delete Team
                    </button>
                </div>
            </DialogContent>
        </Dialog>
    );
}
