import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Play, Plus } from 'lucide-react';
import { useMemo, useState } from 'react';
import {
    type CourseLesson,
    type Course as CourseType,
    type CourseWorkspace,
} from './types';

interface Props {
    workspace: CourseWorkspace;
    course: CourseType;
    /** Lesson to open on, from ?lesson= — null when absent or unrecognised. */
    initialLessonId: number | null;
}

/** A lesson flattened with its module, so the outline reads as one sequence. */
interface FlatLesson {
    lesson: CourseLesson;
    moduleName: string;
    videoUrl: string;
    index: number;
}

/** Seconds as m:ss, or h:mm:ss once past an hour. */
function clock(seconds: number): string {
    const s = Math.floor(seconds % 60);
    const m = Math.floor((seconds / 60) % 60);
    const h = Math.floor(seconds / 3600);
    const mm = h > 0 ? String(m).padStart(2, '0') : String(m);

    return `${h > 0 ? `${h}:` : ''}${mm}:${String(s).padStart(2, '0')}`;
}

/** Course total, written the way a duration is read rather than as a clock. */
function totalLength(seconds: number): string {
    const h = Math.floor(seconds / 3600);
    const m = Math.round((seconds % 3600) / 60);

    return h > 0 ? `${h}h ${m}m` : `${m}m`;
}

const metaClass =
    'font-mono text-[10px] font-medium tracking-wider text-gray-400 uppercase dark:text-gray-500';

export default function CoursePreview({
    workspace,
    course,
    initialLessonId,
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

            <div className="p-4 md:p-7">
                {/* Header */}
                <div className="mb-5 flex items-start justify-between gap-4">
                    <div className="min-w-0">
                        <div className="flex items-center gap-2">
                            <Link
                                href={baseUrl}
                                className="flex items-center gap-1 font-mono text-[10px] font-medium tracking-wider text-emerald-600 uppercase transition-colors hover:text-emerald-700 dark:text-emerald-400"
                            >
                                <ArrowLeft className="h-3 w-3" />
                                All Courses
                            </Link>
                            <span className={`${metaClass} truncate`}>
                                {course.name}
                            </span>
                        </div>

                        <h1 className="mt-1 truncate text-[22px] font-semibold tracking-tight text-gray-800 dark:text-gray-100">
                            {course.name}
                        </h1>

                        <p className="mt-0.5 text-[13px] text-gray-500 dark:text-gray-400">
                            {modules.length}{' '}
                            {modules.length === 1 ? 'module' : 'modules'} ·{' '}
                            {flat.length}{' '}
                            {flat.length === 1 ? 'lesson' : 'lessons'}
                            {totalSeconds > 0
                                ? ` · ${totalLength(totalSeconds)}`
                                : ''}
                        </p>
                    </div>

                    <div className="flex shrink-0 items-center gap-2">
                        <Link
                            href={`${baseUrl}?new=1`}
                            className="flex h-9 items-center gap-1.5 rounded-lg bg-emerald-600 px-4 font-mono! text-[12px]! font-medium text-white transition-all hover:bg-emerald-700"
                        >
                            <Plus className="h-4 w-4" />
                            New course
                        </Link>
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-5 lg:grid-cols-3">
                    {/* Player + lesson detail */}
                    <div className="space-y-5 lg:col-span-2">
                        <div className="overflow-hidden rounded-xl bg-zinc-900">
                            {active?.lesson.video ? (
                                <video
                                    // Keyed so switching lessons loads the new
                                    // source instead of keeping the old buffer.
                                    key={active.lesson.id}
                                    src={active.videoUrl}
                                    controls
                                    autoPlay
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
                            <div className="rounded-xl border border-black/6 bg-white p-5 dark:border-white/6 dark:bg-zinc-900">
                                <p className={metaClass}>{active.moduleName}</p>

                                <h2 className="mt-1.5 text-[17px] font-semibold text-gray-800 dark:text-gray-100">
                                    {active.lesson.name}
                                </h2>

                                <p className="mt-1 font-mono text-[11px] text-gray-400 tabular-nums dark:text-gray-500">
                                    {active.lesson.duration_seconds
                                        ? `${clock(active.lesson.duration_seconds)}  `
                                        : ''}
                                    Lesson {active.index + 1} of {flat.length}
                                </p>

                                <div className="mt-4 rounded-lg border border-black/8 px-3 py-2 font-mono text-[11px] text-gray-400 dark:border-white/8 dark:text-gray-500">
                                    No file or link attached
                                </div>

                                <div className="mt-4 flex items-center gap-2">
                                    <button
                                        onClick={() =>
                                            prev && setActiveId(prev.lesson.id)
                                        }
                                        disabled={!prev}
                                        className="flex h-9 items-center rounded-lg border border-black/8 px-4 font-mono! text-[12px]! font-medium text-gray-600 transition-all hover:bg-stone-100 disabled:pointer-events-none disabled:opacity-30 dark:border-white/8 dark:text-gray-300 dark:hover:bg-zinc-800"
                                    >
                                        Previous
                                    </button>
                                    <button
                                        onClick={() =>
                                            next && setActiveId(next.lesson.id)
                                        }
                                        disabled={!next}
                                        className="flex h-9 items-center rounded-lg border border-black/8 bg-stone-100 px-4 font-mono! text-[12px]! font-medium text-gray-800 transition-all hover:bg-stone-200 disabled:pointer-events-none disabled:opacity-30 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-100 dark:hover:bg-zinc-700"
                                    >
                                        Next lesson
                                    </button>
                                    <Link
                                        href={courseUrl}
                                        className="ml-2 font-mono text-[12px] text-emerald-600 transition-colors hover:text-emerald-700 dark:text-emerald-400"
                                    >
                                        Back to outline
                                    </Link>
                                </div>
                            </div>
                        )}
                    </div>

                    {/* Outline */}
                    <aside className="lg:col-span-1">
                        <div className="overflow-hidden rounded-xl border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
                            <div className="px-4 pt-4 pb-2">
                                <p className={metaClass}>
                                    {course.name} · {flat.length}{' '}
                                    {flat.length === 1 ? 'lesson' : 'lessons'}
                                </p>
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
                                            className={`flex w-full items-center gap-2.5 px-4 py-2 text-left transition-all ${
                                                isActive
                                                    ? 'bg-emerald-50 dark:bg-emerald-950/30'
                                                    : 'hover:bg-stone-50 dark:hover:bg-zinc-800/50'
                                            }`}
                                        >
                                            <span
                                                className={`h-1.5 w-1.5 shrink-0 rounded-full ${
                                                    isActive
                                                        ? 'bg-emerald-500'
                                                        : 'bg-gray-300 dark:bg-gray-600'
                                                }`}
                                            />
                                            <span
                                                className={`min-w-0 flex-1 truncate text-[13px] ${
                                                    isActive
                                                        ? 'text-emerald-700 dark:text-emerald-400'
                                                        : 'text-gray-600 dark:text-gray-300'
                                                }`}
                                            >
                                                {entry.lesson.name}
                                            </span>
                                            <span className="shrink-0 font-mono text-[10px] text-gray-400 tabular-nums dark:text-gray-500">
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
