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
    lessons_count?: number;
    duration_seconds?: number;
    /** Average completion across every workspace member. */
    team_percent?: number;
    /** Only loaded on the detail page. */
    modules?: CourseModule[];
}

/** The current user's own run through a course. */
export interface CourseProgress {
    started: boolean;
    started_at: string | null;
    completed_at: string | null;
    resume_lesson_id: number | null;
    completed_lesson_ids: number[];
    completed_count: number;
    total_lessons: number;
    percent: number;
}

export interface CourseStats {
    /** False for a learner: the page is scoped to their own started courses. */
    can_manage: boolean;
    total_courses: number;
    draft_courses: number;
    active_courses: number;
    total_lessons: number;
    /** Lessons the learner has finished across the courses they started. */
    completed_lessons: number;
    /** Team-wide average — managers only. */
    avg_completion: number;
    /** The learner's own progress across the courses they started. */
    my_completion: number;
}

export interface LeaderboardRow {
    id: number;
    name: string;
    percent: number;
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
