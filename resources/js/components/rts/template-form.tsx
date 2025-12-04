import { Workspace } from '@/types/models/Workspace';
import { ParcelJourneyNotificationTemplate } from '@/types/models/ParcelJourneyNotificationTemplate';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

import { ChangeEvent, useCallback, useEffect, useMemo, useRef } from 'react';
import { useForm } from '@inertiajs/react';
import { Textarea } from '@/components/ui/textarea';
import { Button } from '@/components/ui/button';
import workspaces from '@/routes/workspaces';

interface Props {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    workspace: Workspace;
    initialValue?: ParcelJourneyNotificationTemplate;
}

const TemplateForm  = ({initialValue, open, onOpenChange, workspace}: Props) => {
    const textareaRef = useRef<HTMLTextAreaElement | null>(null);

    const { data, setData, put, processing } = useForm({
        message: initialValue?.message ?? ""
    });

    useEffect(() => {
        setData('message', initialValue?.message as string)
    }, [initialValue?.message, setData]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        put(workspaces.rts.parcelJourneyNotificationTemplates.update.url({ workspace, template: initialValue as ParcelJourneyNotificationTemplate }), {
            onSuccess: () => onOpenChange(false)
        })
    };

    const handleChange = (e: ChangeEvent<HTMLTextAreaElement>) => {
        setData('message', e.target.value)
    };

    const insertAtCursor =  useCallback((placeholder: string) => {
        const textarea = textareaRef.current;
        if (!textarea) return;

        const start = textarea.selectionStart;
        const end = textarea.selectionEnd;

        // Insert placeholder at cursor
        const newText =
            data.message.slice(0, start) + placeholder + data.message.slice(end);

        setData('message', newText);

        // Put cursor after the inserted placeholder
        requestAnimationFrame(() => {
            const pos = start + placeholder.length;
            textarea.selectionStart = pos;
            textarea.selectionEnd = pos;
            textarea.focus();
        });
    }, [data.message, setData])

    const variables = useMemo(() => {
        return [
            { display: 'Date', code: '{{date}}', isVisible: true },
            { display: 'Page Name', code: '{{page_name}}', isVisible: true },
            { display: 'Customer Name', code: '{{customer_name}}', isVisible: true },
            { display: 'Tracking Code', code: '{{tracking_code}}', isVisible: true },
            { display: 'Shipping Address', code: '{{shipping_address}}', isVisible: true },
            { display: 'Next Location', code: '{{next_location}}', isVisible: initialValue?.activity === 'departure' },
            { display: 'Current Location', code: '{{current_location}}', isVisible: initialValue?.activity === 'arrival' },
            { display: 'Rider Name', code: '{{rider_name}}', isVisible: initialValue?.activity === 'for-delivery' },
            { display: 'Rider Mobile', code: '{{rider_mobile}}', isVisible: initialValue?.activity === 'for-delivery' },
        ]

    }, [initialValue?.activity])

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-[600px]">
                <form onSubmit={handleSubmit}>
                    <DialogHeader>
                        <DialogTitle>Edit Parcel Journey Notification Template</DialogTitle>
                        <DialogDescription>
                            For SMS, the shorter the message the better
                        </DialogDescription>
                    </DialogHeader>


                    <div className="my-5">
                        <Textarea
                            ref={textareaRef}
                            rows={6}
                            value={data.message}
                            onChange={handleChange}
                            placeholder="Type your message here..."
                            className="text-gray-700 text-xs font-normal"
                        />

                        <div className="flex flex-wrap mt-4">
                            {variables.map(variable =>
                                <div onClick={() => insertAtCursor(variable.code)} className="bg-gray-500 text-white m-1 py-1 px-2 text-xs font-light rounded-lg cursor-pointer" key={variable.code}>
                                {variable.display}
                            </div>)
                            }
                        </div>
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                            disabled={processing}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {processing ? 'Saving...' : 'Save'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default TemplateForm
