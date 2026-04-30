import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { SharedData } from '@/types';
import { useForm, usePage } from '@inertiajs/react';
import { ReactNode, useEffect, useState } from 'react';
import { toast } from 'sonner';

const CATEGORY_OPTIONS = [
    { value: 'question', label: 'Question' },
    { value: 'bug', label: 'Bug' },
    { value: 'feature_request', label: 'Feature request' },
    { value: 'billing', label: 'Billing' },
    { value: 'other', label: 'Other' },
] as const;

interface ContactSupportModalProps {
    trigger: ReactNode;
}

export function ContactSupportModal({ trigger }: ContactSupportModalProps) {
    const { currentWorkspace } = usePage<SharedData>().props as unknown as {
        currentWorkspace?: { slug: string };
    };
    const [open, setOpen] = useState(false);

    const form = useForm({
        category: '',
        subject: '',
        description: '',
        current_url: '',
        user_agent: '',
    });

    useEffect(() => {
        if (!open || typeof window === 'undefined') return;

        form.setData('current_url', window.location.href);
        form.setData('user_agent', window.navigator.userAgent);
    }, [open]);

    const handleSubmit = (event: React.FormEvent) => {
        event.preventDefault();

        if (!currentWorkspace?.slug) return;

        form.post(`/workspaces/${currentWorkspace.slug}/support`, {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Support request sent.');
                form.reset('category', 'subject', 'description');
                setOpen(false);
            },
            onError: () => {
                toast.error('Unable to submit the request.');
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent className="sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>Contact Support</DialogTitle>
                    <DialogDescription>
                        Send a ticket to the workspace support queue. We will follow up as soon as possible.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-4">
                    <div className="space-y-2">
                        <Label htmlFor="support-category">Category</Label>
                        <Select
                            value={form.data.category}
                            onValueChange={(value) => form.setData('category', value)}
                        >
                            <SelectTrigger id="support-category">
                                <SelectValue placeholder="Select a category" />
                            </SelectTrigger>
                            <SelectContent>
                                {CATEGORY_OPTIONS.map((option) => (
                                    <SelectItem key={option.value} value={option.value}>
                                        {option.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={form.errors.category} />
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="support-subject">Subject</Label>
                        <Input
                            id="support-subject"
                            value={form.data.subject}
                            onChange={(event) => form.setData('subject', event.target.value)}
                            placeholder="Short summary"
                        />
                        <InputError message={form.errors.subject} />
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="support-description">Description</Label>
                        <Textarea
                            id="support-description"
                            value={form.data.description}
                            onChange={(event) => form.setData('description', event.target.value)}
                            placeholder="Tell us what you need help with"
                            rows={6}
                        />
                        <InputError message={form.errors.description} />
                    </div>

                    <DialogFooter className="gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setOpen(false)}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={form.processing || !currentWorkspace?.slug}>
                            {form.processing ? 'Sending...' : 'Submit ticket'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
