import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

/** Asked when leaving the creative form with an upload that isn't saved yet. */
export function DiscardUploadDialog({
    open,
    fileName,
    uploading,
    onStay,
    onDiscard,
}: {
    open: boolean;
    fileName: string | null;
    /** Still uploading, rather than uploaded-but-unsaved. */
    uploading: boolean;
    onStay: () => void;
    onDiscard: () => void;
}) {
    return (
        <Dialog open={open} onOpenChange={(v) => !v && onStay()}>
            <DialogContent className="gap-0 overflow-hidden border-none p-0 shadow-2xl sm:max-w-sm dark:bg-zinc-900 [&_[data-default-close=true]]:hidden">
                <div className="border-b border-black/6 px-5 pt-5 pb-4 dark:border-white/6">
                    <DialogHeader>
                        <DialogTitle className="text-[15px] font-semibold text-gray-900 dark:text-gray-100">
                            Discard upload?
                        </DialogTitle>
                    </DialogHeader>
                </div>
                <div className="space-y-2 px-5 py-4">
                    <p className="font-mono text-[12px] text-gray-500 dark:text-gray-400">
                        {uploading
                            ? 'A file is still uploading and this creative hasn’t been saved.'
                            : 'You uploaded a file but haven’t saved this creative.'}{' '}
                        Leaving now deletes the upload.
                    </p>
                    {fileName && (
                        <p className="truncate rounded-lg bg-stone-50 px-3 py-2 font-mono text-[12px] text-gray-600 dark:bg-zinc-800 dark:text-gray-300">
                            {fileName}
                        </p>
                    )}
                </div>
                <div className="flex items-center justify-end gap-2 border-t border-black/6 bg-stone-50/50 px-5 py-3 dark:border-white/6 dark:bg-white/2">
                    <button
                        type="button"
                        onClick={onStay}
                        className="flex h-9 items-center rounded-lg border border-black/8 bg-white px-4 font-mono! text-[12px]! font-medium text-gray-600 transition-all hover:bg-stone-100 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-300 dark:hover:bg-zinc-700"
                    >
                        Stay
                    </button>
                    <button
                        type="button"
                        onClick={onDiscard}
                        className="flex h-9 items-center rounded-lg bg-red-600 px-4 font-mono! text-[12px]! font-medium text-white transition-all hover:bg-red-700"
                    >
                        Discard &amp; leave
                    </button>
                </div>
            </DialogContent>
        </Dialog>
    );
}
