
import { toast } from 'sonner';
import { useForm } from '@inertiajs/react';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from '@/components/ui/dialog';
import { MemberSelector } from '@/components/teams/member-selector';
import { Workspace } from '@/types/models/Workspace';
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
    workspace: Workspace;
    workspaceMembers: User[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
    team?: Team | null;
}

export function TeamFormDialog({ workspace, workspaceMembers, open, onOpenChange, team }: TeamFormDialogProps) {
    const isEditing = !!team;

    const { data, setData, post, put, processing, errors, reset } = useForm({
        name: '',
        members: [] as number[],
    });

    useEffect(() => {
        if (team) {
            setData({ name: team.name, members: team.members.map((m) => m.id) });
        } else {
            reset();
        }
    }, [team, open]);

    const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();

    if (isEditing) {
        put(workspaces.teams.update.url({ workspace, team: team.id }), {
            preserveScroll: true,
            onSuccess: () => { 
                toast.success('Team updated successfully!');
                reset(); 
                onOpenChange(false); 
            },
        });
    } else {
        post(workspaces.teams.store.url({ workspace }), {
            preserveScroll: true,
            onSuccess: () => { 
                // 3. Add the success notification for Creating
                toast.success('Team created successfully!');
                reset(); 
                onOpenChange(false); 
            },
        });
    }
};

    const handleOpenChange = (open: boolean) => {
        onOpenChange(open);
        if (!open) reset();
    };

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <DialogContent className="sm:max-w-md p-0 gap-0 overflow-hidden">
                <div className="px-5 pt-5 pb-4 border-b border-black/6 dark:border-white/6">
                    <DialogHeader>
                        <DialogTitle className="text-[15px] font-semibold text-gray-900 dark:text-gray-100">
                            {isEditing ? 'Edit Team' : 'Create Team'}
                        </DialogTitle>
                        <DialogDescription className="text-[12px] text-gray-400 dark:text-gray-500 mt-0.5">
                            {isEditing ? 'Update team name and members' : 'Create a new team and add members from your workspace'}
                        </DialogDescription>
                    </DialogHeader>
                </div>

                <form onSubmit={handleSubmit}>
                    <div className="space-y-5 px-5 py-4">
                        {/* Team Name */}
                        <div className="space-y-1.5">
                            <label className="block font-mono text-[10px] font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500">
                                Team Name <span className="text-red-400">*</span>
                            </label>
                            <input
                                type="text"
                                autoFocus
                                placeholder="Enter team name"
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                className="h-10 w-full rounded-[10px] border border-black/8 bg-stone-50 px-3 font-mono! text-[13px]! text-gray-800 placeholder:text-gray-300 outline-none transition-all focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-100 dark:placeholder:text-gray-600 dark:focus:border-emerald-400"
                            />
                            {errors.name && <p className="font-mono text-[11px] text-red-500">{errors.name}</p>}
                        </div>

                        {/* Member Selector */}
                        <MemberSelector
                            workspaceMembers={workspaceMembers}
                            selectedMemberIds={data.members}
                            onAddMember={(id) => setData('members', [...data.members, id])}
                            onRemoveMember={(id) => setData('members', data.members.filter((m) => m !== id))}
                        />
                    </div>

                    <div className="flex items-center justify-end gap-2 border-t border-black/6 dark:border-white/6 px-5 py-3">
                        <button
                            type="button"
                            onClick={() => onOpenChange(false)}
                            className="flex h-9 items-center rounded-lg border border-black/8 bg-stone-100 px-4 font-mono! text-[12px]! font-medium text-gray-600 transition-all hover:bg-stone-200 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-300 dark:hover:bg-zinc-700"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            disabled={processing}
                            className="flex h-9 items-center rounded-lg bg-emerald-600 px-4 font-mono! text-[12px]! font-medium text-white transition-all hover:bg-emerald-700 disabled:opacity-50"
                        >
                            {processing ? (isEditing ? 'Saving…' : 'Creating…') : (isEditing ? 'Save Changes' : 'Create Team')}
                        </button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}
