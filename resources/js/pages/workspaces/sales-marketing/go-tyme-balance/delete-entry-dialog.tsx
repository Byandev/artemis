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
import { LiquidationEntry, peso, postedDate } from './types';

interface Props {
    /** Base URL of the wallet's entries collection. */
    entriesUrl: string;
    /** The entry awaiting confirmation; null keeps the dialog shut. */
    entry: LiquidationEntry | null;
    onClose: () => void;
}

/**
 * Confirms deleting one line of the liquidation ledger. Deleting it re-flows
 * every balance after it, so the copy says so rather than leaving the effect to
 * be discovered.
 */
export function DeleteEntryDialog({ entriesUrl, entry, onClose }: Props) {
    const { delete: destroy, processing } = useForm({});

    const handleDelete = () => {
        if (!entry) return;

        destroy(`${entriesUrl}/${entry.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Entry deleted');
                onClose();
            },
        });
    };

    return (
        <AlertDialog open={!!entry} onOpenChange={(open) => !open && onClose()}>
            <AlertDialogContent className="max-w-[400px] border-none shadow-2xl dark:bg-zinc-900">
                <AlertDialogHeader>
                    <AlertDialogTitle className="text-[16px] font-semibold text-gray-900 dark:text-gray-100">
                        Delete Entry?
                    </AlertDialogTitle>
                    <AlertDialogDescription className="text-[13px] leading-relaxed text-gray-500 dark:text-gray-400">
                        The{' '}
                        <strong>{entry ? postedDate(entry.date) : ''}</strong>{' '}
                        <strong>
                            {entry?.transaction_type_name ?? entry?.description}
                        </strong>{' '}
                        entry of{' '}
                        <strong>{entry ? peso(entry.amount) : ''}</strong>.
                        {/* The running balance is not a per-row figure: every
                            entry after this one moves with it. */}{' '}
                        Every balance after it will be recalculated. This action
                        cannot be undone.
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
