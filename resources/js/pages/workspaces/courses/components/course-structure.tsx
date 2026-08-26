import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Link, router } from '@inertiajs/react';
import { Check, MoreHorizontal, Plus, Trash2, Upload } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { uploadLessonVideo } from '../lib/upload-video';
import { type CourseModule } from '../types';

/** Seconds as m:ss, or h:mm:ss once past an hour. */
function clock(seconds: number): string {
    const sec = Math.floor(seconds % 60);
    const min = Math.floor((seconds / 60) % 60);
    const hr = Math.floor(seconds / 3600);
    const mm = hr > 0 ? String(min).padStart(2, '0') : String(min);

    return `${hr > 0 ? `${hr}:` : ''}${mm}:${String(sec).padStart(2, '0')}`;
}

interface Props {
    baseUrl: string;
    courseId: number;
    modules: CourseModule[];
    canEdit: boolean;
    /** Lessons the current user has finished. */
    completedLessonIds: number[];
}

/**
 * A name that turns into an input when clicked. Enter or blur saves, Escape
 * reverts. An empty or unchanged value is not submitted, so clicking away from
 * a row you didn't mean to touch is a no-op rather than a validation error.
 */
function EditableName({
    value,
    action,
    editing,
    onEditingChange,
    canEdit,
    className,
}: {
    value: string;
    action: string;
    editing: boolean;
    onEditingChange: (editing: boolean) => void;
    canEdit: boolean;
    className: string;
}) {
    const [draft, setDraft] = useState(value);
    const inputRef = useRef<HTMLInputElement>(null);
    // Guards against blur firing a second save right after Enter or Escape.
    const settled = useRef(false);

    useEffect(() => {
        if (!editing) return;
        settled.current = false;
        setDraft(value);
        // Select the whole name so a freshly added row can be typed over.
        requestAnimationFrame(() => inputRef.current?.select());
    }, [editing, value]);

    function commit() {
        if (settled.current) return;
        settled.current = true;

        const name = draft.trim();
        onEditingChange(false);

        if (!name || name === value) return;

        router.put(action, { name }, { preserveScroll: true });
    }

    function cancel() {
        settled.current = true;
        setDraft(value);
        onEditingChange(false);
    }

    if (!editing) {
        return (
            <span
                onClick={() => canEdit && onEditingChange(true)}
                className={`${className} ${canEdit ? '-mx-1 cursor-text rounded px-1 hover:bg-black/4 dark:hover:bg-white/6' : ''}`}
            >
                {value}
            </span>
        );
    }

    return (
        <input
            ref={inputRef}
            value={draft}
            onChange={(e) => setDraft(e.target.value)}
            onBlur={commit}
            onKeyDown={(e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    commit();
                }
                if (e.key === 'Escape') {
                    e.preventDefault();
                    cancel();
                }
            }}
            className={`${className} -mx-1 min-w-0 flex-1 rounded border border-emerald-500 bg-white px-1 ring-2 ring-emerald-500/15 outline-none dark:bg-zinc-900`}
        />
    );
}

export default function CourseStructure({
    baseUrl,
    courseId,
    modules,
    canEdit,
    completedLessonIds,
}: Props) {
    const courseUrl = `${baseUrl}/${courseId}`;
    const done = new Set(completedLessonIds);

    const [editingModule, setEditingModule] = useState<number | null>(null);
    const [editingLesson, setEditingLesson] = useState<number | null>(null);
    const [busy, setBusy] = useState(false);
    // Per-lesson upload progress, so one row uploading doesn't block the rest.
    const [uploading, setUploading] = useState<Record<number, number>>({});
    // One hidden input per lesson would be wasteful; a single one is retargeted
    // at whichever row asked for it.
    const videoInputRef = useRef<HTMLInputElement>(null);
    const uploadTarget = useRef<string | null>(null);
    const [uploadError, setUploadError] = useState<string | null>(null);

    // A row is created first and named in place, so once the new id arrives it
    // has to be put straight into edit mode. These track what to look for.
    const awaitingModule = useRef(false);
    const awaitingLesson = useRef<number | null>(null);
    const knownModules = useRef<Set<number>>(new Set());
    const knownLessons = useRef<Set<number>>(new Set());

    useEffect(() => {
        const moduleIds = new Set(modules.map((m) => m.id));
        const lessonIds = new Set(
            modules.flatMap((m) => m.lessons.map((l) => l.id)),
        );

        if (awaitingModule.current) {
            const fresh = [...moduleIds].find(
                (id) => !knownModules.current.has(id),
            );
            if (fresh !== undefined) {
                awaitingModule.current = false;
                setEditingModule(fresh);
            }
        }

        if (awaitingLesson.current !== null) {
            const parent = modules.find((m) => m.id === awaitingLesson.current);
            const fresh = parent?.lessons.find(
                (l) => !knownLessons.current.has(l.id),
            );
            if (fresh) {
                awaitingLesson.current = null;
                setEditingLesson(fresh.id);
            }
        }

        knownModules.current = moduleIds;
        knownLessons.current = lessonIds;
    }, [modules]);

    function addModule() {
        awaitingModule.current = true;
        setBusy(true);
        router.post(
            `${courseUrl}/modules`,
            {},
            {
                preserveScroll: true,
                onFinish: () => setBusy(false),
                onError: () => {
                    awaitingModule.current = false;
                },
            },
        );
    }

    function addLesson(moduleId: number) {
        awaitingLesson.current = moduleId;
        setBusy(true);
        router.post(
            `${courseUrl}/modules/${moduleId}/lessons`,
            {},
            {
                preserveScroll: true,
                onFinish: () => setBusy(false),
                onError: () => {
                    awaitingLesson.current = null;
                },
            },
        );
    }

    function pickVideo(lessonId: number, videoUrl: string) {
        uploadTarget.current = videoUrl;
        // Reset first so re-picking the same file still fires onChange.
        if (videoInputRef.current) {
            videoInputRef.current.value = '';
            videoInputRef.current.dataset.lessonId = String(lessonId);
            videoInputRef.current.click();
        }
    }

    function uploadVideo(file: File) {
        const url = uploadTarget.current;
        const lessonId = Number(videoInputRef.current?.dataset.lessonId);
        if (!url || !lessonId) return;

        setUploadError(null);

        uploadLessonVideo({
            videoUrl: url,
            file,
            onProgress: (percentage) =>
                setUploading((prev) => ({ ...prev, [lessonId]: percentage })),
            onDone: () =>
                setUploading((prev) => {
                    const next = { ...prev };
                    delete next[lessonId];
                    return next;
                }),
            onError: setUploadError,
        });
    }

    /**
     * Called on the router rather than pulled off it: these are methods on a
     * class instance, and delete() takes (url, options) where post() takes
     * (url, data, options).
     */
    function setComplete(url: string, complete: boolean) {
        if (complete) {
            router.post(url, {}, { preserveScroll: true });
        } else {
            router.delete(url, { preserveScroll: true });
        }
    }

    function destroy(url: string, message: string) {
        if (!confirm(message)) return;
        router.delete(url, { preserveScroll: true });
    }

    return (
        <section className="space-y-3">
            {uploadError && (
                <p className="rounded-lg bg-red-50 px-3 py-2 font-mono text-[11px] text-red-600 dark:bg-red-950/40 dark:text-red-400">
                    {uploadError}
                </p>
            )}

            {modules.length === 0 && !canEdit && (
                <div className="rounded-xl border border-dashed border-black/12 bg-stone-50 py-10 text-center dark:border-white/12 dark:bg-zinc-900">
                    <p className="font-mono text-[12px] text-gray-500 dark:text-gray-400">
                        No lessons yet
                    </p>
                </div>
            )}

            <div className="space-y-3.5">
                {modules.map((module) => {
                    const moduleUrl = `${courseUrl}/modules/${module.id}`;
                    const doneHere = module.lessons.filter((l) =>
                        done.has(l.id),
                    ).length;

                    return (
                        <div
                            key={module.id}
                            className="overflow-hidden rounded-xl border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900"
                        >
                            <div className="flex items-center gap-2 border-b border-black/6 px-4 py-3 dark:border-white/6">
                                <EditableName
                                    value={module.name}
                                    action={moduleUrl}
                                    editing={editingModule === module.id}
                                    onEditingChange={(on) =>
                                        setEditingModule(on ? module.id : null)
                                    }
                                    canEdit={canEdit}
                                    className="min-w-0 flex-1 truncate font-mono text-[10px] font-medium tracking-wider text-gray-500 uppercase dark:text-gray-400"
                                />

                                <span
                                    title={`${doneHere} of ${module.lessons.length} lessons completed`}
                                    className="shrink-0 font-mono text-[11px] text-gray-400 tabular-nums dark:text-gray-500"
                                >
                                    {doneHere}/{module.lessons.length}
                                </span>

                                {canEdit && (
                                    <DropdownMenu>
                                        <DropdownMenuTrigger asChild>
                                            <button
                                                aria-label="Module actions"
                                                className="flex h-6 w-6 shrink-0 items-center justify-center rounded-md text-gray-400 transition-all hover:bg-stone-100 hover:text-gray-700 dark:hover:bg-zinc-800"
                                            >
                                                <MoreHorizontal className="h-4 w-4" />
                                            </button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent align="end">
                                            <DropdownMenuItem
                                                variant="destructive"
                                                onSelect={() =>
                                                    destroy(
                                                        moduleUrl,
                                                        `Delete "${module.name}"? Its lessons are removed too.`,
                                                    )
                                                }
                                            >
                                                <Trash2 className="mr-2 h-4 w-4" />
                                                Delete module
                                            </DropdownMenuItem>
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                )}
                            </div>

                            <div className="divide-y divide-black/4 dark:divide-white/4">
                                {module.lessons.map((lesson) => {
                                    const lessonUrl = `${moduleUrl}/lessons/${lesson.id}`;
                                    const isDone = done.has(lesson.id);

                                    return (
                                        <div
                                            key={lesson.id}
                                            className="group flex items-center gap-3 px-4 py-2.5"
                                        >
                                            <button
                                                onClick={() =>
                                                    setComplete(
                                                        `${lessonUrl}/complete`,
                                                        !isDone,
                                                    )
                                                }
                                                aria-label={
                                                    isDone
                                                        ? 'Mark lesson not completed'
                                                        : 'Mark lesson completed'
                                                }
                                                aria-pressed={isDone}
                                                className={`flex h-5 w-5 shrink-0 items-center justify-center rounded-full border transition-all ${
                                                    isDone
                                                        ? 'border-emerald-600 bg-emerald-600 text-white'
                                                        : 'border-black/20 hover:border-emerald-500 dark:border-white/25'
                                                }`}
                                            >
                                                {isDone && (
                                                    <Check className="h-3 w-3" />
                                                )}
                                            </button>

                                            <EditableName
                                                value={lesson.name}
                                                action={lessonUrl}
                                                editing={
                                                    editingLesson === lesson.id
                                                }
                                                onEditingChange={(on) =>
                                                    setEditingLesson(
                                                        on ? lesson.id : null,
                                                    )
                                                }
                                                canEdit={canEdit}
                                                className={`min-w-0 flex-1 truncate text-[13px] ${
                                                    isDone
                                                        ? 'text-gray-400 dark:text-gray-500'
                                                        : 'text-gray-700 dark:text-gray-200'
                                                }`}
                                            />

                                            <span className="shrink-0 font-mono text-[11px] text-gray-400 tabular-nums dark:text-gray-500">
                                                {lesson.duration_seconds
                                                    ? clock(
                                                          lesson.duration_seconds,
                                                      )
                                                    : '—'}
                                            </span>

                                            {uploading[lesson.id] !==
                                            undefined ? (
                                                <div className="flex w-24 shrink-0 items-center gap-2">
                                                    <div className="h-1 flex-1 overflow-hidden rounded-full bg-stone-200 dark:bg-zinc-700">
                                                        <div
                                                            className="h-full rounded-full bg-emerald-600 transition-all"
                                                            style={{
                                                                width: `${uploading[lesson.id]}%`,
                                                            }}
                                                        />
                                                    </div>
                                                    <span className="font-mono text-[10px] text-gray-400 tabular-nums">
                                                        {uploading[lesson.id]}%
                                                    </span>
                                                </div>
                                            ) : (
                                                <>
                                                    {lesson.video ? (
                                                        <Link
                                                            href={`${courseUrl}/preview?lesson=${lesson.id}`}
                                                            className="flex h-7 shrink-0 items-center rounded-md border border-emerald-600/20 bg-emerald-50 px-2.5 font-mono! text-[10px]! font-medium tracking-wider text-emerald-700 uppercase transition-all hover:bg-emerald-100 dark:border-emerald-400/20 dark:bg-emerald-950/40 dark:text-emerald-400"
                                                        >
                                                            {isDone
                                                                ? 'Replay'
                                                                : 'Play'}
                                                        </Link>
                                                    ) : (
                                                        <span className="flex h-7 shrink-0 items-center rounded-md border border-black/8 px-2.5 font-mono! text-[10px]! font-medium tracking-wider text-gray-300 uppercase dark:border-white/8 dark:text-gray-600">
                                                            Play
                                                        </span>
                                                    )}

                                                    {canEdit && (
                                                        <div className="flex shrink-0 items-center gap-1 opacity-0 transition-opacity group-hover:opacity-100 focus-within:opacity-100">
                                                            <button
                                                                aria-label="Upload lesson video"
                                                                title={
                                                                    lesson.video
                                                                        ? 'Replace video'
                                                                        : 'Upload video'
                                                                }
                                                                onClick={() =>
                                                                    pickVideo(
                                                                        lesson.id,
                                                                        `${lessonUrl}/video`,
                                                                    )
                                                                }
                                                                className="flex h-7 w-7 items-center justify-center rounded-md text-gray-400 transition-all hover:bg-stone-100 hover:text-gray-700 dark:hover:bg-zinc-800"
                                                            >
                                                                <Upload className="h-3.5 w-3.5" />
                                                            </button>
                                                            <button
                                                                aria-label="Delete lesson"
                                                                onClick={() =>
                                                                    destroy(
                                                                        lessonUrl,
                                                                        `Delete lesson "${lesson.name}"?`,
                                                                    )
                                                                }
                                                                className="flex h-7 w-7 items-center justify-center rounded-md text-gray-400 transition-all hover:bg-stone-100 hover:text-red-600 dark:hover:bg-zinc-800"
                                                            >
                                                                <Trash2 className="h-3.5 w-3.5" />
                                                            </button>
                                                        </div>
                                                    )}
                                                </>
                                            )}
                                        </div>
                                    );
                                })}

                                {canEdit && (
                                    <button
                                        onClick={() => addLesson(module.id)}
                                        disabled={busy}
                                        className="flex w-full items-center gap-1.5 px-4 py-2.5 font-mono text-[12px] text-gray-400 transition-colors hover:text-emerald-600 disabled:opacity-50 dark:hover:text-emerald-400"
                                    >
                                        <Plus className="h-3.5 w-3.5" />
                                        New Lesson
                                    </button>
                                )}
                            </div>
                        </div>
                    );
                })}

                {canEdit && (
                    <button
                        onClick={addModule}
                        disabled={busy}
                        className="flex w-full items-center gap-1.5 rounded-xl border border-dashed border-black/12 px-4 py-3 font-mono text-[12px] text-gray-400 transition-all hover:border-emerald-500/40 hover:text-emerald-600 disabled:opacity-50 dark:border-white/12 dark:hover:border-emerald-400/40 dark:hover:text-emerald-400"
                    >
                        <Plus className="h-4 w-4" />
                        New Module
                    </button>
                )}
            </div>

            <input
                ref={videoInputRef}
                type="file"
                accept="video/*"
                className="hidden"
                onChange={(e) => {
                    const file = e.target.files?.[0];
                    if (file) uploadVideo(file);
                }}
            />
        </section>
    );
}
