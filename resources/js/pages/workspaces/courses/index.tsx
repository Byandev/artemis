import { PERMISSIONS } from '@/constants/permissions';
import { usePermission } from '@/hooks/use-permission';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, PaginatedData } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { GraduationCap, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import CourseFormDialog from './components/course-form-dialog';
import {
    type Course,
    type CourseStats,
    type CourseWorkspace,
    type LeaderboardRow,
} from './types';

interface Props {
    workspace: CourseWorkspace;
    courses: PaginatedData<Course>;
    stats: CourseStats;
    leaderboard: LeaderboardRow[];
    /** Set by ?new=1 so the player's "New course" button lands ready to type. */
    openCreateOnMount?: boolean;
}

const metaClass =
    'font-mono text-[10px] font-medium tracking-wider text-gray-400 uppercase dark:text-gray-500';

/** Total video length, written the way a duration is read. */
function length(seconds: number): string {
    const h = Math.floor(seconds / 3600);
    const m = Math.round((seconds % 3600) / 60);
    return h > 0 ? `${h}h ${m}m` : `${m}m`;
}

/**
 * Banner colour when a course has no cover. Keyed off the id so a given course
 * keeps the same one rather than shuffling between renders.
 */
const BANNERS = [
    'from-slate-700 to-slate-900',
    'from-purple-800 to-purple-950',
    'from-emerald-800 to-emerald-950',
    'from-amber-700 to-amber-900',
    'from-sky-800 to-sky-950',
    'from-rose-800 to-rose-950',
];

function CourseCard({
    course,
    baseUrl,
    canEdit,
    canDelete,
    onEdit,
    onDelete,
}: {
    course: Course;
    baseUrl: string;
    canEdit: boolean;
    canDelete: boolean;
    onEdit: () => void;
    onDelete: () => void;
}) {
    const coverUrl = course.cover_image
        ? `${baseUrl}/${course.id}/media/${course.cover_image.id}`
        : null;

    const banner = BANNERS[course.id % BANNERS.length];
    const live = course.status === 'published';

    return (
        <div className="overflow-hidden rounded-xl border border-black/6 bg-white transition-all hover:border-black/12 dark:border-white/6 dark:bg-zinc-900 dark:hover:border-white/12">
            <div
                className={`relative flex h-32 items-start justify-between bg-gradient-to-br p-3 ${coverUrl ? '' : banner}`}
                style={
                    coverUrl
                        ? {
                              // The bucket is private, so the cover is reached
                              // through the app, which signs a short-lived URL.
                              backgroundImage: `url(${coverUrl})`,
                              backgroundSize: 'cover',
                              backgroundPosition: 'center',
                          }
                        : undefined
                }
            >
                {course.category && (
                    <span className="rounded-md bg-black/30 px-2 py-1 font-mono text-[10px] font-medium tracking-wider text-white/90 uppercase backdrop-blur-sm">
                        {course.category}
                    </span>
                )}

                <div className="ml-auto flex items-center gap-2">
                    <span
                        className={`rounded-md px-2 py-1 font-mono text-[10px] font-medium tracking-wider uppercase ${
                            live
                                ? 'bg-emerald-500/90 text-white'
                                : 'bg-black/30 text-white/80 backdrop-blur-sm'
                        }`}
                    >
                        {live ? 'Live' : 'Draft'}
                    </span>
                    {(course.duration_seconds ?? 0) > 0 && (
                        <span className="font-mono text-[11px] text-white/80 tabular-nums">
                            {length(course.duration_seconds ?? 0)}
                        </span>
                    )}
                </div>
            </div>

            <div className="space-y-3 p-4">
                <div>
                    <Link
                        href={`${baseUrl}/${course.id}`}
                        className="block truncate text-[15px] font-semibold text-gray-800 transition-colors hover:text-emerald-600 dark:text-gray-100 dark:hover:text-emerald-400"
                    >
                        {course.name}
                    </Link>
                    <p className="mt-0.5 text-[13px] text-gray-500 dark:text-gray-400">
                        {course.modules_count ?? 0} modules ·{' '}
                        {course.lessons_count ?? 0} lessons
                    </p>
                </div>

                <div className="h-1.5 w-full overflow-hidden rounded-full bg-stone-200 dark:bg-zinc-800">
                    <div
                        className="h-full rounded-full bg-emerald-600 transition-all"
                        style={{ width: `${course.team_percent ?? 0}%` }}
                    />
                </div>

                <div className="flex items-center justify-between">
                    <span className="text-[13px] text-gray-500 tabular-nums dark:text-gray-400">
                        Team {course.team_percent ?? 0}%
                    </span>

                    <div className="flex items-center gap-1.5">
                        {canDelete && (
                            <button
                                onClick={onDelete}
                                aria-label="Delete course"
                                className="flex h-8 w-8 items-center justify-center rounded-lg text-gray-400 transition-all hover:bg-stone-100 hover:text-red-600 dark:hover:bg-zinc-800"
                            >
                                <Trash2 className="h-3.5 w-3.5" />
                            </button>
                        )}
                        {canEdit && (
                            <button
                                onClick={onEdit}
                                className="flex h-8 items-center rounded-lg border border-black/8 px-3 font-mono! text-[11px]! font-medium tracking-wider text-gray-600 uppercase transition-all hover:bg-stone-100 dark:border-white/8 dark:text-gray-300 dark:hover:bg-zinc-800"
                            >
                                Edit
                            </button>
                        )}
                        <Link
                            href={`${baseUrl}/${course.id}/preview`}
                            className="flex h-8 items-center rounded-lg border border-emerald-600/20 bg-emerald-50 px-3 font-mono! text-[11px]! font-medium tracking-wider text-emerald-700 uppercase transition-all hover:bg-emerald-100 dark:border-emerald-400/20 dark:bg-emerald-950/40 dark:text-emerald-400"
                        >
                            Open
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    );
}

export default function CoursesIndex({
    workspace,
    courses,
    stats,
    leaderboard,
    openCreateOnMount = false,
}: Props) {
    const baseUrl = `/workspaces/${workspace.slug}/courses`;

    const [dialogOpen, setDialogOpen] = useState(openCreateOnMount);
    const [editing, setEditing] = useState<Course | null>(null);

    const canCreate = usePermission(PERMISSIONS.CreateCourses);
    const canEdit = usePermission(PERMISSIONS.EditCourses);
    const canDelete = usePermission(PERMISSIONS.DeleteCourses);

    const breadcrumbs: BreadcrumbItem[] = [{ title: 'Courses', href: baseUrl }];

    function openCreate() {
        setEditing(null);
        setDialogOpen(true);
    }

    function handleDelete(course: Course) {
        if (
            !confirm(`Delete "${course.name}"? Its cover image is removed too.`)
        ) {
            return;
        }
        router.delete(`${baseUrl}/${course.id}`, { preserveScroll: true });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Courses" />

            <div className="p-4 md:p-7">
                {/* Header */}
                <div className="mb-5 flex items-start justify-between gap-4">
                    <div className="min-w-0">
                        <p className={metaClass}>Course Management</p>
                        <h1 className="mt-1 text-[22px] font-semibold tracking-tight text-gray-800 dark:text-gray-100">
                            Courses
                        </h1>
                        <p className="mt-0.5 text-[13px] text-gray-500 dark:text-gray-400">
                            {stats.total_courses}{' '}
                            {stats.total_courses === 1 ? 'course' : 'courses'}
                            {stats.draft_courses > 0
                                ? ` · ${stats.draft_courses} in draft`
                                : ''}
                        </p>
                    </div>

                    {canCreate && (
                        <button
                            onClick={openCreate}
                            className="flex h-10 shrink-0 items-center gap-1.5 rounded-lg bg-emerald-600 px-4 text-[13px] font-medium text-white transition-all hover:bg-emerald-700"
                        >
                            <Plus className="h-4 w-4" />
                            New course
                        </button>
                    )}
                </div>

                {/* Stat tiles */}
                <div className="mb-5 grid grid-cols-1 gap-3.5 sm:grid-cols-2">
                    <div className="rounded-xl border border-black/6 bg-white p-5 dark:border-white/6 dark:bg-zinc-900">
                        <p className={metaClass}>Active Courses</p>
                        <p className="mt-1 font-mono text-[22px] font-semibold tracking-tight text-gray-800 tabular-nums dark:text-gray-100">
                            {stats.active_courses}
                        </p>
                        <p className="mt-0.5 text-[13px] text-gray-500 dark:text-gray-400">
                            {stats.total_lessons} lessons across{' '}
                            {stats.total_courses}{' '}
                            {stats.total_courses === 1 ? 'course' : 'courses'}
                        </p>
                    </div>

                    <div className="rounded-xl border border-black/6 bg-white p-5 dark:border-white/6 dark:bg-zinc-900">
                        <p className={metaClass}>Avg Completion</p>
                        <p className="mt-1 font-mono text-[22px] font-semibold tracking-tight text-amber-600 tabular-nums dark:text-amber-500">
                            {stats.avg_completion}%
                        </p>
                        <p className="mt-0.5 text-[13px] text-gray-500 dark:text-gray-400">
                            team average across all courses
                        </p>
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-5 lg:grid-cols-3">
                    {/* Course grid */}
                    <div className="lg:col-span-2">
                        {courses.data.length === 0 ? (
                            <div className="flex flex-col items-center justify-center rounded-xl border border-dashed border-black/12 bg-stone-50 py-16 text-center dark:border-white/12 dark:bg-zinc-900">
                                <GraduationCap className="h-6 w-6 text-gray-300 dark:text-gray-600" />
                                <p className="mt-2 font-mono text-[12px] text-gray-500 dark:text-gray-400">
                                    No courses yet
                                </p>
                            </div>
                        ) : (
                            <div className="grid grid-cols-1 gap-3.5 sm:grid-cols-2">
                                {courses.data.map((course) => (
                                    <CourseCard
                                        key={course.id}
                                        course={course}
                                        baseUrl={baseUrl}
                                        canEdit={canEdit}
                                        canDelete={canDelete}
                                        onEdit={() => {
                                            setEditing(course);
                                            setDialogOpen(true);
                                        }}
                                        onDelete={() => handleDelete(course)}
                                    />
                                ))}
                            </div>
                        )}
                    </div>

                    {/* Leaderboard */}
                    <aside className="lg:col-span-1">
                        <div className="rounded-xl border border-black/6 bg-white p-5 dark:border-white/6 dark:bg-zinc-900">
                            <p className={metaClass}>Completion Leaderboard</p>

                            {leaderboard.length === 0 ? (
                                <p className="mt-3 font-mono text-[11px] text-gray-400 dark:text-gray-500">
                                    Nobody has completed a lesson yet
                                </p>
                            ) : (
                                <ol className="mt-3 space-y-3">
                                    {leaderboard.map((row, i) => (
                                        <li
                                            key={row.id}
                                            className="flex items-start gap-3"
                                        >
                                            <span className="mt-0.5 w-4 shrink-0 font-mono text-[11px] text-gray-400 tabular-nums dark:text-gray-500">
                                                {i + 1}
                                            </span>
                                            <div className="min-w-0 flex-1">
                                                <div className="flex items-baseline justify-between gap-2">
                                                    <span className="truncate text-[13px] text-gray-700 dark:text-gray-200">
                                                        {row.name}
                                                    </span>
                                                    <span className="shrink-0 font-mono text-[11px] font-medium text-gray-600 tabular-nums dark:text-gray-300">
                                                        {row.percent}%
                                                    </span>
                                                </div>
                                                <div className="mt-1 h-1 w-full overflow-hidden rounded-full bg-stone-200 dark:bg-zinc-800">
                                                    <div
                                                        className="h-full rounded-full bg-emerald-600"
                                                        style={{
                                                            width: `${row.percent}%`,
                                                        }}
                                                    />
                                                </div>
                                            </div>
                                        </li>
                                    ))}
                                </ol>
                            )}
                        </div>
                    </aside>
                </div>

                <CourseFormDialog
                    open={dialogOpen}
                    onOpenChange={setDialogOpen}
                    baseUrl={baseUrl}
                    course={editing}
                />
            </div>
        </AppLayout>
    );
}
