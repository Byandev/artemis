import PageHeader from '@/components/common/PageHeader';
import { PERMISSIONS } from '@/constants/permissions';
import { usePermission } from '@/hooks/use-permission';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { Pencil } from 'lucide-react';
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
    course: Course;
}

export default function ShowCourse({ workspace, course }: Props) {
    const baseUrl = `/workspaces/${workspace.slug}/courses`;
    const canEdit = usePermission(PERMISSIONS.EditCourses);
    const [editOpen, setEditOpen] = useState(false);

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
                    description={course.category ?? undefined}
                >
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

                <div className="max-w-3xl space-y-5">
                    <span
                        className={`inline-flex items-center rounded-full px-2.5 py-0.5 font-mono text-[11px] font-medium ${STATUS_STYLES[course.status]}`}
                    >
                        {STATUS_LABELS[course.status]}
                    </span>

                    {course.cover_image && (
                        // The bucket is private, so the image is reached
                        // through the app, which signs a short-lived URL.
                        <img
                            src={`${baseUrl}/${course.id}/media/${course.cover_image.id}`}
                            alt={course.name}
                            className="max-h-72 w-full rounded-xl border border-black/6 object-cover dark:border-white/6"
                        />
                    )}

                    {course.description && (
                        <p className="text-[13px] whitespace-pre-wrap text-gray-600 dark:text-gray-400">
                            {course.description}
                        </p>
                    )}
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
