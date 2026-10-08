import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';
import type { SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import {
    Download,
    FileArchive,
    File as FileIcon,
    FileSpreadsheet,
    FileText,
    Image as ImageIcon,
    Paperclip,
    Trash2,
    Upload,
} from 'lucide-react';
import type { DragEvent } from 'react';
import { useRef, useState } from 'react';
import { useTaskAttachments } from '../hooks/use-task-attachments';
import { formatBytes } from '../lib/task-format';
import type { Attachment } from '../types';

/** Pick a glyph from the stored mime type, so the list scans by shape. */
function GlyphFor({ mime }: { mime: string | null }) {
    const type = mime ?? '';

    if (type.startsWith('image/')) {
        return <ImageIcon className="size-4" />;
    }

    if (type === 'application/pdf' || type.startsWith('text/')) {
        return <FileText className="size-4" />;
    }

    if (type.includes('spreadsheet') || type === 'text/csv') {
        return <FileSpreadsheet className="size-4" />;
    }

    if (type.includes('zip') || type.includes('compressed')) {
        return <FileArchive className="size-4" />;
    }

    return <FileIcon className="size-4" />;
}

/**
 * One file in the list: the name opens it, the button saves it.
 *
 * The name is a plain link to the preview route in a new tab, so an image or a
 * PDF is just there to look at. It is safe to open for any file -- the route
 * hands over anything it will not render -- so the link needs no condition on
 * the type.
 *
 * `rel` is `noopener`: the routes are same origin and session authenticated,
 * so `noopener` alone is all the isolation this needs.
 *
 * The save button carries `download`, which makes the click a download rather
 * than a navigation. Without it a browser may open a tab to hold the response
 * before deciding to save it, leaving a blank tab behind.
 */
function AttachmentRow({
    attachment,
    removable,
    onRemove,
}: {
    attachment: Attachment;
    removable: boolean;
    onRemove: () => void;
}) {
    return (
        <div className="flex items-center gap-3 border-b border-black/5 px-5 py-3 last:border-b-0 dark:border-white/5">
            <span
                aria-hidden
                className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-700 dark:text-emerald-400"
            >
                <GlyphFor mime={attachment.mime_type} />
            </span>

            <div className="min-w-0 flex-1">
                <a
                    href={attachment.preview_url}
                    target="_blank"
                    rel="noopener"
                    title={
                        attachment.previewable
                            ? `Open ${attachment.file_name} in a new tab`
                            : `${attachment.file_name} cannot be shown in the browser, so it will be saved`
                    }
                    className="block truncate text-sm font-medium transition-colors duration-200 ease-out hover:text-emerald-700 dark:hover:text-emerald-400"
                >
                    {attachment.file_name}
                </a>
                <p className="truncate text-[0.6875rem] text-muted-foreground">
                    <span>{formatBytes(attachment.size)}</span>
                    {attachment.uploader && (
                        <> &middot; {attachment.uploader.name}</>
                    )}
                </p>
            </div>

            <div className="flex shrink-0 gap-0.5">
                <Button
                    asChild
                    size="icon"
                    variant="ghost"
                    className="size-7 text-muted-foreground hover:text-foreground"
                >
                    <a
                        href={attachment.download_url}
                        download={attachment.file_name}
                        aria-label={`Download ${attachment.file_name}`}
                    >
                        <Download className="size-3.5" />
                    </a>
                </Button>

                {removable && (
                    <Button
                        type="button"
                        size="icon"
                        variant="ghost"
                        aria-label={`Delete ${attachment.file_name}`}
                        onClick={onRemove}
                        className="size-7 text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
                    >
                        <Trash2 className="size-3.5" />
                    </Button>
                )}
            </div>
        </div>
    );
}

/**
 * The files stored against one task. Uploading is offered to members and above
 * and deleting to the uploader or a space admin; the API refuses anyone else
 * regardless of what the interface shows.
 */
export function TaskAttachments({
    taskId,
    canAttach,
    canManage,
    onError,
}: {
    taskId: number;
    canAttach: boolean;
    canManage: boolean;
    onError: (cause: unknown) => void;
}) {
    const { auth } = usePage<SharedData>().props;
    const { attachments, loading, uploading, upload, remove } =
        useTaskAttachments(taskId, onError);
    const input = useRef<HTMLInputElement>(null);
    const [dragging, setDragging] = useState(false);

    function send(files: FileList | null) {
        if (files !== null && files.length > 0) {
            void upload(Array.from(files));
        }
    }

    function onDrop(event: DragEvent<HTMLDivElement>) {
        event.preventDefault();
        setDragging(false);

        if (canAttach && !uploading) {
            send(event.dataTransfer.files);
        }
    }

    return (
        <section className="overflow-hidden rounded-xl border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
            <header className="flex items-center justify-between gap-3 border-b border-black/6 px-5 py-3.5 dark:border-white/6">
                <h2 className="flex items-center gap-2 font-mono text-[0.6875rem] tracking-[0.1em] uppercase">
                    <Paperclip className="size-3.5 text-muted-foreground" />
                    Attachments
                </h2>
                {!loading && attachments.length > 0 && (
                    <span className="text-xs text-muted-foreground">
                        {attachments.length}
                    </span>
                )}
            </header>

            {loading ? (
                <div className="flex flex-col gap-2 px-5 py-4">
                    <Skeleton className="h-4 w-48" />
                    <Skeleton className="h-4 w-32" />
                </div>
            ) : attachments.length === 0 ? (
                <p className="px-5 py-4 text-sm text-muted-foreground">
                    No files attached yet.
                </p>
            ) : (
                attachments.map((attachment) => (
                    <AttachmentRow
                        key={attachment.id}
                        attachment={attachment}
                        removable={
                            canManage || attachment.uploaded_by === auth.user.id
                        }
                        onRemove={() => void remove(attachment)}
                    />
                ))
            )}

            {canAttach && (
                <div
                    onDragOver={(event) => {
                        event.preventDefault();
                        setDragging(true);
                    }}
                    onDragLeave={() => setDragging(false)}
                    onDrop={onDrop}
                    className={cn(
                        'flex flex-col items-center gap-2 border-t border-black/6 px-5 py-5 transition-colors duration-200 ease-out dark:border-white/6',
                        dragging && 'bg-emerald-500/5',
                    )}
                >
                    <input
                        ref={input}
                        type="file"
                        multiple
                        aria-label="Attach files"
                        className="hidden"
                        onChange={(event) => {
                            send(event.target.files);
                            event.target.value = '';
                        }}
                    />

                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        disabled={uploading}
                        onClick={() => input.current?.click()}
                    >
                        <Upload />
                        {uploading ? 'Uploading…' : 'Attach a file'}
                    </Button>

                    <p className="text-center text-[0.6875rem] text-muted-foreground">
                        or drop files here &middot; up to 25 MB each
                    </p>
                </div>
            )}
        </section>
    );
}
