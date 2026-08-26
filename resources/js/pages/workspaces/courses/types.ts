export type CourseStatus = 'draft' | 'published';

export interface CourseMedia {
    id: number;
    file_name: string;
    mime_type: string | null;
    size: number;
}

export interface LessonVideo {
    id: number;
    file_name: string;
    size: number;
}

export interface CourseLesson {
    id: number;
    name: string;
    position: number;
    /** Null until a video with readable metadata has been uploaded. */
    duration_seconds: number | null;
    video: LessonVideo | null;
}

export interface CourseModule {
    id: number;
    name: string;
    position: number;
    lessons: CourseLesson[];
}

export interface Course {
    id: number;
    name: string;
    description: string | null;
    category: string | null;
    status: CourseStatus;
    created_at: string | null;
    updated_at: string | null;
    cover_image: CourseMedia | null;
    modules_count?: number;
    /** Only loaded on the detail page. */
    modules?: CourseModule[];
}

export interface CourseWorkspace {
    id: number;
    name: string;
    slug: string;
}

export const STATUS_LABELS: Record<CourseStatus, string> = {
    draft: 'Draft',
    published: 'Published',
};

export const STATUS_STYLES: Record<CourseStatus, string> = {
    published:
        'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
    draft: 'bg-gray-100 text-gray-600 dark:bg-zinc-800 dark:text-gray-400',
};
