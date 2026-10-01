import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useForm } from '@inertiajs/react';
import { useEffect } from 'react';
import {
    BTN_PRIMARY,
    BTN_SECONDARY,
    FIELD,
    FIELD_ERROR,
    FIELD_LABEL,
    INPUT,
    LABEL,
    MUTED,
    SECTION_BORDER,
} from '../../lib/ui';
import { type ParentOption, type TargetMarketEntry } from '../types';

interface Props {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    baseUrl: string;
    /** Every category, for the "Add under" picker. */
    parents: ParentOption[];
    /**
     * Null opens the dialog on a new entry; a row opens it to rename that row.
     */
    editing: TargetMarketEntry | null;
    /**
     * Which parent a new entry starts on — the id of the category whose `+`
     * was pressed, or null for the header's "Add Entry". Ignored when editing.
     */
    defaultParentId: number | null;
}

interface FormValues {
    /** '' is the "Top level — new category" option. */
    parent_id: string;
    name: string;
}

export default function TargetMarketEntryDialog({
    open,
    onOpenChange,
    baseUrl,
    parents,
    editing,
    defaultParentId,
}: Props) {
    const isEditing = editing !== null;

    const { data, setData, post, put, processing, errors, reset, clearErrors } =
        useForm<FormValues>({ parent_id: '', name: '' });

    // Reopening for a different row (or for a fresh entry) has to reload the
    // fields — useForm keeps its state across open/close.
    useEffect(() => {
        if (!open) return;
        clearErrors();
        setData({
            parent_id: editing
                ? (editing.parent_id?.toString() ?? '')
                : (defaultParentId?.toString() ?? ''),
            name: editing ? editing.name : '',
        });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, editing?.id, defaultParentId]);

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();

        const options = {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                onOpenChange(false);
            },
        };

        // Editing is a rename: where a row sits is fixed once it exists, so
        // only the name goes up.
        if (isEditing) {
            put(`${baseUrl}/${editing.id}`, options);
            return;
        }

        post(baseUrl, options);
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="gap-0 overflow-hidden p-0 sm:max-w-md">
                <div className={`border-b ${SECTION_BORDER} px-5 pt-5 pb-4`}>
                    <p className={LABEL}>Target Market</p>
                    <DialogHeader className="mt-1 gap-0">
                        <DialogTitle className="text-[17px] font-semibold text-gray-900 dark:text-gray-100">
                            {isEditing
                                ? 'Rename target market entry'
                                : 'New target market entry'}
                        </DialogTitle>
                        <DialogDescription className={`mt-1 ${MUTED}`}>
                            {isEditing
                                ? 'Where it sits stays as it is — only the name changes.'
                                : 'Pick where it belongs, then name it.'}
                        </DialogDescription>
                    </DialogHeader>
                </div>

                <form onSubmit={handleSubmit}>
                    <div className="space-y-5 px-5 py-4">
                        {!isEditing && (
                            <div className={FIELD}>
                                <label className={FIELD_LABEL}>Add under</label>
                                <select
                                    className={INPUT}
                                    value={data.parent_id}
                                    onChange={(e) =>
                                        setData('parent_id', e.target.value)
                                    }
                                >
                                    <option value="">
                                        Top level — new category
                                    </option>
                                    {parents.map((parent) => (
                                        <option
                                            key={parent.id}
                                            value={parent.id}
                                        >
                                            {parent.name}
                                        </option>
                                    ))}
                                </select>
                                {errors.parent_id && (
                                    <p className={FIELD_ERROR}>
                                        {errors.parent_id}
                                    </p>
                                )}
                            </div>
                        )}

                        <div className={FIELD}>
                            <label className={FIELD_LABEL}>Name</label>
                            <input
                                type="text"
                                autoFocus
                                className={INPUT}
                                placeholder="e.g. Hypertension"
                                value={data.name}
                                onChange={(e) =>
                                    setData('name', e.target.value)
                                }
                            />
                            {errors.name && (
                                <p className={FIELD_ERROR}>{errors.name}</p>
                            )}
                        </div>
                    </div>

                    <div
                        className={`flex items-center justify-end gap-2 border-t ${SECTION_BORDER} px-5 py-4`}
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
                            {processing
                                ? 'Saving…'
                                : isEditing
                                  ? 'Save name'
                                  : 'Add entry'}
                        </button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}
