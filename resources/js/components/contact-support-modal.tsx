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
import support from '@/routes/support';
import { SharedData } from '@/types';
import { useForm, usePage } from '@inertiajs/react';
import { LifeBuoy } from 'lucide-react';
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

        form.post(support.store.url({ workspace: currentWorkspace.slug }), {
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
                        Send a ticket to the workspace support queue. We will
                        follow up as soon as possible.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-4">
                    <div className="space-y-2">
                        <Label htmlFor="support-category">Category</Label>
                        <Select
                            value={form.data.category}
                            onValueChange={(value) =>
                                form.setData('category', value)
                            }
                        >
                            <SelectTrigger id="support-category">
                                <SelectValue placeholder="Select a category" />
                            </SelectTrigger>
                            <SelectContent>
                                {CATEGORY_OPTIONS.map((option) => (
                                    <SelectItem
                                        key={option.value}
                                        value={option.value}
                                    >
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
                            onChange={(event) =>
                                form.setData('subject', event.target.value)
                            }
                            placeholder="Short summary"
                        />
                        <InputError message={form.errors.subject} />
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="support-description">Description</Label>
                        <Textarea
                            id="support-description"
                            value={form.data.description}
                            onChange={(event) =>
                                form.setData('description', event.target.value)
                            }
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
                        <Button
                            type="submit"
                            disabled={
                                form.processing || !currentWorkspace?.slug
                            }
                        >
                            {form.processing ? 'Sending...' : 'Submit ticket'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export function ContactSupportSideTab() {
    return (
        <ContactSupportModal
            trigger={
                <Button
                    type="button"
                    variant="ghost"
                    className="group fixed right-0 bottom-5 z-50 h-10 w-10 justify-start overflow-hidden rounded-l-[10px] rounded-r-none border border-r-0 border-emerald-500/20 bg-white/95 px-0 text-gray-600 shadow-[0_4px_18px_rgba(0,0,0,0.10)] backdrop-blur transition-[width,border-color,background-color,color] duration-200 hover:w-32 hover:border-emerald-500/35 hover:bg-white hover:text-emerald-700 focus-visible:w-32 focus-visible:border-emerald-500/40 focus-visible:ring-2 focus-visible:ring-emerald-500/15 focus-visible:ring-offset-2 dark:border-emerald-400/20 dark:bg-zinc-900/95 dark:text-gray-300 dark:shadow-[0_4px_18px_rgba(0,0,0,0.35)] dark:hover:border-emerald-400/35 dark:hover:bg-zinc-900 dark:hover:text-emerald-300"
                    aria-label="Contact support"
                    title="Contact support"
                >
                    <span className="flex min-w-32 items-center gap-2 pl-2">
                        <span className="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-emerald-500/10 text-emerald-600 ring-1 ring-emerald-500/20 transition-colors group-hover:bg-emerald-500 group-hover:text-white dark:bg-emerald-400/10 dark:text-emerald-300 dark:ring-emerald-400/20 dark:group-hover:bg-emerald-400 dark:group-hover:text-zinc-950">
                            <LifeBuoy className="h-3.5 w-3.5" />
                        </span>
                        <span className="whitespace-nowrap font-mono! text-[11px]! font-medium opacity-0 transition-opacity duration-150 group-hover:opacity-100 group-focus-visible:opacity-100">
                            Support
                        </span>
                    </span>
                </Button>
            }
        />
    );
}
