import { PERMISSIONS } from '@/constants/permissions';
import { usePermission } from '@/hooks/use-permission';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Pencil } from 'lucide-react';
import { useState } from 'react';
import CourseFormDialog from './components/course-form-dialog';
import CourseStructure from './components/course-structure';
import {
    type Course,
    type CourseProgress,
    type CourseWorkspace,
    type LeaderboardRow,
} from './types';

interface Props {
    workspace: CourseWorkspace;
    course: Course;
    progress: CourseProgress;
    team: LeaderboardRow[];
}

const metaClass =
    'font-mono text-[10px] font-medium tracking-wider text-gray-400 uppercase dark:text-gray-500';

/** Total course length, written the way a duration is read. */
function length(seconds: number): string {
    const h = Math.floor(seconds / 3600);
    const m = Math.round((seconds % 3600) / 60);
    return h > 0 ? `${h}h ${m}m` : `${m}m`;
}

function initials(name: string): string {
    return name
        .split(/\s+/)
        .slice(0, 2)
        .map((part) => part[0] ?? '')
        .join('')
        .toUpperCase();
}

/** Avatar tint, keyed off the user id so it stays put between renders. */
const AVATARS = [
    'bg-sky-500',
    'bg-emerald-500',
    'bg-amber-500',
    'bg-violet-500',
    'bg-rose-500',
    'bg-red-500',
];

export default function ShowCourse({
    workspace,
    course,
    progress,
    team,
}: Props) {
    const baseUrl = `/workspaces/${workspace.slug}/courses`;
    const canEdit = usePermission(PERMISSIONS.EditCourses);
    const [editOpen, setEditOpen] = useState(false);

    const modules = course.modules ?? [];
    const lessonCount = modules.reduce((n, m) => n + m.lessons.length, 0);
    const totalSeconds = modules.reduce(
        (sum, m) =>
            sum + m.lessons.reduce((s, l) => s + (l.duration_seconds ?? 0), 0),
        0,
    );

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Courses', href: baseUrl },
        { title: course.name, href: `${baseUrl}/${course.id}` },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={course.name} />

            <div className="p-4 md:p-7">
                {/* Header */}
                <div className="mb-5">
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
                        {lessonCount} {lessonCount === 1 ? 'lesson' : 'lessons'}
                        {totalSeconds > 0 ? ` · ${length(totalSeconds)}` : ''}
                    </p>
                </div>

                <div className="grid grid-cols-1 gap-5 lg:grid-cols-3">
                    <div className="space-y-3.5 lg:col-span-2">
                        {/* Summary */}
                        <div className="rounded-xl border border-black/6 bg-white p-5 dark:border-white/6 dark:bg-zinc-900">
                            <div className="flex items-start justify-between gap-4">
                                <div className="flex min-w-0 flex-wrap items-center gap-2">
                                    {course.category && (
                                        <span className="rounded-md bg-rose-50 px-2 py-1 font-mono text-[10px] font-medium tracking-wider text-rose-700 uppercase dark:bg-rose-950/40 dark:text-rose-400">
                                            {course.category}
                                        </span>
                                    )}
                                    <span
                                        className={`rounded-md px-2 py-1 font-mono text-[10px] font-medium tracking-wider uppercase ${
                                            course.status === 'published'
                                                ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400'
                                                : 'bg-stone-100 text-gray-500 dark:bg-zinc-800 dark:text-gray-400'
                                        }`}
                                    >
                                        {course.status === 'published'
                                            ? 'Published'
                                            : 'Draft'}
                                    </span>
                                    <span className="font-mono text-[12px] text-gray-400 tabular-nums dark:text-gray-500">
                                        {totalSeconds > 0
                                            ? `${length(totalSeconds)} · `
                                            : ''}
                                        {lessonCount} lessons
                                    </span>
                                </div>

                                <div className="flex shrink-0 items-center gap-2">
                                    {canEdit && (
                                        <button
                                            onClick={() => setEditOpen(true)}
                                            aria-label="Edit course"
                                            className="flex h-9 w-9 items-center justify-center rounded-lg border border-black/8 text-gray-500 transition-all hover:bg-stone-100 dark:border-white/8 dark:text-gray-400 dark:hover:bg-zinc-800"
                                        >
                                            <Pencil className="h-4 w-4" />
                                        </button>
                                    )}
                                    <button
                                        onClick={() =>
                                            router.post(
                                                `${baseUrl}/${course.id}/start`,
                                            )
                                        }
                                        disabled={lessonCount === 0}
                                        title={
                                            lessonCount === 0
                                                ? 'This course has no lessons yet'
                                                : undefined
                                        }
                                        className="flex h-9 items-center rounded-lg bg-emerald-600 px-5 text-[13px] font-medium text-white transition-all hover:bg-emerald-700 disabled:pointer-events-none disabled:opacity-40"
                                    >
                                        {progress.started
                                            ? 'Continue'
                                            : 'Start course'}
                                    </button>
                                </div>
                            </div>

                            {course.description && (
                                <p className="mt-4 text-[14px] leading-relaxed whitespace-pre-wrap text-gray-600 dark:text-gray-300">
                                    {course.description}
                                </p>
                            )}

                            {lessonCount > 0 && (
                                <div className="mt-4">
                                    <div className="h-1.5 w-full overflow-hidden rounded-full bg-stone-200 dark:bg-zinc-800">
                                        <div
                                            className="h-full rounded-full bg-emerald-600 transition-all"
                                            style={{
                                                width: `${progress.percent}%`,
                                            }}
                                        />
                                    </div>
                                    <p className="mt-1.5 font-mono text-[11px] text-gray-400 tabular-nums dark:text-gray-500">
                                        {progress.completed_count} of{' '}
                                        {progress.total_lessons} lessons done ·{' '}
                                        {progress.percent}%
                                    </p>
                                </div>
                            )}
                        </div>

                        <CourseStructure
                            baseUrl={baseUrl}
                            courseId={course.id}
                            modules={modules}
                            canEdit={canEdit}
                            completedLessonIds={progress.completed_lesson_ids}
                        />
                    </div>

                    {/* Team */}
                    <aside className="lg:col-span-1">
                        <div className="rounded-xl border border-black/6 bg-white p-5 lg:sticky lg:top-4 dark:border-white/6 dark:bg-zinc-900">
                            <p className={metaClass}>How The Team Is Doing</p>

                            {team.length === 0 ? (
                                <p className="mt-3 font-mono text-[11px] text-gray-400 dark:text-gray-500">
                                    Nobody has started this course yet
                                </p>
                            ) : (
                                <ul className="mt-4 space-y-3.5">
                                    {team.map((member) => (
                                        <li
                                            key={member.id}
                                            className="flex items-center gap-3"
                                        >
                                            <span
                                                className={`flex h-8 w-8 shrink-0 items-center justify-center rounded-lg font-mono text-[11px] font-medium text-white ${
                                                    AVATARS[
                                                        member.id %
                                                            AVATARS.length
                                                    ]
                                                }`}
                                            >
                                                {initials(member.name)}
                                            </span>

                                            <div className="min-w-0 flex-1">
                                                <div className="flex items-baseline justify-between gap-2">
                                                    <span className="truncate text-[13px] text-gray-700 dark:text-gray-200">
                                                        {member.name}
                                                    </span>
                                                    <span className="shrink-0 font-mono text-[11px] font-medium text-gray-600 tabular-nums dark:text-gray-300">
                                                        {member.percent}%
                                                    </span>
                                                </div>
                                                <div className="mt-1 h-1 w-full overflow-hidden rounded-full bg-stone-200 dark:bg-zinc-800">
                                                    <div
                                                        className="h-full rounded-full bg-emerald-600"
                                                        style={{
                                                            width: `${member.percent}%`,
                                                        }}
                                                    />
                                                </div>
                                            </div>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>
                    </aside>
                </div>

                <CourseFormDialog
                    open={editOpen}
                    onOpenChange={setEditOpen}
                    baseUrl={baseUrl}
                    course={course}
                />
            </div>
        </AppLayout>
    );
}
