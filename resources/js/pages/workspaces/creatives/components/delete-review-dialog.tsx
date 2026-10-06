import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { router } from '@inertiajs/react';
import { Loader2, X } from 'lucide-react';
import { useState } from 'react';
import { Review } from '../types';

/**
 * Confirms, then deletes a review. `url` is the review's own endpoint; the
 * page stays put (and a playing video keeps its place) afterwards.
 */
export function DeleteReviewDialog({
    review,
    url,
    onClose,
    onDeleted,
}: {
    /** The review to delete; null keeps the dialog closed. */
    review: Review | null;
    url: string;
    onClose: () => void;
    onDeleted?: () => void;
}) {
    const [deleting, setDeleting] = useState(false);

    const what = review
        ? [
              review.timestamp_seconds !== null && 'its timestamp',
              review.region && 'its marked area',
              review.voice && 'its voice message',
          ].filter(Boolean)
        : [];

    const destroy = () => {
        setDeleting(true);
        router.delete(url, {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => {
                onDeleted?.();
                onClose();
            },
            onFinish: () => setDeleting(false),
        });
    };

    return (
        <Dialog open={review !== null} onOpenChange={(v) => !v && onClose()}>
            <DialogContent className="gap-0 overflow-hidden border-none p-0 shadow-2xl sm:max-w-sm dark:bg-zinc-900 [&_[data-default-close=true]]:hidden">
                <div className="relative border-b border-black/6 px-5 pt-5 pb-4 dark:border-white/6">
                    <DialogHeader>
                        <DialogTitle className="text-[15px] font-semibold text-gray-900 dark:text-gray-100">
                            Delete Review
                        </DialogTitle>
                    </DialogHeader>
                    <DialogClose className="absolute top-4 right-4 inline-flex h-8 w-8 items-center justify-center rounded-md text-gray-400 transition-colors hover:bg-black/5 hover:text-gray-600 dark:text-gray-500 dark:hover:bg-white/6 dark:hover:text-gray-300">
                        <X className="h-4 w-4" />
                        <span className="sr-only">Close</span>
                    </DialogClose>
                </div>
                <div className="px-5 py-4">
                    <p className="font-mono text-[12px] text-gray-500 dark:text-gray-400">
                        Permanently deletes this review
                        {what.length > 0
                            ? `, along with ${what.join(', ')}`
                            : ''}
                        .
                    </p>
                    {review?.feedback && (
                        <p className="mt-2 line-clamp-3 rounded-lg bg-stone-50 px-3 py-2 text-[12px] text-gray-600 dark:bg-zinc-800 dark:text-gray-300">
                            {review.feedback}
                        </p>
                    )}
                </div>
                <div className="flex items-center justify-end gap-2 border-t border-black/6 bg-stone-50/50 px-5 py-3 dark:border-white/6 dark:bg-white/2">
                    <button
                        type="button"
                        onClick={onClose}
                        disabled={deleting}
                        className="flex h-9 items-center rounded-lg border border-black/8 bg-white px-4 font-mono! text-[12px]! font-medium text-gray-600 transition-all hover:bg-stone-100 disabled:opacity-50 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-300 dark:hover:bg-zinc-700"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        onClick={destroy}
                        disabled={deleting}
                        className="flex h-9 items-center gap-1.5 rounded-lg bg-red-600 px-4 font-mono! text-[12px]! font-medium text-white transition-all hover:bg-red-700 disabled:opacity-50"
                    >
                        {deleting && (
                            <Loader2 className="h-3.5 w-3.5 animate-spin" />
                        )}
                        Delete
                    </button>
                </div>
            </DialogContent>
        </Dialog>
    );
}
