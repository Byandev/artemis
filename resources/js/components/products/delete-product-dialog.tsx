import { useForm } from '@inertiajs/react';
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
import { Product } from '@/types/models/Product';
import { Workspace } from '@/types/models/Workspace';
import workspaces from '@/routes/workspaces';

interface DeleteProductDialogProps {
    product: Product | null;
    workspace: Workspace;
    onClose: () => void;
}

export function DeleteProductDialog({
    product,
    workspace,
    onClose,
}: DeleteProductDialogProps) {
    const { delete: destroy, processing } = useForm({});

    const handleDelete = () => {
        if (!product) return;

        destroy(workspaces.products.destroy.url({ workspace, product }), {
            onSuccess: () => {
                onClose();
            },
        });
    };

    return (
        <AlertDialog open={!!product} onOpenChange={(open) => !open && onClose()}>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>Delete Product</AlertDialogTitle>
                    <AlertDialogDescription>
                        Are you sure you want to delete <strong>{product?.name}</strong>{' '}
                        (Code: {product?.code})? This action cannot be undone.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel disabled={processing}>Cancel</AlertDialogCancel>
                    <AlertDialogAction
                        onClick={handleDelete}
                        disabled={processing}
                        className="bg-destructive text-black hover:bg-destructive/90"
                    >
                        {processing ? 'Deleting...' : 'Delete'}
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}
