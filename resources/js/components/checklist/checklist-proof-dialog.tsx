import {
    ChecklistProgressItem,
    formatFileSize,
    PROOF_ACCEPT,
    PROOF_MAX_MB,
} from '@/components/checklist/types';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import {
    FileText,
    LoaderCircle,
    ShieldCheck,
    Trash2,
    UploadCloud,
    X,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

const NOTE_MAX = 1000;

type ChecklistProofDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    item: ChecklistProgressItem | null;
    submitting: boolean;
    onSubmit: (file: File, note: string) => void;
};

export function ChecklistProofDialog({
    open,
    onOpenChange,
    item,
    submitting,
    onSubmit,
}: ChecklistProofDialogProps) {
    const inputRef = useRef<HTMLInputElement>(null);
    const [file, setFile] = useState<File | null>(null);
    const [preview, setPreview] = useState<string | null>(null);
    const [note, setNote] = useState('');
    const [error, setError] = useState<string | null>(null);
    const [dragging, setDragging] = useState(false);

    const isReplacing = Boolean(item?.proof);

    // A fresh dialog every time it is opened for an item: a file left over from
    // a previous item would silently be attached to the wrong one.
    useEffect(() => {
        if (open) {
            setFile(null);
            setNote(item?.note ?? '');
            setError(null);
            setDragging(false);
        }
    }, [open, item?.id, item?.note]);

    // Thumbnails are read straight off the picked File, so the object URL has
    // to be released when it is replaced or the dialog closes.
    useEffect(() => {
        if (!file || !file.type.startsWith('image/')) {
            setPreview(null);
            return;
        }

        const url = URL.createObjectURL(file);
        setPreview(url);

        return () => URL.revokeObjectURL(url);
    }, [file]);

    const pick = (next: File | null) => {
        setError(null);

        if (next && next.size > PROOF_MAX_MB * 1024 * 1024) {
            setFile(null);
            setError(
                `That file is ${formatFileSize(next.size)} — the limit is ${PROOF_MAX_MB} MB.`,
            );
            return;
        }

        setFile(next);
    };

    const clear = () => {
        setFile(null);
        setError(null);

        if (inputRef.current) {
            inputRef.current.value = '';
        }
    };

    const submit = () => {
        if (submitting) {
            return;
        }

        if (!file) {
            setError('Attach a proof of completion before marking this done.');
            return;
        }

        onSubmit(file, note.trim());
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-md gap-0 overflow-hidden rounded-xl border border-black/8 p-0 dark:border-white/8 [&_[data-default-close=true]]:hidden">
                <DialogHeader className="space-y-0 border-b border-black/6 px-5 py-3 text-left dark:border-white/8">
                    <div className="flex items-start justify-between gap-3">
                        <div className="flex min-w-0 items-start gap-2.5">
                            <span className="mt-0.5 inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-400">
                                <ShieldCheck className="h-4 w-4" />
                            </span>
                            <div className="min-w-0 space-y-1">
                                <DialogTitle className="font-mono text-[16px] leading-none tracking-wide text-gray-800 uppercase dark:text-gray-100">
                                    {isReplacing
                                        ? 'Replace Proof'
                                        : 'Proof of Completion'}
                                </DialogTitle>
                                <DialogDescription className="text-[11px] leading-tight text-gray-500 dark:text-gray-300">
                                    {isReplacing
                                        ? 'Upload a new file to supersede the one on record.'
                                        : 'Required before this item can be marked complete.'}
                                </DialogDescription>
                            </div>
                        </div>
                        <DialogClose className="z-10 mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-md text-gray-400 transition-colors hover:bg-black/5 hover:text-gray-600 dark:text-gray-500 dark:hover:bg-white/6 dark:hover:text-gray-300">
                            <X className="h-4 w-4" />
                            <span className="sr-only">Close</span>
                        </DialogClose>
                    </div>
                </DialogHeader>

                {item && (
                    <div className="border-b border-black/6 bg-stone-50 px-5 py-2.5 dark:border-white/8 dark:bg-zinc-800/40">
                        <p className="font-mono text-[10px] tracking-wider text-gray-400 uppercase dark:text-gray-500">
                            Checklist item
                        </p>
                        <p className="mt-0.5 font-mono text-[12px] font-medium break-words text-gray-800 dark:text-gray-100">
                            {item.title}
                        </p>
                    </div>
                )}

                <div className="space-y-3.5 px-5 py-3.5">
                    <div className="space-y-1.5">
                        <Label className="font-mono text-[10px] font-medium tracking-wider text-gray-400 uppercase dark:text-gray-500">
                            Proof <span className="text-red-500">*</span>
                        </Label>

                        <input
                            ref={inputRef}
                            type="file"
                            accept={PROOF_ACCEPT}
                            onChange={(e) => pick(e.target.files?.[0] ?? null)}
                            className="hidden"
                        />

                        {file ? (
                            <div className="flex items-center gap-3 rounded-lg border border-emerald-500/40 bg-emerald-50/60 p-2.5 dark:border-emerald-400/30 dark:bg-emerald-500/10">
                                {preview ? (
                                    <img
                                        src={preview}
                                        alt=""
                                        className="h-11 w-11 shrink-0 rounded-md border border-black/8 object-cover dark:border-white/10"
                                    />
                                ) : (
                                    <span className="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-md border border-black/8 bg-white text-gray-400 dark:border-white/10 dark:bg-zinc-900 dark:text-gray-500">
                                        <FileText className="h-5 w-5" />
                                    </span>
                                )}
                                <div className="min-w-0 flex-1">
                                    <p className="truncate font-mono text-[11px] font-medium text-gray-800 dark:text-gray-100">
                                        {file.name}
                                    </p>
                                    <p className="mt-0.5 font-mono text-[10px] tracking-wider text-emerald-700 uppercase dark:text-emerald-400">
                                        Ready · {formatFileSize(file.size)}
                                    </p>
                                </div>
                                <button
                                    type="button"
                                    onClick={clear}
                                    disabled={submitting}
                                    className="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-md text-gray-400 transition-colors hover:bg-black/5 hover:text-red-600 disabled:opacity-50 dark:text-gray-500 dark:hover:bg-white/6 dark:hover:text-red-400"
                                >
                                    <Trash2 className="h-3.5 w-3.5" />
                                    <span className="sr-only">Remove file</span>
                                </button>
                            </div>
                        ) : (
                            <button
                                type="button"
                                onClick={() => inputRef.current?.click()}
                                onDragOver={(e) => {
                                    e.preventDefault();
                                    setDragging(true);
                                }}
                                onDragLeave={() => setDragging(false)}
                                onDrop={(e) => {
                                    e.preventDefault();
                                    setDragging(false);
                                    pick(e.dataTransfer.files?.[0] ?? null);
                                }}
                                className={cn(
                                    'flex w-full flex-col items-center gap-1.5 rounded-lg border border-dashed px-3 py-6 transition-colors',
                                    dragging
                                        ? 'border-emerald-500 bg-emerald-50 dark:border-emerald-400 dark:bg-emerald-500/10'
                                        : 'border-black/15 bg-stone-100 hover:border-emerald-500 hover:bg-emerald-50/50 dark:border-white/15 dark:bg-zinc-800 dark:hover:border-emerald-400 dark:hover:bg-emerald-500/5',
                                )}
                            >
                                <UploadCloud className="h-5 w-5 text-gray-400 dark:text-gray-500" />
                                <span className="font-mono text-[12px] text-gray-600 dark:text-gray-300">
                                    Drop a file or{' '}
                                    <span className="text-emerald-700 underline underline-offset-2 dark:text-emerald-400">
                                        browse
                                    </span>
                                </span>
                                <span className="font-mono text-[10px] text-gray-400 dark:text-gray-500">
                                    JPG, PNG, WEBP, HEIC or PDF · max{' '}
                                    {PROOF_MAX_MB} MB
                                </span>
                            </button>
                        )}

                        {isReplacing && !error && (
                            <p className="font-mono text-[10px] text-gray-400 dark:text-gray-500">
                                Replaces {item?.proof?.file_name}.
                            </p>
                        )}

                        {error && (
                            <p className="font-mono text-[10px] text-red-500">
                                {error}
                            </p>
                        )}
                    </div>

                    <div className="space-y-1.5">
                        <div className="flex items-center justify-between">
                            <Label className="font-mono text-[10px] font-medium tracking-wider text-gray-400 uppercase dark:text-gray-500">
                                Note (optional)
                            </Label>
                            <span className="font-mono text-[10px] text-gray-400 dark:text-gray-500">
                                {note.length}/{NOTE_MAX}
                            </span>
                        </div>
                        <Textarea
                            value={note}
                            maxLength={NOTE_MAX}
                            rows={3}
                            onChange={(e) => setNote(e.target.value)}
                            placeholder="Anything a reviewer should know"
                            className="resize-none rounded-lg border-black/6 bg-stone-100 font-mono! text-[12px]! placeholder:text-gray-400 focus-visible:border-emerald-500 focus-visible:ring-2 focus-visible:ring-emerald-500/20 dark:border-white/8 dark:bg-zinc-800 dark:placeholder:text-gray-500 dark:focus-visible:border-emerald-400 dark:focus-visible:ring-emerald-400/20"
                        />
                    </div>
                </div>

                <DialogFooter className="flex-row items-center gap-2 border-t border-black/6 bg-stone-50 px-5 py-3 dark:border-white/8 dark:bg-zinc-800/40">
                    <button
                        type="button"
                        className="flex h-8 flex-1 items-center justify-center rounded-lg border border-black/8 bg-white px-4 font-mono! text-[12px]! font-medium text-gray-600 transition-all hover:bg-stone-100 disabled:opacity-50 dark:border-white/8 dark:bg-zinc-900 dark:text-gray-300 dark:hover:bg-zinc-800"
                        onClick={() => onOpenChange(false)}
                        disabled={submitting}
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        className="flex h-8 flex-1 items-center justify-center gap-2 rounded-lg bg-emerald-600 px-4 font-mono! text-[12px]! font-medium text-white transition-all hover:bg-emerald-700 disabled:opacity-50"
                        onClick={submit}
                        disabled={submitting || !file}
                    >
                        {submitting && (
                            <LoaderCircle className="h-3.5 w-3.5 animate-spin" />
                        )}
                        {submitting
                            ? 'Uploading…'
                            : isReplacing
                              ? 'Replace proof'
                              : 'Mark complete'}
                    </button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
