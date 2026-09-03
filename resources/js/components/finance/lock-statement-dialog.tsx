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
import { useEffect, useState } from 'react';
import { toast } from 'sonner';

interface Props {
    open: boolean;
    onClose: () => void;
    /** Locking closes the month; unlocking reopens it. */
    mode: 'lock' | 'unlock';
    /** The statement's name — its month, e.g. "May 2026". Typed to confirm. */
    statementName: string;
    /** The statement's lock endpoint: POST to lock, DELETE to unlock. */
    url: string;
}

/**
 * Lock / unlock confirmation, gated on typing the statement's name.
 *
 * Both directions move real money figures — locking freezes what gets reported,
 * unlocking lets a settled month start moving again — and both are one click
 * from a page someone is only reading. Naming the statement is the step that
 * makes it a decision rather than a reflex, and it also catches the commonest
 * mistake: doing it to the month next to the one you meant.
 */
export function LockStatementDialog({
    open,
    onClose,
    mode,
    statementName,
    url,
}: Props) {
    const [typed, setTyped] = useState('');
    const [processing, setProcessing] = useState(false);
    const locking = mode === 'lock';

    // A fresh box each time it opens, so a name left over from a cancelled
    // attempt can't confirm the next one.
    useEffect(() => {
        if (open) setTyped('');
    }, [open, mode]);

    // Case and stray spaces aren't what is being checked — that the right
    // statement was named is.
    const confirmed =
        typed.trim().toLowerCase() === statementName.trim().toLowerCase();

    const submit = () => {
        if (!confirmed || processing) return;

        const options = {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onSuccess: () => onClose(),
            onError: () => toast.error('Could not update the lock.'),
            onFinish: () => setProcessing(false),
        };

        if (locking) {
            router.post(url, {}, options);
        } else {
            router.delete(url, options);
        }
    };

    return (
        <AlertDialog open={open} onOpenChange={(o) => !o && onClose()}>
            <AlertDialogContent className="max-w-[440px] border-none shadow-2xl dark:bg-zinc-900">
                <AlertDialogHeader>
                    <AlertDialogTitle className="text-[16px] font-semibold text-gray-900 dark:text-gray-100">
                        {locking ? 'Lock' : 'Unlock'} {statementName}
                    </AlertDialogTitle>
                    <AlertDialogDescription className="text-[13px] leading-relaxed text-gray-500 dark:text-gray-400">
                        {locking
                            ? 'Closing this month holds its figures as they stand. It can no longer be regenerated, saved over, deleted, or given a loss carryover until it is unlocked.'
                            : 'Reopening this month lets its figures move again — it can be regenerated, saved over and deleted, and a regenerate will pull whatever the orders and transactions say today.'}
                    </AlertDialogDescription>
                </AlertDialogHeader>

                <div className="mt-1">
                    <label
                        htmlFor="lock-confirm-name"
                        className="block text-[12px] text-gray-500 dark:text-gray-400"
                    >
                        Type{' '}
                        <span className="font-mono font-semibold text-gray-800 dark:text-gray-100">
                            {statementName}
                        </span>{' '}
                        to confirm.
                    </label>
                    <input
                        id="lock-confirm-name"
                        autoFocus
                        autoComplete="off"
                        value={typed}
                        onChange={(e) => setTyped(e.target.value)}
                        onKeyDown={(e) => {
                            if (e.key === 'Enter') {
                                e.preventDefault();
                                submit();
                            }
                        }}
                        placeholder={statementName}
                        className="mt-2 h-9 w-full rounded-lg border border-black/8 bg-white px-3 font-mono text-[13px] text-gray-800 outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-100"
                    />
                </div>

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
                            submit();
                        }}
                        disabled={!confirmed || processing}
                        className={`h-9 rounded-lg px-4 font-mono! text-[12px]! font-medium text-white transition-all disabled:opacity-50 ${
                            locking
                                ? 'bg-amber-600 hover:bg-amber-700'
                                : 'bg-red-600 hover:bg-red-700'
                        }`}
                    >
                        {processing
                            ? locking
                                ? 'Locking...'
                                : 'Unlocking...'
                            : locking
                              ? 'Lock statement'
                              : 'Unlock statement'}
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}
