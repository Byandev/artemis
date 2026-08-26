import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useForm } from '@inertiajs/react';
import { useEffect } from 'react';
import { type Course, type CourseStatus } from '../types';
import CoverImageField from './cover-image-field';

const inputClass =
    'h-10 w-full rounded-[10px] border border-black/8 bg-stone-50 px-3 font-mono! text-[13px]! text-gray-800 placeholder:text-gray-300 outline-none transition-all focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-100 dark:placeholder:text-gray-600 dark:focus:border-emerald-400';
const textareaClass =
    'w-full resize-none rounded-[10px] border border-black/8 bg-stone-50 px-3 py-2.5 font-mono! text-[13px]! text-gray-800 placeholder:text-gray-300 outline-none transition-all focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-100 dark:placeholder:text-gray-600 dark:focus:border-emerald-400';
const labelClass =
    'block font-mono text-[10px] font-medium tracking-wider text-gray-400 uppercase dark:text-gray-500';
const fieldClass = 'space-y-1.5';
const errorClass = 'font-mono text-[11px] text-red-500';

interface Props {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    baseUrl: string;
    /** Null opens the dialog in create mode; a course opens it in edit mode. */
    course: Course | null;
}

interface FormValues {
    name: string;
    description: string;
    category: string;
    cover_image: File | null;
    remove_cover_image: boolean;
    status: CourseStatus;
}

const EMPTY: FormValues = {
    name: '',
    description: '',
    category: '',
    cover_image: null,
    remove_cover_image: false,
    status: 'draft',
};

export default function CourseFormDialog({
    open,
    onOpenChange,
    baseUrl,
    course,
}: Props) {
    const editing = course !== null;

    const {
        data,
        setData,
        post,
        processing,
        progress,
        errors,
        reset,
        clearErrors,
    } = useForm<FormValues>({ ...EMPTY });

    // Reopening the dialog for a different course (or for a fresh create) has
    // to reload the fields — useForm keeps its state across open/close.
    useEffect(() => {
        if (!open) return;
        clearErrors();
        setData(
            course
                ? {
                      ...EMPTY,
                      name: course.name,
                      description: course.description ?? '',
                      category: course.category ?? '',
                      status: course.status,
                  }
                : { ...EMPTY },
        );
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, course?.id]);

    function patch(values: Partial<FormValues>) {
        setData((prev) => ({ ...prev, ...values }));
    }

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();

        // forceFormData so the file input is sent as multipart. An update
        // rides `_method` rather than a real PUT: PHP only populates $_FILES
        // on a POST.
        const url = editing ? `${baseUrl}/${course.id}` : baseUrl;

        post(url, {
            forceFormData: true,
            ...(editing ? { data: { ...data, _method: 'put' } } : {}),
            preserveScroll: true,
            onSuccess: () => {
                reset();
                onOpenChange(false);
            },
        });
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="gap-0 overflow-hidden p-0 sm:max-w-md">
                <div className="border-b border-black/6 px-5 pt-5 pb-4 dark:border-white/6">
                    <DialogHeader>
                        <DialogTitle className="text-[15px] font-semibold text-gray-900 dark:text-gray-100">
                            {editing ? 'Edit Course' : 'New Course'}
                        </DialogTitle>
                        <DialogDescription className="mt-0.5 text-[12px] text-gray-400 dark:text-gray-500">
                            {editing
                                ? course.name
                                : 'Add a course for this workspace.'}
                        </DialogDescription>
                    </DialogHeader>
                </div>

                <form onSubmit={handleSubmit}>
                    <div className="space-y-5 px-5 py-4">
                        <div className={fieldClass}>
                            <label className={labelClass}>
                                Course Name{' '}
                                <span className="text-red-400">*</span>
                            </label>
                            <input
                                type="text"
                                autoFocus
                                className={inputClass}
                                placeholder="e.g. Onboarding 101"
                                value={data.name}
                                onChange={(e) =>
                                    patch({ name: e.target.value })
                                }
                            />
                            {errors.name && (
                                <p className={errorClass}>{errors.name}</p>
                            )}
                        </div>

                        <div className={fieldClass}>
                            <label className={labelClass}>Description</label>
                            <textarea
                                rows={4}
                                className={textareaClass}
                                placeholder="What this course covers (optional)"
                                value={data.description}
                                onChange={(e) =>
                                    patch({ description: e.target.value })
                                }
                            />
                            {errors.description && (
                                <p className={errorClass}>
                                    {errors.description}
                                </p>
                            )}
                        </div>

                        <div className={fieldClass}>
                            <label className={labelClass}>Category</label>
                            <input
                                type="text"
                                className={inputClass}
                                placeholder="e.g. Sales (optional)"
                                value={data.category}
                                onChange={(e) =>
                                    patch({ category: e.target.value })
                                }
                            />
                            {errors.category && (
                                <p className={errorClass}>{errors.category}</p>
                            )}
                        </div>

                        <CoverImageField
                            file={data.cover_image}
                            onFileChange={(f) => patch({ cover_image: f })}
                            removed={data.remove_cover_image}
                            onRemovedChange={(r) =>
                                patch({ remove_cover_image: r })
                            }
                            existing={course?.cover_image ?? null}
                            existingUrl={
                                course?.cover_image
                                    ? `${baseUrl}/${course.id}/media/${course.cover_image.id}`
                                    : undefined
                            }
                            progress={
                                progress ? (progress.percentage ?? 0) : null
                            }
                            error={errors.cover_image}
                        />

                        <div className={fieldClass}>
                            <label className={labelClass}>
                                Status <span className="text-red-400">*</span>
                            </label>
                            <select
                                className={inputClass}
                                value={data.status}
                                onChange={(e) =>
                                    patch({
                                        status: e.target.value as CourseStatus,
                                    })
                                }
                            >
                                <option value="draft">Draft</option>
                                <option value="published">Published</option>
                            </select>
                            {errors.status && (
                                <p className={errorClass}>{errors.status}</p>
                            )}
                        </div>
                    </div>

                    <div className="flex items-center justify-end gap-2 border-t border-black/6 px-5 py-3 dark:border-white/6">
                        <button
                            type="button"
                            onClick={() => onOpenChange(false)}
                            className="flex h-9 items-center rounded-lg border border-black/8 bg-stone-100 px-4 font-mono! text-[12px]! font-medium text-gray-600 transition-all hover:bg-stone-200 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-300 dark:hover:bg-zinc-700"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            disabled={processing}
                            className="flex h-9 items-center rounded-lg bg-emerald-600 px-4 font-mono! text-[12px]! font-medium text-white transition-all hover:bg-emerald-700 disabled:opacity-50"
                        >
                            {processing
                                ? editing
                                    ? 'Saving…'
                                    : 'Creating…'
                                : editing
                                  ? 'Save Changes'
                                  : 'Create Course'}
                        </button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}
