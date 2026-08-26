import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useForm } from '@inertiajs/react';
import { Check, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import {
    BTN_PRIMARY,
    BTN_SECONDARY,
    FIELD,
    FIELD_ERROR,
    INPUT,
    LABEL,
    SECTION_BORDER,
    TEXTAREA,
} from '../lib/ui';
import { type Course, type CourseStatus } from '../types';
import CoverImageField from './cover-image-field';

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
    /** Bucket key from the presigned upload — the usual path. */
    cover_image_key: string | null;
    /** Only sent when the disk could not sign an upload. */
    cover_image: File | null;
    remove_cover_image: boolean;
    status: CourseStatus;
}

const EMPTY: FormValues = {
    name: '',
    description: '',
    category: '',
    cover_image_key: null,
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

    const { data, setData, post, processing, errors, reset, clearErrors } =
        useForm<FormValues>({ ...EMPTY });

    // The cover goes to the bucket as soon as it is picked, so submitting has
    // to wait for it or the key would still be null.
    const [uploadingCover, setUploadingCover] = useState(false);

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

        // Only a fallback upload still needs multipart; the usual path sends a
        // key and can go as plain JSON.
        post(url, {
            forceFormData: data.cover_image !== null,
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
                <div className={`border-b ${SECTION_BORDER} px-5 pt-5 pb-4`}>
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
                        <div className={FIELD}>
                            <label className={`block ${LABEL}`}>
                                Course Name{' '}
                                <span className="text-red-400">*</span>
                            </label>
                            <input
                                type="text"
                                autoFocus
                                className={INPUT}
                                placeholder="e.g. Onboarding 101"
                                value={data.name}
                                onChange={(e) =>
                                    patch({ name: e.target.value })
                                }
                            />
                            {errors.name && (
                                <p className={FIELD_ERROR}>{errors.name}</p>
                            )}
                        </div>

                        <div className={FIELD}>
                            <label className={`block ${LABEL}`}>
                                Description
                            </label>
                            <textarea
                                rows={4}
                                className={TEXTAREA}
                                placeholder="What this course covers (optional)"
                                value={data.description}
                                onChange={(e) =>
                                    patch({ description: e.target.value })
                                }
                            />
                            {errors.description && (
                                <p className={FIELD_ERROR}>
                                    {errors.description}
                                </p>
                            )}
                        </div>

                        <div className={FIELD}>
                            <label className={`block ${LABEL}`}>Category</label>
                            <input
                                type="text"
                                className={INPUT}
                                placeholder="e.g. Sales (optional)"
                                value={data.category}
                                onChange={(e) =>
                                    patch({ category: e.target.value })
                                }
                            />
                            {errors.category && (
                                <p className={FIELD_ERROR}>{errors.category}</p>
                            )}
                        </div>

                        <CoverImageField
                            presignUrl={`${baseUrl}/cover/presign`}
                            file={data.cover_image}
                            onFileChange={(f) => patch({ cover_image: f })}
                            onKeyChange={(k) => patch({ cover_image_key: k })}
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
                            onUploadingChange={setUploadingCover}
                            error={errors.cover_image ?? errors.cover_image_key}
                        />

                        <div className={FIELD}>
                            <label className={`block ${LABEL}`}>
                                Status <span className="text-red-400">*</span>
                            </label>
                            <select
                                className={INPUT}
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
                                <p className={FIELD_ERROR}>{errors.status}</p>
                            )}
                        </div>
                    </div>

                    <div
                        className={`flex items-center justify-end gap-2 border-t ${SECTION_BORDER} px-5 py-3`}
                    >
                        <button
                            type="button"
                            onClick={() => onOpenChange(false)}
                            className={BTN_SECONDARY}
                        >
                            <X className="h-4 w-4" />
                            Cancel
                        </button>
                        <button
                            type="submit"
                            disabled={processing || uploadingCover}
                            className={BTN_PRIMARY}
                        >
                            <Check className="h-4 w-4" />
                            {uploadingCover
                                ? 'Uploading…'
                                : processing
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
