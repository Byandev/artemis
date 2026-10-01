import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    Check,
    CheckCircle2,
    ChevronLeft,
    ChevronRight,
    Clock,
    Play,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { clock, duration } from './lib/format';
import {
    BAR,
    BTN_OUTLINE,
    BTN_PRIMARY,
    BTN_SECONDARY,
    CARD,
    CHIP_ACCENT,
    CHIP_NEUTRAL,
    LABEL,
    MUTED,
    NUM,
    PAGE,
    SECTION_BORDER,
    TITLE,
    TRACK,
} from './lib/ui';
import {
    type CourseLesson,
    type CourseProgress,
    type Course as CourseType,
    type CourseWorkspace,
} from './types';

interface Props {
    workspace: CourseWorkspace;
    course: CourseType;
    /** Lesson to open on, from ?lesson= — null when absent or unrecognised. */
    initialLessonId: number | null;
    progress: CourseProgress;
}

/** A lesson flattened with its module, so the outline reads as one sequence. */
interface FlatLesson {
    lesson: CourseLesson;
    moduleName: string;
    videoUrl: string;
    /** Endpoint for marking this lesson done or undone. */
    completeUrl: string;
    index: number;
}

export default function CoursePreview({
    workspace,
    course,
    initialLessonId,
    progress,
}: Props) {
    const baseUrl = `/workspaces/${workspace.slug}/courses`;
    const courseUrl = `${baseUrl}/${course.id}`;

    const modules = useMemo(() => course.modules ?? [], [course.modules]);

    const flat = useMemo<FlatLesson[]>(() => {
        let index = 0;
        return modules.flatMap((module) =>
            module.lessons.map((lesson) => ({
                lesson,
                moduleName: module.name,
                videoUrl: `${courseUrl}/modules/${module.id}/lessons/${lesson.id}/video`,
                completeUrl: `${courseUrl}/modules/${module.id}/lessons/${lesson.id}/complete`,
                index: index++,
            })),
        );
    }, [modules, courseUrl]);

    const [activeId, setActiveId] = useState<number | null>(
        () =>
            initialLessonId ??
            flat.find((f) => f.lesson.video)?.lesson.id ??
            flat[0]?.lesson.id ??
            null,
    );

    const active = flat.find((f) => f.lesson.id === activeId) ?? null;
    const prev = active ? flat[active.index - 1] : undefined;
    const next = active ? flat[active.index + 1] : undefined;

    const done = new Set(progress.completed_lesson_ids);
    const activeDone = active ? done.has(active.lesson.id) : false;

    function setComplete(entry: FlatLesson, complete: boolean) {
        // Called on the router rather than pulled off it: these are methods on
        // a class instance, so detaching one loses its `this`. Note also that
        // delete() takes (url, options) while post() takes (url, data,
        // options) — passing post's shape to delete drops the options.
        if (complete) {
            router.post(entry.completeUrl, {}, { preserveScroll: true });
        } else {
            router.delete(entry.completeUrl, { preserveScroll: true });
        }
    }

    const totalSeconds = flat.reduce(
        (sum, f) => sum + (f.lesson.duration_seconds ?? 0),
        0,
    );

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Courses', href: baseUrl },
        { title: course.name, href: courseUrl },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={course.name} />

            <div className={PAGE}>
                {/* Header */}
                <div className="mb-5 flex items-start justify-between gap-4">
                    <div className="min-w-0">
                        <div className="flex items-center gap-2">
                            {/* Back to the course that was opened, not the
                                whole catalogue — the player is one level in. */}
                            <Link
                                href={courseUrl}
                                className="flex min-w-0 items-center gap-1 font-mono text-[10px] font-medium tracking-wider text-emerald-600 uppercase transition-colors hover:text-emerald-700 dark:text-emerald-400"
                            >
                                <ArrowLeft className="h-3 w-3 shrink-0" />
                                <span className="truncate">{course.name}</span>
                            </Link>
                            {active && (
                                <span className={`${LABEL} truncate`}>
                                    {active.moduleName}
                                </span>
                            )}
                        </div>

                        <h1 className={`mt-1 truncate ${TITLE}`}>
                            {course.name}
                        </h1>

                        <p className={`mt-0.5 ${MUTED}`}>
                            {modules.length}{' '}
                            {modules.length === 1 ? 'module' : 'modules'} ·{' '}
                            {flat.length}{' '}
                            {flat.length === 1 ? 'lesson' : 'lessons'}
                            {totalSeconds > 0
                                ? ` · ${duration(totalSeconds)}`
                                : ''}
                        </p>
                    </div>

                    <div className="flex shrink-0 items-center gap-3">
                        {progress.total_lessons > 0 && (
                            <div className="hidden w-40 sm:block">
                                <div className={TRACK}>
                                    <div
                                        className={BAR}
                                        style={{
                                            width: `${progress.percent}%`,
                                        }}
                                    />
                                </div>
                                <p className={`mt-1 text-right ${NUM}`}>
                                    {progress.completed_count}/
                                    {progress.total_lessons} ·{' '}
                                    {progress.percent}%
                                </p>
                            </div>
                        )}
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-5 lg:grid-cols-3">
                    {/* Player + lesson detail */}
                    <div className="space-y-5 lg:col-span-2">
                        <div className="overflow-hidden rounded-xl bg-zinc-900 ring-1 ring-black/6 dark:ring-white/6">
                            {active?.lesson.video ? (
                                <video
                                    // Keyed so switching lessons loads the new
                                    // source instead of keeping the old buffer.
                                    key={active.lesson.id}
                                    src={active.videoUrl}
                                    controls
                                    autoPlay
                                    // Watching a lesson to the end is the
                                    // clearest signal it's finished, so it
                                    // counts without the learner doing anything.
                                    onEnded={() => {
                                        if (!activeDone) {
                                            setComplete(active, true);
                                        }
                                    }}
                                    className="aspect-video w-full"
                                />
                            ) : (
                                <div className="flex aspect-video w-full flex-col items-center justify-center gap-4">
                                    <div className="flex h-12 w-12 items-center justify-center rounded-full bg-white/10">
                                        <Play className="h-5 w-5 translate-x-0.5 text-white/70" />
                                    </div>
                                    <p className="font-mono text-[10px] tracking-wider text-white/40 uppercase">
                                        No content uploaded yet
                                    </p>
                                </div>
                            )}
                        </div>

                        {active && (
                            <div className={CARD}>
                                <div className="p-5">
                                    <div className="flex items-center gap-2">
                                        <span className={CHIP_NEUTRAL}>
                                            {active.moduleName}
                                        </span>
                                        {activeDone && (
                                            <span
                                                className={`${CHIP_ACCENT} flex items-center gap-1`}
                                            >
                                                <Check className="h-3 w-3" />
                                                Completed
                                            </span>
                                        )}
                                    </div>

                                    <h2 className="mt-2 text-[17px] font-semibold text-gray-800 dark:text-gray-100">
                                        {active.lesson.name}
                                    </h2>

                                    <div
                                        className={`mt-1.5 flex items-center gap-3 ${NUM}`}
                                    >
                                        {active.lesson.duration_seconds && (
                                            <span className="flex items-center gap-1">
                                                <Clock className="h-3 w-3" />
                                                {clock(
                                                    active.lesson
                                                        .duration_seconds,
                                                )}
                                            </span>
                                        )}
                                        <span>
                                            Lesson {active.index + 1} of{' '}
                                            {flat.length}
                                        </span>
                                    </div>
                                </div>

                                <div
                                    className={`flex flex-wrap items-center gap-2 border-t ${SECTION_BORDER} px-5 py-3`}
                                >
                                    <button
                                        onClick={() =>
                                            setComplete(active, !activeDone)
                                        }
                                        className={
                                            activeDone
                                                ? `${BTN_OUTLINE} border-emerald-600/20 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 dark:border-emerald-400/20 dark:bg-emerald-950/40 dark:text-emerald-400`
                                                : BTN_PRIMARY
                                        }
                                    >
                                        <Check className="h-4 w-4" />
                                        {activeDone
                                            ? 'Completed'
                                            : 'Mark complete'}
                                    </button>

                                    <div className="ml-auto flex items-center gap-2">
                                        <button
                                            onClick={() =>
                                                prev &&
                                                setActiveId(prev.lesson.id)
                                            }
                                            disabled={!prev}
                                            className={`${BTN_OUTLINE} px-3`}
                                        >
                                            <ChevronLeft className="h-4 w-4" />
                                            Previous
                                        </button>
                                        <button
                                            onClick={() =>
                                                next &&
                                                setActiveId(next.lesson.id)
                                            }
                                            disabled={!next}
                                            className={`${BTN_SECONDARY} px-3`}
                                        >
                                            Next
                                            <ChevronRight className="h-4 w-4" />
                                        </button>
                                    </div>
                                </div>

                                {next && (
                                    <button
                                        onClick={() =>
                                            setActiveId(next.lesson.id)
                                        }
                                        className={`flex w-full items-center gap-2 border-t ${SECTION_BORDER} px-5 py-2.5 text-left transition-all hover:bg-stone-50 dark:hover:bg-zinc-800/50`}
                                    >
                                        <span className={LABEL}>Up next</span>
                                        <span className="min-w-0 flex-1 truncate text-[13px] text-gray-600 dark:text-gray-300">
                                            {next.lesson.name}
                                        </span>
                                        <ChevronRight className="h-4 w-4 shrink-0 text-gray-300 dark:text-gray-600" />
                                    </button>
                                )}
                            </div>
                        )}
                    </div>

                    {/* Outline */}
                    <aside className="lg:col-span-1">
                        {/* Sticky so the outline stays reachable while the
                            lesson notes scroll. */}
                        <div
                            className={`overflow-hidden ${CARD} lg:sticky lg:top-4`}
                        >
                            <div
                                className={`border-b ${SECTION_BORDER} px-4 py-3`}
                            >
                                <p className={LABEL}>
                                    {course.name} · {flat.length}{' '}
                                    {flat.length === 1 ? 'lesson' : 'lessons'}
                                </p>
                                {flat.length > 0 && (
                                    <p className={`mt-0.5 ${NUM}`}>
                                        {progress.completed_count} of{' '}
                                        {flat.length} completed
                                    </p>
                                )}
                            </div>

                            <div className="max-h-[70vh] overflow-y-auto pb-2">
                                {flat.map((entry) => {
                                    const isActive =
                                        entry.lesson.id === activeId;

                                    return (
                                        <button
                                            key={entry.lesson.id}
                                            onClick={() =>
                                                setActiveId(entry.lesson.id)
                                            }
                                            className={`flex w-full items-center gap-2.5 border-l-2 py-2 pr-4 pl-3.5 text-left transition-all ${
                                                isActive
                                                    ? 'border-emerald-500 bg-emerald-50 dark:bg-emerald-950/30'
                                                    : 'border-transparent hover:bg-stone-50 dark:hover:bg-zinc-800/50'
                                            }`}
                                        >
                                            {done.has(entry.lesson.id) ? (
                                                <CheckCircle2 className="h-3.5 w-3.5 shrink-0 text-emerald-600 dark:text-emerald-400" />
                                            ) : (
                                                <span
                                                    className={`h-1.5 w-1.5 shrink-0 rounded-full ${
                                                        isActive
                                                            ? 'bg-emerald-500'
                                                            : 'bg-gray-300 dark:bg-gray-600'
                                                    }`}
                                                />
                                            )}
                                            <span
                                                className={`min-w-0 flex-1 truncate text-[13px] ${
                                                    isActive
                                                        ? 'font-medium text-emerald-700 dark:text-emerald-400'
                                                        : done.has(
                                                                entry.lesson.id,
                                                            )
                                                          ? 'text-gray-400 dark:text-gray-500'
                                                          : 'text-gray-600 dark:text-gray-300'
                                                }`}
                                            >
                                                {entry.lesson.name}
                                            </span>
                                            <span className={`shrink-0 ${NUM}`}>
                                                {entry.lesson.duration_seconds
                                                    ? clock(
                                                          entry.lesson
                                                              .duration_seconds,
                                                      )
                                                    : '—'}
                                            </span>
                                        </button>
                                    );
                                })}

                                {flat.length === 0 && (
                                    <p className="px-4 py-6 text-center font-mono text-[11px] text-gray-400 dark:text-gray-500">
                                        No lessons yet
                                    </p>
                                )}
                            </div>
                        </div>
                    </aside>
                </div>
            </div>
        </AppLayout>
    );
}
