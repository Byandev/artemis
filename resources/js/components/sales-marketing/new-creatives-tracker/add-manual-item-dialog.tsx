import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useForm } from '@inertiajs/react';
import { Loader2 } from 'lucide-react';
import { useEffect } from 'react';
import { toast } from 'sonner';

export interface ManualItem {
    id: number;
    item_type: 'campaign' | 'ad_set';
    name: string | null;
    account_name: string | null;
    page_name: string | null;
    product: string | null;
    start_date: string | null;
}

interface Props {
    workspaceSlug: string;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** Given to edit an existing manual item; omitted to add a new one. */
    item?: ManualItem | null;
}

const inputClass =
    'h-10 w-full rounded-[10px] border border-black/8 bg-stone-50 px-3 text-[13px] text-gray-800 outline-none transition-all placeholder:text-gray-300 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-100 dark:placeholder:text-gray-600';

const labelClass =
    'block font-mono text-[10px] font-medium tracking-wider text-gray-400 uppercase dark:text-gray-500';

/**
 * Adds a campaign or ad set that the Meta sync does not carry.
 *
 * Everything a synced item reads back through its Meta id has to be typed here
 * instead — including the ad account and page, which are plain names rather
 * than ids because there is nothing to point at.
 */
export default function AddManualItemDialog({
    workspaceSlug,
    open,
    onOpenChange,
    item = null,
}: Props) {
    const isEditing = Boolean(item);

    const { data, setData, post, put, processing, errors, reset } = useForm({
        item_type: 'campaign' as 'campaign' | 'ad_set',
        name: '',
        account_name: '',
        page_name: '',
        start_date: '',
        product_name: '',
    });

    // Load the item being edited, or clear back to blanks for a new one.
    useEffect(() => {
        if (!open) return;

        if (item) {
            setData({
                item_type: item.item_type,
                name: item.name ?? '',
                account_name: item.account_name ?? '',
                page_name: item.page_name ?? '',
                start_date: item.start_date ?? '',
                product_name: item.product ?? '',
            });
        } else {
            reset();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [item, open]);

    const handleOpenChange = (next: boolean) => {
        onOpenChange(next);
        if (!next) reset();
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();

        const base = `/workspaces/${workspaceSlug}/sales-marketing/new-creatives-tracker/items`;

        const options = {
            preserveScroll: true,
            onSuccess: () => {
                toast.success(
                    isEditing ? 'Item updated.' : 'Manual item added.',
                );
                reset();
                onOpenChange(false);
            },
            onError: () => toast.error('Could not save that item.'),
        };

        if (isEditing && item) {
            put(`${base}/${item.id}`, options);
        } else {
            post(`${base}/manual`, options);
        }
    };

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <DialogContent className="gap-0 overflow-hidden p-0 sm:max-w-md">
                <div className="border-b border-black/6 px-5 pt-5 pb-4 dark:border-white/6">
                    <DialogHeader>
                        <DialogTitle className="text-[15px] font-semibold text-gray-900 dark:text-gray-100">
                            {isEditing ? 'Edit Manual Item' : 'Add Manual Item'}
                        </DialogTitle>
                        <DialogDescription className="mt-0.5 text-[12px] text-gray-400 dark:text-gray-500">
                            {isEditing
                                ? 'Moving the start date re-dates the days already recorded, so day 1 stays on the start date.'
                                : 'For a campaign or ad set that is not in the Meta sync. You type its daily figures straight into the table.'}
                        </DialogDescription>
                    </DialogHeader>
                </div>

                <form onSubmit={submit}>
                    <div className="max-h-[65vh] space-y-4 overflow-y-auto px-5 py-4">
                        <div className="space-y-1.5">
                            <label className={labelClass}>Type</label>
                            <div className="flex rounded-[10px] bg-stone-100 p-0.5 dark:bg-zinc-800">
                                {(
                                    [
                                        ['campaign', 'Campaign'],
                                        ['ad_set', 'Ad Set'],
                                    ] as const
                                ).map(([value, label]) => (
                                    <button
                                        key={value}
                                        type="button"
                                        onClick={() =>
                                            setData('item_type', value)
                                        }
                                        className={`flex-1 rounded-[8px] px-3 py-1.5 text-[12px] font-medium transition-colors ${
                                            data.item_type === value
                                                ? 'bg-white text-gray-800 shadow-xs dark:bg-zinc-700 dark:text-gray-100'
                                                : 'text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300'
                                        }`}
                                    >
                                        {label}
                                    </button>
                                ))}
                            </div>
                        </div>

                        <div className="space-y-1.5">
                            <label className={labelClass}>
                                Name <span className="text-red-400">*</span>
                            </label>
                            <input
                                value={data.name}
                                onChange={(e) =>
                                    setData('name', e.target.value)
                                }
                                placeholder="Campaign or ad set name"
                                className={inputClass}
                            />
                            {errors.name && (
                                <p className="text-[11px] text-red-500">
                                    {errors.name}
                                </p>
                            )}
                        </div>

                        <div className="space-y-1.5">
                            <label className={labelClass}>
                                Ad Account Name
                            </label>
                            <input
                                value={data.account_name}
                                onChange={(e) =>
                                    setData('account_name', e.target.value)
                                }
                                placeholder="e.g. Dump Ci"
                                className={inputClass}
                            />
                        </div>

                        {/* A manual row has no page id to resolve, so the page
                            is recorded by name only. */}
                        <div className="space-y-1.5">
                            <label className={labelClass}>Page Name</label>
                            <input
                                value={data.page_name}
                                onChange={(e) =>
                                    setData('page_name', e.target.value)
                                }
                                placeholder="e.g. Pikutin Perfume"
                                className={inputClass}
                            />
                        </div>

                        <div className="space-y-1.5">
                            <label className={labelClass}>
                                Start Date{' '}
                                <span className="text-red-400">*</span>
                            </label>
                            <input
                                type="date"
                                required
                                value={data.start_date}
                                onChange={(e) =>
                                    setData('start_date', e.target.value)
                                }
                                className={inputClass}
                            />
                            {errors.start_date && (
                                <p className="text-[11px] text-red-500">
                                    {errors.start_date}
                                </p>
                            )}
                            <p className="text-[11px] text-gray-400 dark:text-gray-500">
                                Day 1 is counted from here, and each day&apos;s
                                figures are stored against the dates it
                                produces.
                            </p>
                        </div>

                        <div className="space-y-1.5">
                            <label className={labelClass}>Product</label>
                            <input
                                value={data.product_name}
                                onChange={(e) =>
                                    setData('product_name', e.target.value)
                                }
                                placeholder="e.g. Nerve Cream"
                                className={inputClass}
                            />
                        </div>
                    </div>

                    <div className="flex items-center justify-end gap-2 border-t border-black/6 px-5 py-3 dark:border-white/6">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => handleOpenChange(false)}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            size="sm"
                            disabled={
                                processing ||
                                !data.name.trim() ||
                                !data.start_date
                            }
                        >
                            {processing && (
                                <Loader2 className="size-3.5 animate-spin" />
                            )}
                            {isEditing ? 'Save Changes' : 'Add Item'}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}
