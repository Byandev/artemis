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
import { useForm } from '@inertiajs/react';
import { toast } from 'sonner';
import { WalletRow } from './types';

interface Props {
    workspaceSlug: string;
    /** The wallet awaiting confirmation; null keeps the dialog shut. */
    wallet: WalletRow | null;
    onClose: () => void;
}

/**
 * Confirms deleting a wallet. The ledger cascades off the account row, so the
 * copy names how many entries go with it rather than asking about the wallet
 * alone.
 */
export function DeleteWalletDialog({ workspaceSlug, wallet, onClose }: Props) {
    const { delete: destroy, processing } = useForm({});

    const handleDelete = () => {
        if (!wallet) return;

        destroy(
            `/workspaces/${workspaceSlug}/sales-marketing/go-tyme-balance/${wallet.id}`,
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success('Account deleted');
                    onClose();
                },
            },
        );
    };

    const entries = wallet?.entry_count ?? 0;

    return (
        <AlertDialog
            open={!!wallet}
            onOpenChange={(open) => !open && onClose()}
        >
            <AlertDialogContent className="max-w-[400px] border-none shadow-2xl dark:bg-zinc-900">
                <AlertDialogHeader>
                    <AlertDialogTitle className="text-[16px] font-semibold text-gray-900 dark:text-gray-100">
                        Delete Account?
                    </AlertDialogTitle>
                    <AlertDialogDescription className="text-[13px] leading-relaxed text-gray-500 dark:text-gray-400">
                        <strong>{wallet?.name}</strong>
                        {/* Naming the blast radius: the liquidation ledger
                            goes with the wallet. */}
                        {entries === 0 ? (
                            <>, which has no liquidation entries.</>
                        ) : (
                            <>
                                , including its{' '}
                                <strong>
                                    {entries}{' '}
                                    {entries === 1 ? 'entry' : 'entries'}
                                </strong>
                                .
                            </>
                        )}{' '}
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
                        className="h-9 rounded-lg bg-error-600 px-4 font-mono! text-[12px]! font-medium text-white transition-all hover:bg-error-700 disabled:opacity-50"
                    >
                        {processing ? 'Deleting...' : 'Confirm Delete'}
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}
