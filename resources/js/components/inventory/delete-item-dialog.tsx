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
    /** Summary view: the roll-up's product name (no `product` relation there). */
    product_name?: string | null;
    /** Summary view: true when the row is a parent standing for a group. */
    is_group?: boolean | number;
    /** How many SKUs the group holds. */
    child_count?: number;
}

interface Props {
    item: Item | null;
    workspace: Workspace;
    onClose: () => void;
}

export function DeleteItemDialog({ item, workspace, onClose }: Props) {
    const [processing, setProcessing] = useState(false);

    const isGroup = !!item?.is_group;
    const productName = item?.product?.name ?? item?.product_name ?? null;

    const handleDelete = () => {
        if (!item) return;

        router.delete(
            `/workspaces/${workspace.slug}/inventory/items/${item.id}`,
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onSuccess: () => {
                    toast.success(
                        isGroup
                            ? 'Group deleted — its SKUs were ungrouped, not removed'
                            : 'Item deleted successfully',
                    );
                    onClose();
                },
                onFinish: () => setProcessing(false),
            },
        );
    };

    return (
        <AlertDialog open={!!item} onOpenChange={(open) => !open && onClose()}>
            <AlertDialogContent className="max-w-[400px] border-none shadow-2xl dark:bg-zinc-900">
                <AlertDialogHeader>
                    <AlertDialogTitle className="text-[16px] font-semibold text-gray-900 dark:text-gray-100">
                        {isGroup ? 'Delete Group?' : 'Delete Inventory Record?'}
                    </AlertDialogTitle>
                    <AlertDialogDescription className="text-[13px] leading-relaxed text-gray-500 dark:text-gray-400">
                        {isGroup ? (
                            <>
                                Delete the group{' '}
                                <span className="font-mono font-medium text-gray-900 dark:text-gray-200">
                                    {item?.sku}
                                </span>
                                ? Its{' '}
                                <span className="font-medium text-gray-900 dark:text-gray-200">
                                    {item?.child_count ?? 0} SKU(s)
                                </span>{' '}
                                are kept — they are ungrouped and stay in the
                                list as standalone items. This action cannot be
                                undone.
                            </>
                        ) : (
                            <>
                                Are you sure you want to delete{' '}
                                <span className="font-mono font-medium text-gray-900 dark:text-gray-200">
                                    {item?.sku}
                                </span>
                                {productName ? (
                                    <>
                                        {' '}
                                        (
                                        <span className="font-medium text-gray-900 dark:text-gray-200">
                                            {productName}
                                        </span>
                                        )
                                    </>
                                ) : null}
                                ? This action cannot be undone.
                            </>
                        )}
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
