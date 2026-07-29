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
import { router } from '@inertiajs/react';
import { useState } from 'react';
import { toast } from 'sonner';

interface TestOrder {
    id: number;
    customer_name: string | null;
}

interface Props {
    order: TestOrder | null;
    workspace: Workspace;
    onClose: () => void;
}

/** Only ever shown for hand-made test orders; the server refuses synced rows. */
export function DeleteTestOrderDialog({ order, workspace, onClose }: Props) {
    const [processing, setProcessing] = useState(false);

    const handleDelete = () => {
        if (!order) return;

        router.delete(
            `/workspaces/${workspace.slug}/gencys/daily-sales-tracker/${order.id}`,
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onSuccess: () => {
                    toast.success('Test order deleted');
                    onClose();
                },
                onError: () => toast.error('Failed to delete test order'),
                onFinish: () => setProcessing(false),
            },
        );
    };

    return (
        <AlertDialog open={!!order} onOpenChange={(open) => !open && onClose()}>
            <AlertDialogContent className="max-w-[400px] border-none shadow-2xl dark:bg-zinc-900">
                <AlertDialogHeader>
                    <AlertDialogTitle className="text-[16px] font-semibold text-gray-900 dark:text-gray-100">
                        Delete Test Order?
                    </AlertDialogTitle>
                    <AlertDialogDescription className="text-[13px] leading-relaxed text-gray-500 dark:text-gray-400">
                        Test order{' '}
                        <span className="font-mono text-emerald-600 dark:text-emerald-400">
                            #{order?.id}
                        </span>
                        {order?.customer_name ? ` (${order.customer_name})` : ''}{' '}
                        and its line items will be removed. This action cannot be
                        undone.
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
