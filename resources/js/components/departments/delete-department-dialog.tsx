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
import { Workspace } from '@/types/models/Workspace';
import { useForm } from '@inertiajs/react';
import { toast } from 'sonner';

interface Department {
    id: number;
    name: string;
}

interface DeleteDepartmentDialogProps {
    workspace: Workspace;
    department: Department | null;
    onClose: () => void;
}

export function DeleteDepartmentDialog({
    department,
    workspace,
    onClose,
}: DeleteDepartmentDialogProps) {
    const { delete: destroy, processing } = useForm({});

    const handleDelete = () => {
        if (!department) return;

        destroy(
            workspaces.departments.destroy.url({ workspace, department: department.id }),
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success('Department deleted successfully');
                    onClose();
                },
            },
        );
    };

    return (
        <AlertDialog
            open={!!department}
            onOpenChange={(open) => !open && onClose()}
        >
            <AlertDialogContent className="max-w-[400px] border-none shadow-2xl dark:bg-zinc-900">
                <AlertDialogHeader>
                    <AlertDialogTitle className="text-[16px] font-semibold text-gray-900 dark:text-gray-100">
                        Delete Department?
                    </AlertDialogTitle>
                    <AlertDialogDescription className="text-[13px] leading-relaxed text-gray-500 dark:text-gray-400">
                        Are you sure you want to delete the department{' '}
                        <strong>{department?.name}</strong>? This action cannot
                        be undone.
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
                        className="h-9 rounded-lg bg-red-600 px-4 font-mono! text-[12px]! font-medium text-white transition-all hover:bg-red-700 disabled:opacity-50"
                    >
                        {processing ? 'Deleting...' : 'Confirm Delete'}
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}
