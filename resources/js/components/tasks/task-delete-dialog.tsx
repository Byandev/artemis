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
import { Task } from './types';

interface TaskDeleteDialogProps {
    isDeleting: boolean;
    onConfirm: () => void;
    onOpenChange: (open: boolean) => void;
    open: boolean;
    task: Task | null;
}

export function TaskDeleteDialog({
    isDeleting,
    onConfirm,
    onOpenChange,
    open,
    task,
}: TaskDeleteDialogProps) {
    return (
        <AlertDialog open={open} onOpenChange={onOpenChange}>
            <AlertDialogContent className="rounded-xl border border-black/8 dark:border-white/8">
                <AlertDialogHeader>
                    <AlertDialogTitle className="font-mono text-[14px] tracking-wide uppercase">
                        Delete Task
                    </AlertDialogTitle>
                    <AlertDialogDescription className="text-[12px] text-gray-500 dark:text-gray-400">
                        {task
                            ? `Are you sure you want to delete "${task.name}"? Comments and assignee progress will be removed too.`
                            : 'This task is no longer available.'}
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel
                        disabled={isDeleting}
                        className="h-8 rounded-lg text-[12px]"
                    >
                        Cancel
                    </AlertDialogCancel>
                    <AlertDialogAction
                        disabled={isDeleting}
                        className="h-8 rounded-lg bg-red-600 text-[12px] text-white hover:bg-red-700"
                        onClick={onConfirm}
                    >
                        Delete
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}
