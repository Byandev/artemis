import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';
import type { SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import { Pencil, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { useTaskComments } from '../hooks/use-task-comments';
import { initials } from '../lib/task-format';
import type { Comment } from '../types';

/**
 * Render a timestamp without locale or clock lookups.
 *
 * Artemis can render this page with SSR, so anything that differs between the
 * server and client render breaks hydration silently. An ISO string sliced to
 * the minute is identical on both sides; the machine-readable value goes on the
 * `<time>` element for anything that wants to reformat it.
 */
function PostedAt({ at, edited }: { at: string | null; edited: boolean }) {
    if (at === null) {
        return null;
    }

    return (
        <time
            dateTime={at}
            className="shrink-0 font-mono text-[0.625rem] tracking-[0.06em] text-muted-foreground"
        >
            {at.slice(0, 16).replace('T', ' ')} UTC
            {edited && <span className="ml-1.5 italic">edited</span>}
        </time>
    );
}

function CommentBody({ body }: { body: string }) {
    return (
        <p className="text-sm leading-relaxed break-words whitespace-pre-wrap">
            {body}
        </p>
    );
}

function CommentRow({
    comment,
    mine,
    onEdit,
    onDelete,
}: {
    comment: Comment;
    mine: boolean;
    onEdit: (body: string) => Promise<boolean>;
    onDelete: () => void;
}) {
    const [editing, setEditing] = useState(false);
    const [draft, setDraft] = useState(comment.body);
    const [busy, setBusy] = useState(false);

    async function save() {
        setBusy(true);

        const saved = await onEdit(draft);

        setBusy(false);

        if (saved) {
            setEditing(false);
        }
    }

    return (
        <article className="flex gap-3 border-b border-black/6 px-5 py-4 last:border-b-0 dark:border-white/6">
            <span
                aria-hidden
                className="mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-full bg-emerald-500/10 font-mono text-[0.625rem] text-emerald-700 dark:text-emerald-400"
            >
                {initials(comment.author?.name ?? '?')}
            </span>

            <div className="min-w-0 flex-1">
                <div className="mb-1 flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
                    <span className="text-sm font-medium">
                        {comment.author?.name ?? 'Unknown'}
                    </span>
                    <PostedAt at={comment.created_at} edited={comment.edited} />
                </div>

                {editing ? (
                    <div className="flex flex-col gap-2">
                        <textarea
                            value={draft}
                            disabled={busy}
                            aria-label="Edit comment"
                            rows={3}
                            onChange={(event) => setDraft(event.target.value)}
                            className="w-full rounded-lg border border-black/8 bg-white px-3 py-2 text-sm outline-none focus-visible:border-emerald-500 focus-visible:ring-4 focus-visible:ring-emerald-500/15 dark:border-white/8 dark:bg-zinc-900"
                        />
                        <div className="flex gap-2">
                            <Button
                                type="button"
                                size="sm"
                                disabled={busy}
                                onClick={() => void save()}
                            >
                                Save
                            </Button>
                            <Button
                                type="button"
                                size="sm"
                                variant="ghost"
                                disabled={busy}
                                onClick={() => {
                                    setDraft(comment.body);
                                    setEditing(false);
                                }}
                            >
                                Cancel
                            </Button>
                        </div>
                    </div>
                ) : (
                    <CommentBody body={comment.body} />
                )}
            </div>

            {mine && !editing && (
                <div className="flex shrink-0 gap-0.5">
                    <Button
                        type="button"
                        size="icon"
                        variant="ghost"
                        aria-label="Edit comment"
                        onClick={() => setEditing(true)}
                        className="size-7 text-muted-foreground hover:text-foreground"
                    >
                        <Pencil className="size-3.5" />
                    </Button>
                    <Button
                        type="button"
                        size="icon"
                        variant="ghost"
                        aria-label="Delete comment"
                        onClick={onDelete}
                        className="size-7 text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
                    >
                        <Trash2 className="size-3.5" />
                    </Button>
                </div>
            )}
        </article>
    );
}

/**
 * The discussion on one task: a flat list, oldest first, plus a box to add to
 * it. Editing and deleting are offered only on your own comments; the API
 * refuses anyone else's regardless of what the interface shows.
 */
export function TaskComments({
    taskId,
    readOnly = false,
    onError,
}: {
    taskId: number;
    /** Without Manage Tasks: comments can be read, not posted or changed. */
    readOnly?: boolean;
    onError: (cause: unknown) => void;
}) {
    const { auth } = usePage<SharedData>().props;
    const { comments, loading, posting, post, edit, remove } = useTaskComments(
        taskId,
        onError,
    );
    const [draft, setDraft] = useState('');

    async function submit() {
        if (draft.trim() === '') {
            return;
        }

        if (await post(draft)) {
            setDraft('');
        }
    }

    return (
        <section className="overflow-hidden rounded-xl border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
            <header className="flex items-center justify-between gap-3 border-b border-black/6 px-5 py-3.5 dark:border-white/6">
                <h2 className="font-mono text-[0.6875rem] tracking-[0.1em] uppercase">
                    Comments
                </h2>
                {!loading && comments.length > 0 && (
                    <span className="text-xs text-muted-foreground">
                        {comments.length}
                    </span>
                )}
            </header>

            {loading ? (
                <div className="flex flex-col gap-2 px-5 py-4">
                    <Skeleton className="h-4 w-40" />
                    <Skeleton className="h-4 w-full" />
                </div>
            ) : comments.length === 0 ? (
                <p className="px-5 py-4 text-sm text-muted-foreground">
                    No comments yet.
                </p>
            ) : (
                comments.map((comment) => (
                    <CommentRow
                        key={comment.id}
                        comment={comment}
                        mine={!readOnly && comment.user_id === auth.user.id}
                        onEdit={(body) => edit(comment, body)}
                        onDelete={() => void remove(comment)}
                    />
                ))
            )}

            {!readOnly && (
                <div className="flex flex-col gap-2 border-t border-black/6 px-5 py-4 dark:border-white/6">
                    <textarea
                        value={draft}
                        disabled={posting}
                        rows={3}
                        aria-label="Add a comment"
                        placeholder="Add a comment"
                        onChange={(event) => setDraft(event.target.value)}
                        className="w-full rounded-lg border border-black/8 bg-white px-3 py-2 text-sm outline-none focus-visible:border-emerald-500 focus-visible:ring-4 focus-visible:ring-emerald-500/15 dark:border-white/8 dark:bg-zinc-900"
                    />
                    <div className="flex justify-end">
                        <Button
                            type="button"
                            size="sm"
                            disabled={posting || draft.trim() === ''}
                            onClick={() => void submit()}
                            className={cn(posting && 'opacity-60')}
                        >
                            Comment
                        </Button>
                    </div>
                </div>
            )}
        </section>
    );
}
