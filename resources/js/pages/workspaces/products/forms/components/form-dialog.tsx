import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useForm } from '@inertiajs/react';
import { Plus, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import {
    ADD_ROW,
    BTN_PRIMARY,
    BTN_SECONDARY,
    FIELD,
    FIELD_ERROR,
    INPUT,
    LABEL,
    SECTION_BORDER,
} from '../../lib/ui';
import { type ProductForm, type VariantImage } from '../types';
import VariantImageField from './variant-image-field';

interface Props {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    baseUrl: string;
    /** Null opens the dialog in create mode; a form opens it in edit mode. */
    form: ProductForm | null;
}

interface VariantRow {
    /**
     * Stable across re-orders and removals, which the array index is not — two
     * rows would otherwise swap their staged files when one above is deleted.
     * Client-side only; never submitted.
     */
    key: string;
    /** The saved row's id, or null for one being added. */
    id: number | null;
    name: string;
    image: File | null;
    remove_image: boolean;
    existing: VariantImage | null;
}

interface FormValues {
    name: string;
    variants: VariantRow[];
}

let rowSeq = 0;

function blankRow(): VariantRow {
    rowSeq += 1;
    return {
        key: `new-${rowSeq}`,
        id: null,
        name: '',
        image: null,
        remove_image: false,
        existing: null,
    };
}

const EMPTY: FormValues = { name: '', variants: [] };

export default function ProductFormDialog({
    open,
    onOpenChange,
    baseUrl,
    form,
}: Props) {
    const editing = form !== null;

    const {
        data,
        setData,
        post,
        transform,
        processing,
        errors,
        reset,
        clearErrors,
    } = useForm<FormValues>({ ...EMPTY });

    const [submitError, setSubmitError] = useState<string | null>(null);

    // Reopening the dialog for a different form (or for a fresh create) has to
    // reload the fields — useForm keeps its state across open/close.
    useEffect(() => {
        if (!open) return;
        clearErrors();
        setSubmitError(null);
        setData(
            form
                ? {
                      name: form.name,
                      variants: form.variants.map((variant) => ({
                          key: `saved-${variant.id}`,
                          id: variant.id,
                          name: variant.name,
                          image: null,
                          remove_image: false,
                          existing: variant.image,
                      })),
                  }
                : { ...EMPTY, variants: [blankRow()] },
        );
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, form?.id]);

    function patchRow(key: string, values: Partial<VariantRow>) {
        setData((prev) => ({
            ...prev,
            variants: prev.variants.map((row) =>
                row.key === key ? { ...row, ...values } : row,
            ),
        }));
    }

    function addRow() {
        setData((prev) => ({
            ...prev,
            variants: [...prev.variants, blankRow()],
        }));
    }

    function removeRow(key: string) {
        setData((prev) => ({
            ...prev,
            variants: prev.variants.filter((row) => row.key !== key),
        }));
    }

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        setSubmitError(null);

        // An update rides `_method` rather than a real PUT: PHP only populates
        // $_FILES on a POST.
        //
        // transform() is what actually reaches the request — passing data in
        // the visit options does nothing, since useForm hands its own data to
        // router.post as the data argument. Set on every submit rather than
        // only when editing: the dialog is reused for create, and a transform
        // left over from a previous edit would spoof PUT on a store.
        transform((values) => ({
            ...(editing ? { _method: 'put' } : {}),
            name: values.name,
            // `key` and `existing` are UI bookkeeping; the server reconciles on
            // `id` alone. A file is only sent when one was picked, so a row
            // left alone keeps whatever it already has.
            variants: values.variants.map((row) => ({
                ...(row.id !== null ? { id: row.id } : {}),
                name: row.name,
                ...(row.image ? { image: row.image } : {}),
                remove_image: row.remove_image,
            })),
        }));

        post(editing ? `${baseUrl}/${form.id}` : baseUrl, {
            // Always multipart: nested `variants[n][image]` files only survive
            // as form data, and an update needs the spoofed method to ride a
            // POST body anyway.
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                reset();
                onOpenChange(false);
            },
            onError: (bag) => {
                // Per-field messages render inline; this catches the rest.
                if (!Object.keys(bag).length) {
                    setSubmitError('The form could not be saved.');
                }
            },
        });
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            {/*
                `flex` replaces DialogContent's own `grid` so the three bands
                below can size themselves: a header and footer that hold their
                height, and a middle that takes the rest and scrolls. Without
                it a form with a dozen sizes grows the dialog past the viewport
                and pushes Save out of reach.
            */}
            <DialogContent className="flex max-h-[85vh] flex-col gap-0 overflow-hidden p-0 sm:max-w-xl">
                <div
                    className={`shrink-0 border-b ${SECTION_BORDER} px-5 pt-5 pb-4`}
                >
                    <p className={LABEL}>Product Forms</p>
                    <DialogHeader className="mt-1 gap-0">
                        <DialogTitle className="text-[17px] font-semibold text-gray-900 dark:text-gray-100">
                            {editing ? 'Edit form' : 'New form'}
                        </DialogTitle>
                        <DialogDescription className="mt-1 text-[12px] text-gray-400 dark:text-gray-500">
                            Name the form, then add a picture for every size or
                            variant it ships in.
                        </DialogDescription>
                    </DialogHeader>
                </div>

                <form
                    onSubmit={handleSubmit}
                    className="flex min-h-0 flex-1 flex-col"
                >
                    {/* min-h-0 so this band may shrink below its content and
                        scroll, rather than forcing the dialog to grow. */}
                    <div className="min-h-0 flex-1 space-y-5 overflow-y-auto px-5 py-4">
                        <div className={FIELD}>
                            <label className="block text-[13px] font-medium text-gray-700 dark:text-gray-200">
                                Form name
                            </label>
                            <input
                                type="text"
                                autoFocus
                                className={INPUT}
                                placeholder="e.g. Tincture"
                                value={data.name}
                                onChange={(e) =>
                                    setData('name', e.target.value)
                                }
                            />
                            {errors.name && (
                                <p className={FIELD_ERROR}>{errors.name}</p>
                            )}
                        </div>

                        <div className="space-y-3">
                            <div className="flex items-start justify-between gap-3">
                                <div>
                                    <p className="text-[13px] font-medium text-gray-700 dark:text-gray-200">
                                        Size / Variants
                                    </p>
                                    <p className="mt-0.5 text-[12px] text-gray-400 dark:text-gray-500">
                                        Each size or variant carries its own
                                        picture.
                                    </p>
                                </div>
                                <button
                                    type="button"
                                    onClick={addRow}
                                    className={ADD_ROW}
                                >
                                    <Plus className="h-3.5 w-3.5" />
                                    Add size
                                </button>
                            </div>

                            {data.variants.length === 0 ? (
                                <p className="rounded-[10px] border border-dashed border-black/12 bg-stone-50 px-3 py-6 text-center text-[12px] text-gray-400 dark:border-white/12 dark:bg-zinc-800 dark:text-gray-500">
                                    No sizes yet. Add one to give this form a
                                    picture.
                                </p>
                            ) : (
                                <div className="space-y-2.5">
                                    {data.variants.map((row, index) => (
                                        <div
                                            key={row.key}
                                            className="flex items-start gap-3 rounded-[12px] border border-black/6 bg-stone-50/70 p-3 dark:border-white/6 dark:bg-zinc-800/50"
                                        >
                                            <VariantImageField
                                                file={row.image}
                                                onFileChange={(file) =>
                                                    patchRow(row.key, {
                                                        image: file,
                                                    })
                                                }
                                                existing={row.existing}
                                                removed={row.remove_image}
                                                onRemovedChange={(removed) =>
                                                    patchRow(row.key, {
                                                        remove_image: removed,
                                                    })
                                                }
                                                error={
                                                    errors[
                                                        `variants.${index}.image` as keyof typeof errors
                                                    ]
                                                }
                                            />

                                            <div className="min-w-0 flex-1 space-y-1.5 pt-1">
                                                <label className={LABEL}>
                                                    Size / Variant {index + 1}
                                                </label>
                                                <input
                                                    type="text"
                                                    className={INPUT}
                                                    placeholder="e.g. 30ml"
                                                    value={row.name}
                                                    onChange={(e) =>
                                                        patchRow(row.key, {
                                                            name: e.target
                                                                .value,
                                                        })
                                                    }
                                                />
                                                {errors[
                                                    `variants.${index}.name` as keyof typeof errors
                                                ] && (
                                                    <p className={FIELD_ERROR}>
                                                        {
                                                            errors[
                                                                `variants.${index}.name` as keyof typeof errors
                                                            ]
                                                        }
                                                    </p>
                                                )}
                                            </div>

                                            <button
                                                type="button"
                                                onClick={() =>
                                                    removeRow(row.key)
                                                }
                                                aria-label={`Remove size ${index + 1}`}
                                                className="mt-1 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border border-black/8 bg-white text-gray-400 transition-all hover:text-gray-700 dark:border-white/8 dark:bg-zinc-900 dark:hover:text-gray-200"
                                            >
                                                <X className="h-4 w-4" />
                                            </button>
                                        </div>
                                    ))}
                                </div>
                            )}

                            {errors.variants && (
                                <p className={FIELD_ERROR}>{errors.variants}</p>
                            )}
                        </div>

                        {submitError && (
                            <p className={FIELD_ERROR}>{submitError}</p>
                        )}
                    </div>

                    <div
                        className={`flex shrink-0 items-center justify-end gap-2 border-t ${SECTION_BORDER} bg-white px-5 py-4 dark:bg-zinc-900`}
                    >
                        <button
                            type="button"
                            onClick={() => onOpenChange(false)}
                            className={BTN_SECONDARY}
                            disabled={processing}
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            className={BTN_PRIMARY}
                            disabled={processing}
                        >
                            {processing ? 'Saving…' : 'Save form'}
                        </button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}
