import PageHeader from '@/components/common/PageHeader';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { PERMISSIONS } from '@/constants/permissions';
import { usePermission } from '@/hooks/use-permission';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, PaginatedData } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import {
    GraduationCap,
    MoreHorizontal,
    Pencil,
    Play,
    Plus,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import CourseFormDialog from './components/course-form-dialog';
import {
    type Course,
    type CourseWorkspace,
    STATUS_LABELS,
    STATUS_STYLES,
} from './types';

interface Props {
    workspace: CourseWorkspace;
    courses: PaginatedData<Course>;
    /** Set by ?new=1 so the player's "New course" button lands ready to type. */
    openCreateOnMount?: boolean;
}

function shortDate(value: string | null) {
    if (!value) return '—';
    return new Date(value).toLocaleDateString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

export default function CoursesIndex({
    workspace,
    courses,
    openCreateOnMount = false,
}: Props) {
    const baseUrl = `/workspaces/${workspace.slug}/courses`;

    // `null` in the dialog means create; a course means edit.
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

    function openEdit(course: Course) {
        setEditing(course);
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
                <PageHeader
                    title="Courses"
                    description="Courses for this workspace"
                >
                    {canCreate && (
                        <button
                            onClick={openCreate}
                            className="flex h-9 items-center gap-1.5 rounded-lg bg-emerald-600 px-4 font-mono! text-[12px]! font-medium text-white transition-all hover:bg-emerald-700"
                        >
                            <Plus className="h-4 w-4" />
                            New Course
                        </button>
                    )}
                </PageHeader>

                {courses.data.length === 0 ? (
                    <div className="flex flex-col items-center justify-center rounded-xl border border-dashed border-black/12 bg-stone-50 py-16 text-center dark:border-white/12 dark:bg-zinc-900">
                        <GraduationCap className="h-6 w-6 text-gray-300 dark:text-gray-600" />
                        <p className="mt-2 font-mono text-[12px] text-gray-500 dark:text-gray-400">
                            No courses yet
                        </p>
                    </div>
                ) : (
                    <div className="grid grid-cols-1 gap-3.5 sm:grid-cols-2 lg:grid-cols-3">
                        {courses.data.map((course) => (
                            <CourseCard
                                key={course.id}
                                course={course}
                                baseUrl={baseUrl}
                                canEdit={canEdit}
                                canDelete={canDelete}
                                onEdit={() => openEdit(course)}
                                onDelete={() => handleDelete(course)}
                            />
                        ))}
                    </div>
                )}

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
    // The bucket is private, so the cover is reached through the app, which
    // signs a short-lived URL.
    const coverUrl = course.cover_image
        ? `${baseUrl}/${course.id}/media/${course.cover_image.id}`
        : null;

    return (
        <div className="group relative overflow-hidden rounded-xl border border-black/6 bg-white transition-all hover:border-black/12 dark:border-white/6 dark:bg-zinc-900 dark:hover:border-white/12">
            <Link href={`${baseUrl}/${course.id}`} className="block">
                <div
                    className="relative flex aspect-video items-center justify-center bg-stone-100 bg-cover bg-center dark:bg-zinc-800"
                    style={
                        coverUrl
                            ? { backgroundImage: `url(${coverUrl})` }
                            : undefined
                    }
                >
                    {!coverUrl && (
                        <GraduationCap className="h-7 w-7 text-gray-300 dark:text-gray-600" />
                    )}

                    <span
                        className={`absolute top-2.5 left-2.5 inline-flex items-center rounded-full px-2.5 py-0.5 font-mono text-[11px] font-medium ${STATUS_STYLES[course.status]}`}
                    >
                        {STATUS_LABELS[course.status]}
                    </span>
                </div>

                <div className="space-y-1.5 p-4">
                    <p className="truncate text-[13px] font-medium text-gray-800 dark:text-gray-100">
                        {course.name}
                    </p>
                    <div className="flex items-center gap-2">
                        {course.category && (
                            <span className="truncate rounded-md bg-stone-100 px-2 py-0.5 font-mono text-[10px] tracking-wider text-gray-500 uppercase dark:bg-zinc-800 dark:text-gray-400">
                                {course.category}
                            </span>
                        )}
                        <span className="font-mono text-[11px] text-gray-400 tabular-nums dark:text-gray-500">
                            {course.modules_count ?? 0}{' '}
                            {course.modules_count === 1 ? 'module' : 'modules'}
                        </span>
                        <span className="font-mono text-[11px] text-gray-400 tabular-nums dark:text-gray-500">
                            {shortDate(course.created_at)}
                        </span>
                    </div>
                </div>
            </Link>

            <div className="absolute top-2 right-2">
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <button
                            aria-label="Course actions"
                            className="flex h-7 w-7 items-center justify-center rounded-lg bg-white/85 text-gray-600 opacity-0 backdrop-blur-sm transition-all group-hover:opacity-100 hover:bg-white focus:opacity-100 dark:bg-zinc-900/85 dark:text-gray-300 dark:hover:bg-zinc-900"
                        >
                            <MoreHorizontal className="h-4 w-4" />
                        </button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        <DropdownMenuItem asChild>
                            <Link href={`${baseUrl}/${course.id}/preview`}>
                                <Play className="mr-2 h-4 w-4" />
                                Preview
                            </Link>
                        </DropdownMenuItem>
                        {canEdit && <DropdownMenuSeparator />}
                        {canEdit && (
                            <DropdownMenuItem onSelect={onEdit}>
                                <Pencil className="mr-2 h-4 w-4" />
                                Edit
                            </DropdownMenuItem>
                        )}
                        {canEdit && canDelete && <DropdownMenuSeparator />}
                        {canDelete && (
                            <DropdownMenuItem
                                variant="destructive"
                                onSelect={onDelete}
                            >
                                <Trash2 className="mr-2 h-4 w-4" />
                                Delete
                            </DropdownMenuItem>
                        )}
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </div>
    );
}
