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
import { router } from '@inertiajs/react';
import { useState } from 'react';
import { toast } from 'sonner';

interface Props {
    open: boolean;
    onClose: () => void;
    title: string;
    description: string;
    url: string;
    successMessage?: string;
}

export function FinanceDeleteDialog({ open, onClose, title, description, url, successMessage = 'Deleted.' }: Props) {
    const [processing, setProcessing] = useState(false);

    const handleDelete = () => {
        router.delete(url, {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onSuccess: () => {
                toast.success(successMessage);
                onClose();
            },
            onFinish: () => setProcessing(false),
        });
    };

    return (
        <AlertDialog open={open} onOpenChange={(o) => !o && onClose()}>
            <AlertDialogContent className="max-w-[400px] border-none shadow-2xl dark:bg-zinc-900">
                <AlertDialogHeader>
                    <AlertDialogTitle className="text-[16px] font-semibold text-gray-900 dark:text-gray-100">
                        {title}
                    </AlertDialogTitle>
                    <AlertDialogDescription className="text-[13px] leading-relaxed text-gray-500 dark:text-gray-400">
                        {description}
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
                        onClick={(e) => { e.preventDefault(); handleDelete(); }}
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
