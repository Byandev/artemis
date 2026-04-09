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

interface Item {
    id: number;
    sku: string;   
    product_id: number;
    sales_keywords: string;
    transaction_keywords: string;
    product?: {
        id: number;
        name: string;
    };
}

interface Props {
    item: Item | null;
    workspace: Workspace;
    onClose: () => void;                                                                                                            
}

export function DeleteItemDialog({ item, workspace, onClose }: Props) {
    const [processing, setProcessing] = useState(false);

    const handleDelete = () => {
        if (!item) return;

        router.delete(`/workspaces/${workspace.slug}/inventory/items/${item.id}`, {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onSuccess: () => {
                toast.success(`Item deleted successfully`);
            onClose();
            },
            onFinish: () => setProcessing(false),
        });
    };

    return (
        <AlertDialog open={!!item} onOpenChange={(open) => !open && onClose()}>
            <AlertDialogContent className="max-w-[400px] border-none shadow-2xl dark:bg-zinc-900">
                <AlertDialogHeader>
                    <AlertDialogTitle className="text-[16px] font-semibold text-gray-900 dark:text-gray-100">
                        Delete Inventory Record?
                    </AlertDialogTitle>
                    <AlertDialogDescription className="text-[13px] leading-relaxed text-gray-500 dark:text-gray-400">
                        Are you sure you want to delete the record for{' '}
                        <span className="font-medium text-gray-900 dark:text-gray-200">
                            {item?.product?.name || 'this product'}
                        </span>{' '}
                        on <span className="font-mono text-emerald-600 dark:text-emerald-400">{item?.transaction_keywords}</span>? 
                        This action cannot be undone.
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