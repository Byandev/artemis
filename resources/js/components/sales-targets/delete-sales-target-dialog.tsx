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
import { Workspace } from '@/types/models/Workspace';
import { useForm } from '@inertiajs/react';
import { toast } from 'sonner';

interface Target {
    id: number;
    name: string;
    date: string;
    team_count: number;
}

interface Props {
    workspace: Workspace;
    target: Target | null;
    onClose: () => void;
}

export function DeleteSalesTargetDialog({ workspace, target, onClose }: Props) {
    const { delete: destroy, processing } = useForm({});

    const handleDelete = () => {
        if (!target) return;

        destroy(
            `/workspaces/${workspace.slug}/sales-marketing/dashboard/sales-targets/${target.id}`,
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success('Sales target deleted');
                    onClose();
                },
            },
        );
    };

    return (
        <AlertDialog
            open={!!target}
            onOpenChange={(open) => !open && onClose()}
        >
            <AlertDialogContent className="max-w-[400px] border-none shadow-2xl dark:bg-zinc-900">
                <AlertDialogHeader>
                    <AlertDialogTitle className="text-[16px] font-semibold text-gray-900 dark:text-gray-100">
                        Delete Sales Target?
                    </AlertDialogTitle>
                    <AlertDialogDescription className="text-[13px] leading-relaxed text-gray-500 dark:text-gray-400">
                        <strong>{target?.name}</strong> for {target?.date},
                        including{' '}
                        {/* Naming the blast radius: the team amounts go too. */}
                        <strong>
                            {target?.team_count}{' '}
                            {target?.team_count === 1 ? 'team' : 'teams'}
                        </strong>{' '}
                        {target?.team_count === 1 ? 'amount' : 'amounts'}. This
                        action cannot be undone.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter className="mt-4 gap-2">
                    <AlertDialogCancel
                        disabled={processing}
                        className="h-9 rounded-lg border-black/8 bg-white px-4 font-mono! text-[12px]! font-medium text-gray-600 transition-all hover:bg-stone-50 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-300 dark:hover:bg-zinc-700"
                    >
                        Cancel
                    </AlertDialogCancel>
                    <AlertDialogAction
                        onClick={(e) => {
                            e.preventDefault();
                            handleDelete();
                        }}
                        disabled={processing}
                        className="h-9 rounded-lg bg-error-600 px-4 font-mono! text-[12px]! font-medium text-white transition-all hover:bg-error-700 disabled:opacity-50"
                    >
                        {processing ? 'Deleting...' : 'Confirm Delete'}
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}
