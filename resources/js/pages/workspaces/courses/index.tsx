import PageHeader from '@/components/common/PageHeader';
import Pagination from '@/components/ui/pagination';
import { PERMISSIONS } from '@/constants/permissions';
import { usePermission } from '@/hooks/use-permission';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, PaginatedData } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import {
    GraduationCap,
    Pencil,
    Play,
    Plus,
    Search,
    Trash2,
    X,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import CourseFilters, {
    type CourseFilterValue,
} from './components/course-filters';
import CourseFormDialog from './components/course-form-dialog';
import { duration, plural } from './lib/format';
import {
    BAR,
    BTN_PRIMARY,
    CARD,
    CARD_HOVER,
    EMPTY,
    ICON_BTN,
    LABEL,
    MUTED,
    NUM,
    PAGE,
    PILL_ACCENT,
    PILL_OUTLINE,
    TRACK,
    TRACK_THIN,
} from './lib/ui';
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
    filters: { search: string; categories: string[] };
    categoryOptions: string[];
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
        <div className={`overflow-hidden ${CARD_HOVER}`}>
            <div
                className={`relative flex h-24 items-start justify-between gap-2 bg-gradient-to-br p-2.5 ${coverUrl ? '' : banner}`}
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
                    <span className="min-w-0 truncate rounded-md bg-black/30 px-2 py-1 font-mono text-[10px] font-medium tracking-wider text-white/90 uppercase backdrop-blur-sm">
                        {course.category}
                    </span>
                )}

                <div className="ml-auto flex shrink-0 items-center gap-1.5">
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
                            {duration(course.duration_seconds ?? 0)}
                        </span>
                    )}
                </div>
            </div>

            <div className="space-y-2.5 p-3.5">
                <div>
                    <Link
                        href={`${baseUrl}/${course.id}`}
                        className="block truncate text-[15px] font-semibold text-gray-800 transition-colors hover:text-emerald-600 dark:text-gray-100 dark:hover:text-emerald-400"
                    >
                        {course.name}
                    </Link>
                    <p className={`mt-0.5 ${MUTED}`}>
                        {course.modules_count ?? 0} modules ·{' '}
                        {course.lessons_count ?? 0} lessons
                    </p>
                </div>

                <div className={TRACK}>
                    <div
                        className={BAR}
                        style={{ width: `${course.team_percent ?? 0}%` }}
                    />
                </div>

                <div className="flex flex-wrap items-center justify-between gap-2">
                    <span className="text-[13px] text-gray-500 tabular-nums dark:text-gray-400">
                        Team {course.team_percent ?? 0}%
                    </span>

                    <div className="flex items-center gap-1.5">
                        {canDelete && (
                            <button
                                onClick={onDelete}
                                aria-label="Delete course"
                                className={`${ICON_BTN} hover:text-red-600`}
                            >
                                <Trash2 className="h-3.5 w-3.5" />
                            </button>
                        )}
                        {canEdit && (
                            <button onClick={onEdit} className={PILL_OUTLINE}>
                                <Pencil className="h-3 w-3" />
                                Edit
                            </button>
                        )}
                        <Link
                            href={`${baseUrl}/${course.id}/preview`}
                            className={PILL_ACCENT}
                        >
                            <Play className="h-3 w-3" />
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
    filters,
    categoryOptions,
}: Props) {
    const baseUrl = `/workspaces/${workspace.slug}/courses`;

    const [search, setSearch] = useState(filters.search);
    const [loading, setLoading] = useState(false);
    const [dialogOpen, setDialogOpen] = useState(openCreateOnMount);
    const [editing, setEditing] = useState<Course | null>(null);

    const canCreate = usePermission(PERMISSIONS.CreateCourses);
    const canEdit = usePermission(PERMISSIONS.EditCourses);
    const canDelete = usePermission(PERMISSIONS.DeleteCourses);

    const breadcrumbs: BreadcrumbItem[] = [{ title: 'Courses', href: baseUrl }];

    /** One place that turns the toolbar's state into a request. */
    function query(next: {
        search?: string;
        categories?: string[];
        page?: number;
    }) {
        router.get(
            baseUrl,
            {
                search: next.search ?? search,
                categories: next.categories ?? filters.categories,
                // Narrowing the list has to send you back to the first page, or
                // you can land on a page that no longer exists.
                page: next.page ?? 1,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                onStart: () => setLoading(true),
                onFinish: () => setLoading(false),
            },
        );
    }

    // Debounced so typing doesn't fire a request per keystroke.
    useEffect(() => {
        if (search === filters.search) return;

        const timer = setTimeout(() => query({ search }), 350);

        return () => clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    const activeFilters: CourseFilterValue = {
        categories: filters.categories,
    };

    const isFiltered = filters.search !== '' || filters.categories.length > 0;

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

            <div className={PAGE}>
                <PageHeader
                    title="Courses"
                    description={
                        stats.can_manage
                            ? `${plural(stats.total_courses, 'course')}${
                                  stats.draft_courses > 0
                                      ? ` · ${stats.draft_courses} in draft`
                                      : ''
                              }`
                            : `${plural(stats.total_courses, 'course')} available · ${stats.my_courses} started`
                    }
                >
                    {canCreate && (
                        <button onClick={openCreate} className={BTN_PRIMARY}>
                            <Plus className="h-4 w-4" />
                            New course
                        </button>
                    )}
                </PageHeader>

                {/* Stat tiles */}
                <div className="mb-5 grid grid-cols-1 gap-3.5 sm:grid-cols-3">
                    <div className={`${CARD} p-5`}>
                        <p className={LABEL}>All Courses</p>
                        <p className="mt-1 font-mono text-[22px] font-semibold tracking-tight text-gray-800 tabular-nums dark:text-gray-100">
                            {stats.total_courses}
                        </p>
                        <p className={`mt-0.5 ${MUTED}`}>
                            {stats.can_manage
                                ? `${plural(stats.total_lessons, 'lesson')} in total`
                                : 'published and open to you'}
                        </p>
                    </div>

                    <div className={`${CARD} p-5`}>
                        <p className={LABEL}>
                            {stats.can_manage ? 'Published' : 'My Courses'}
                        </p>
                        <p className="mt-1 font-mono text-[22px] font-semibold tracking-tight text-gray-800 tabular-nums dark:text-gray-100">
                            {stats.can_manage
                                ? stats.active_courses
                                : stats.my_courses}
                        </p>
                        <p className={`mt-0.5 ${MUTED}`}>
                            {stats.can_manage
                                ? stats.draft_courses > 0
                                    ? `${stats.draft_courses} still in draft`
                                    : 'nothing in draft'
                                : stats.my_courses === 0
                                  ? 'nothing started yet'
                                  : `${plural(stats.total_lessons, 'lesson')} to work through`}
                        </p>
                    </div>

                    {stats.can_manage ? (
                        <div className={`${CARD} p-5`}>
                            <p className={LABEL}>Avg Completion</p>
                            <p className="mt-1 font-mono text-[22px] font-semibold tracking-tight text-amber-600 tabular-nums dark:text-amber-500">
                                {stats.avg_completion}%
                            </p>
                            <p className={`mt-0.5 ${MUTED}`}>
                                team average across all courses
                            </p>
                        </div>
                    ) : (
                        <div className={`${CARD} p-5`}>
                            <p className={LABEL}>My Completion</p>
                            <p className="mt-1 font-mono text-[22px] font-semibold tracking-tight text-emerald-600 tabular-nums dark:text-emerald-500">
                                {stats.my_completion}%
                            </p>
                            <div className={`mt-2 ${TRACK}`}>
                                <div
                                    className={BAR}
                                    style={{
                                        width: `${stats.my_completion}%`,
                                    }}
                                />
                            </div>
                            <p className={`mt-1.5 ${MUTED}`}>
                                {stats.completed_lessons} of{' '}
                                {plural(stats.total_lessons, 'lesson')} in the
                                courses you started
                            </p>
                        </div>
                    )}
                </div>

                <div className="grid grid-cols-1 gap-5 lg:grid-cols-4">
                    <div className="space-y-3.5 lg:col-span-3">
                        {/* Section heading on the left, its controls on the right */}
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <h2 className="text-sm font-medium text-gray-800 dark:text-gray-100">
                                All Courses
                            </h2>

                            <div className="flex flex-1 flex-wrap items-center justify-end gap-2">
                                <div className="relative min-w-0 flex-1 sm:w-60 sm:flex-none">
                                    <Search className="pointer-events-none absolute top-1/2 left-3 h-3.5 w-3.5 -translate-y-1/2 text-gray-300 dark:text-gray-600" />
                                    <input
                                        type="text"
                                        value={search}
                                        onChange={(e) =>
                                            setSearch(e.target.value)
                                        }
                                        placeholder="Search courses…"
                                        className="h-9 w-full rounded-[10px] border border-black/8 bg-white pr-8 pl-8.5 text-[13px] text-gray-700 placeholder-gray-300 transition-colors outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/8 dark:bg-zinc-900 dark:text-gray-200 dark:placeholder-gray-600"
                                    />
                                    {search !== '' && (
                                        <button
                                            onClick={() => {
                                                setSearch('');
                                                query({ search: '' });
                                            }}
                                            aria-label="Clear search"
                                            className="absolute top-1/2 right-2 flex h-5 w-5 -translate-y-1/2 items-center justify-center rounded text-gray-400 transition-colors hover:text-gray-700 dark:hover:text-gray-200"
                                        >
                                            <X className="h-3.5 w-3.5" />
                                        </button>
                                    )}
                                </div>

                                <CourseFilters
                                    value={activeFilters}
                                    categoryOptions={categoryOptions}
                                    onChange={(next) =>
                                        query({ categories: next.categories })
                                    }
                                />
                            </div>
                        </div>

                        {/* Course grid */}
                        <div>
                            {courses.data.length === 0 ? (
                                <div className={EMPTY}>
                                    <GraduationCap className="h-6 w-6 text-gray-300 dark:text-gray-600" />
                                    <p className="font-mono text-[12px] text-gray-500 dark:text-gray-400">
                                        {isFiltered
                                            ? 'No courses match those filters'
                                            : stats.can_manage
                                              ? 'No courses yet'
                                              : 'No published courses yet'}
                                    </p>
                                    {isFiltered && (
                                        <button
                                            onClick={() => {
                                                setSearch('');
                                                query({
                                                    search: '',
                                                    categories: [],
                                                });
                                            }}
                                            className="font-mono text-[12px] text-emerald-600 transition-colors hover:text-emerald-700 dark:text-emerald-400"
                                        >
                                            Clear filters
                                        </button>
                                    )}
                                </div>
                            ) : (
                                <div
                                    className={`grid grid-cols-1 gap-3.5 transition-opacity sm:grid-cols-2 xl:grid-cols-3 ${
                                        loading ? 'opacity-50' : ''
                                    }`}
                                >
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
                                            onDelete={() =>
                                                handleDelete(course)
                                            }
                                        />
                                    ))}
                                </div>
                            )}

                            {(courses.last_page ?? 1) > 1 && (
                                <div className="mt-4 flex items-center justify-between gap-3">
                                    <p className={NUM}>
                                        {courses.from}–{courses.to} of{' '}
                                        {courses.total}
                                    </p>
                                    <Pagination
                                        currentPage={courses.current_page ?? 1}
                                        totalPages={courses.last_page ?? 1}
                                        onPageChange={(page) => query({ page })}
                                    />
                                </div>
                            )}
                        </div>
                    </div>

                    {/* Leaderboard */}
                    <aside className="lg:col-span-1">
                        <div className={`${CARD} p-5 lg:sticky lg:top-4`}>
                            <p className={LABEL}>Completion Leaderboard</p>

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
                                                <div
                                                    className={`mt-1 ${TRACK_THIN}`}
                                                >
                                                    <div
                                                        className={BAR}
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
