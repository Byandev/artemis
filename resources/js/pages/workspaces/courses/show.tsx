import PageHeader from '@/components/common/PageHeader';
import { PERMISSIONS } from '@/constants/permissions';
import { usePermission } from '@/hooks/use-permission';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { GraduationCap, Pencil, Play } from 'lucide-react';
import { useState } from 'react';
import CourseFormDialog from './components/course-form-dialog';
import CourseStructure from './components/course-structure';
import {
    type Course,
    type CourseWorkspace,
    STATUS_LABELS,
    STATUS_STYLES,
} from './types';

interface Props {
    workspace: CourseWorkspace;
    course: Course;
}

const labelClass =
    'font-mono text-[10px] font-medium tracking-wider text-gray-400 uppercase dark:text-gray-500';
const cardClass =
    'rounded-xl border border-black/6 bg-white p-5 dark:border-white/6 dark:bg-zinc-900';

function longDate(value: string | null) {
    if (!value) return '—';
    return new Date(value).toLocaleDateString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

/** One labelled row in the details panel. */
function DetailRow({
    label,
    children,
}: {
    label: string;
    children: React.ReactNode;
}) {
    return (
        <div className="flex items-baseline justify-between gap-3 py-2.5">
            <span className={labelClass}>{label}</span>
            <div className="min-w-0 text-right">{children}</div>
        </div>
    );
}

export default function ShowCourse({ workspace, course }: Props) {
    const baseUrl = `/workspaces/${workspace.slug}/courses`;
    const canEdit = usePermission(PERMISSIONS.EditCourses);
    const [editOpen, setEditOpen] = useState(false);

    const modules = course.modules ?? [];
    const lessonCount = modules.reduce((n, m) => n + m.lessons.length, 0);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Courses', href: baseUrl },
        { title: course.name, href: `${baseUrl}/${course.id}` },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={course.name} />

            <div className="p-4 md:p-7">
                <PageHeader
                    title={course.name}
                    description={`${modules.length} ${modules.length === 1 ? 'module' : 'modules'} · ${lessonCount} ${lessonCount === 1 ? 'lesson' : 'lessons'}`}
                >
                    <Link
                        href={`${baseUrl}/${course.id}/preview`}
                        className="flex h-9 items-center gap-1.5 rounded-lg border border-black/8 bg-stone-100 px-4 font-mono! text-[12px]! font-medium text-gray-600 transition-all hover:bg-stone-200 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-300 dark:hover:bg-zinc-700"
                    >
                        <Play className="h-4 w-4" />
                        Preview
                    </Link>
                    {canEdit && (
                        <button
                            onClick={() => setEditOpen(true)}
                            className="flex h-9 items-center gap-1.5 rounded-lg bg-emerald-600 px-4 font-mono! text-[12px]! font-medium text-white transition-all hover:bg-emerald-700"
                        >
                            <Pencil className="h-4 w-4" />
                            Edit
                        </button>
                    )}
                </PageHeader>

                <div className="grid grid-cols-1 gap-5 lg:grid-cols-3">
                    {/* Main column: the course's own content. */}
                    <div className="space-y-5 lg:col-span-2">
                        <div className="overflow-hidden rounded-xl border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
                            <div
                                className="flex aspect-[21/9] items-center justify-center bg-stone-100 bg-cover bg-center dark:bg-zinc-800"
                                style={
                                    course.cover_image
                                        ? {
                                              // The bucket is private, so the
                                              // image is reached through the
                                              // app, which signs a short-lived
                                              // URL.
                                              backgroundImage: `url(${baseUrl}/${course.id}/media/${course.cover_image.id})`,
                                          }
                                        : undefined
                                }
                            >
                                {!course.cover_image && (
                                    <GraduationCap className="h-8 w-8 text-gray-300 dark:text-gray-600" />
                                )}
                            </div>

                            <div className="space-y-2 p-5">
                                <h2 className={labelClass}>Description</h2>
                                {course.description ? (
                                    <p className="text-[13px] leading-relaxed whitespace-pre-wrap text-gray-600 dark:text-gray-400">
                                        {course.description}
                                    </p>
                                ) : (
                                    <p className="font-mono text-[12px] text-gray-400 dark:text-gray-500">
                                        No description
                                    </p>
                                )}
                            </div>
                        </div>

                        <CourseStructure
                            baseUrl={baseUrl}
                            courseId={course.id}
                            modules={modules}
                            canEdit={canEdit}
                        />
                    </div>

                    {/* Side column: everything recorded about the course. */}
                    <aside className="lg:col-span-1">
                        <div className={cardClass}>
                            <h2 className={`${labelClass} mb-1`}>
                                Course Details
                            </h2>

                            <div className="divide-y divide-black/6 dark:divide-white/6">
                                <DetailRow label="Status">
                                    <span
                                        className={`inline-flex items-center rounded-full px-2.5 py-0.5 font-mono text-[11px] font-medium ${STATUS_STYLES[course.status]}`}
                                    >
                                        {STATUS_LABELS[course.status]}
                                    </span>
                                </DetailRow>

                                <DetailRow label="Category">
                                    {course.category ? (
                                        <span className="rounded-md bg-stone-100 px-2 py-0.5 font-mono text-[11px] text-gray-600 dark:bg-zinc-800 dark:text-gray-300">
                                            {course.category}
                                        </span>
                                    ) : (
                                        <span className="font-mono text-[12px] text-gray-400 dark:text-gray-500">
                                            —
                                        </span>
                                    )}
                                </DetailRow>

                                <DetailRow label="Modules">
                                    <span className="font-mono text-[13px] text-gray-700 tabular-nums dark:text-gray-200">
                                        {modules.length}
                                    </span>
                                </DetailRow>

                                <DetailRow label="Lessons">
                                    <span className="font-mono text-[13px] text-gray-700 tabular-nums dark:text-gray-200">
                                        {lessonCount}
                                    </span>
                                </DetailRow>

                                <DetailRow label="Cover Image">
                                    <span className="truncate font-mono text-[12px] text-gray-600 dark:text-gray-300">
                                        {course.cover_image?.file_name ?? '—'}
                                    </span>
                                </DetailRow>

                                <DetailRow label="Created">
                                    <span className="font-mono text-[12px] text-gray-600 tabular-nums dark:text-gray-300">
                                        {longDate(course.created_at)}
                                    </span>
                                </DetailRow>

                                <DetailRow label="Updated">
                                    <span className="font-mono text-[12px] text-gray-600 tabular-nums dark:text-gray-300">
                                        {longDate(course.updated_at)}
                                    </span>
                                </DetailRow>
                            </div>
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
