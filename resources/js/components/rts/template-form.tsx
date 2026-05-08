import { Workspace } from '@/types/models/Workspace';
import { ParcelJourneyNotificationTemplate } from '@/types/models/ParcelJourneyNotificationTemplate';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from '@/components/ui/dialog';
import { Switch } from '@/components/ui/switch';
import { ChangeEvent, useCallback, useEffect, useState, useMemo, useRef } from 'react';
import { useForm } from '@inertiajs/react';
import workspaces from '@/routes/workspaces';
import { startCase } from 'lodash';

interface Props {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    workspace: Workspace;
    initialValue?: ParcelJourneyNotificationTemplate;
}

interface FormErrors {
    message?: string;
}

const TemplateForm = ({ initialValue, open, onOpenChange, workspace }: Props) => {
    const textareaRef = useRef<HTMLTextAreaElement | null>(null);

    const { data, setData, put, processing } = useForm({
        message: initialValue?.message ?? '',
        is_enabled: initialValue?.is_enabled ?? true,
    });

    useEffect(() => {
        setData({
            message: initialValue?.message ?? '',
            is_enabled: initialValue?.is_enabled ?? true,
        });
    }, [initialValue?.id, initialValue?.message, initialValue?.is_enabled, setData]);

    const [errors, setErrors] = useState<FormErrors>({});

    const validate = () => {
        const tempErrors: FormErrors = {};

        if (!data.message.trim()) {
            tempErrors.message = "Message is required.";
        } else if (data.message.length < 10) {
            tempErrors.message = "Message must be at least 10 characters.";
        }

        setErrors(tempErrors);
        return Object.keys(tempErrors).length === 0;
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();


        if (validate()) {
            put(workspaces.rts.parcelJourneyNotificationTemplates.update.url({
                workspace,
                template: initialValue as ParcelJourneyNotificationTemplate
            }), {
                onSuccess: () => {
                    setErrors({});
                    onOpenChange(false);
                },
            });
        }
    };

    const handleChange = (e: ChangeEvent<HTMLTextAreaElement>) => {
        setData('message', e.target.value);
        if (Object.keys(errors).length > 0) {
            setErrors({});
        }
    };

    const insertAtCursor = useCallback((placeholder: string) => {
        const textarea = textareaRef.current;
        if (!textarea) return;

        const start = textarea.selectionStart;
        const end = textarea.selectionEnd;
        const newText = data.message.slice(0, start) + placeholder + data.message.slice(end);

        setData('message', newText);

        requestAnimationFrame(() => {
            const pos = start + placeholder.length;
            textarea.selectionStart = pos;
            textarea.selectionEnd = pos;
            textarea.focus();
        });
    }, [data.message, setData]);

    const variables = useMemo(() => [
        { display: 'Date', code: '{{date}}', isVisible: true },
        { display: 'Page Name', code: '{{page_name}}', isVisible: true },
        { display: 'Customer Name', code: '{{customer_name}}', isVisible: true },
        { display: 'Tracking Code', code: '{{tracking_code}}', isVisible: true },
        { display: 'Shipping Address', code: '{{shipping_address}}', isVisible: true },
        { display: 'Province', code: '{{province}}', isVisible: true },
        { display: 'City', code: '{{city}}', isVisible: true },
        { display: 'Barangay', code: '{{barangay}}', isVisible: true },
        { display: 'Next Location', code: '{{next_location}}', isVisible: initialValue?.activity === 'departure' },
        { display: 'Current Location', code: '{{current_location}}', isVisible: initialValue?.activity === 'arrival' },
        { display: 'Rider Name', code: '{{rider_name}}', isVisible: initialValue?.activity === 'for-delivery' },
        { display: 'Rider Mobile', code: '{{rider_mobile}}', isVisible: initialValue?.activity === 'for-delivery' },
    ], [initialValue?.activity]);


    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-[580px] p-0 gap-0 overflow-hidden">
                <div className="px-5 pt-5 pb-4 border-b border-black/6 dark:border-white/6">
                    <DialogHeader>
                        <DialogTitle className="text-[15px] font-semibold text-gray-900 dark:text-gray-100">
                            Edit Template
                        </DialogTitle>
                        <DialogDescription className="text-[12px] text-gray-400 dark:text-gray-500 mt-0.5">
                            {initialValue && (
                                <span className="inline-flex items-center gap-1.5">
                                    <span className="inline-flex items-center rounded-full bg-stone-100 px-2 py-0.5 font-mono text-[10px] font-medium text-gray-500 dark:bg-zinc-800 dark:text-gray-400">
                                        {startCase(initialValue.type)}
                                    </span>
                                    <span className="inline-flex items-center rounded-full bg-stone-100 px-2 py-0.5 font-mono text-[10px] font-medium text-gray-500 dark:bg-zinc-800 dark:text-gray-400">
                                        {startCase(initialValue.activity)}
                                    </span>
                                    <span className="inline-flex items-center rounded-full bg-stone-100 px-2 py-0.5 font-mono text-[10px] font-medium text-gray-500 dark:bg-zinc-800 dark:text-gray-400">
                                        {startCase(initialValue.receiver)}
                                    </span>
                                </span>
                            )}
                        </DialogDescription>
                    </DialogHeader>
                </div>

                <form onSubmit={handleSubmit}>
                    <div className="space-y-4 px-5 py-4">
                        <div className="flex items-center justify-between rounded-[10px] border border-black/8 bg-stone-50 px-3 py-2.5 dark:border-white/8 dark:bg-zinc-800">
                            <div>
                                <p className="font-mono text-[11px] font-medium text-gray-700 dark:text-gray-200">
                                    Enabled
                                </p>
                                <p className="font-mono text-[10px] text-gray-400 dark:text-gray-500">
                                    Notifications for this template are only sent when enabled.
                                </p>
                            </div>
                            <Switch
                                checked={data.is_enabled}
                                onCheckedChange={(checked) => setData('is_enabled', checked)}
                            />
                        </div>

                        <div className="space-y-1.5">
                            <label className="block font-mono text-[10px] font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500">
                                Message <span className="text-red-400">*</span>
                            </label>
                            <textarea
                                ref={textareaRef}
                                rows={7}
                                value={data.message}
                                onChange={handleChange}
                                placeholder="Type your message here..."
                                /* Added dynamic border color logic here */
                                className={`w-full rounded-[10px] border px-3 py-2.5 font-mono! text-[12px]! outline-none transition-all resize-none
                                ${errors.message
                                        ? "border-red-500 focus:border-red-500 focus:ring-2 focus:ring-red-500/15 text-red-900 dark:text-red-200"
                                        : "border-black/8 bg-stone-50 text-gray-800 placeholder:text-gray-300 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-100 dark:placeholder:text-gray-600 dark:focus:border-emerald-400"
                                    }`}
                            />

                            {/* Display error message if it exists, otherwise show the hint */}
                            {errors.message ? (
                                <p className="font-mono text-[10px] font-medium text-red-500 animate-in fade-in slide-in-from-top-1">
                                    {errors.message}
                                </p>
                            ) : (
                                <p className="font-mono text-[10px] text-gray-400 dark:text-gray-500">
                                    For SMS, keep the message as short as possible.
                                </p>
                            )}
                        </div>

                        <div className="space-y-1.5">
                            <label className="block font-mono text-[10px] font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500">
                                Insert Variable
                            </label>
                            <div className="flex flex-wrap gap-1.5">
                                {variables.filter(v => v.isVisible).map(variable => (
                                    <button
                                        key={variable.code}
                                        type="button"
                                        onClick={() => insertAtCursor(variable.code)}
                                        className="group inline-flex items-center gap-1 rounded-md border border-violet-200 bg-violet-50 px-2 py-1 transition-all hover:border-violet-400 hover:bg-violet-100 dark:border-violet-500/20 dark:bg-violet-500/10 dark:hover:border-violet-400/40 dark:hover:bg-violet-500/20"
                                    >
                                        <span className="font-mono text-[10px] font-semibold text-violet-400 dark:text-violet-500">{'{ }'}</span>
                                        <span className="font-mono text-[11px] font-medium text-violet-700 dark:text-violet-300">{variable.display}</span>
                                    </button>
                                ))}
                            </div>
                        </div>
                    </div>

                    <div className="flex items-center justify-end gap-2 border-t border-black/6 dark:border-white/6 px-5 py-3">
                        <button
                            type="button"
                            onClick={() => onOpenChange(false)}
                            disabled={processing}
                            className="flex h-9 items-center rounded-lg border border-black/8 bg-stone-100 px-4 font-mono! text-[12px]! font-medium text-gray-600 transition-all hover:bg-stone-200 disabled:opacity-50 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-300 dark:hover:bg-zinc-700"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            disabled={processing}
                            className="flex h-9 items-center rounded-lg bg-emerald-600 px-4 font-mono! text-[12px]! font-medium text-white transition-all hover:bg-emerald-700 disabled:opacity-50"
                        >
                            {processing ? 'Saving…' : 'Save Changes'}
                        </button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
};

export default TemplateForm;
