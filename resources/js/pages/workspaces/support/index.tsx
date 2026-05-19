import PageHeader from '@/components/common/PageHeader';
import InputError from '@/components/input-error';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DataTable, SortableHeader } from '@/components/ui/data-table';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectLabel,
    SelectSeparator,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useUserPermissions } from '@/hooks/use-permission';
import AppLayout from '@/layouts/app-layout';
import { toFrontendSort } from '@/lib/sort';
import {
    getSupportDestinations,
    type SupportDestination,
} from '@/lib/workspace-navigation';
import { PaginatedData, SharedData } from '@/types';
import { SupportTicket } from '@/types/models/SupportTicket';
import { Workspace } from '@/types/models/Workspace';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import { omit } from 'lodash';
import { LifeBuoy, MoreHorizontal, Pencil, Trash2 } from 'lucide-react';
import { FormEvent, useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';

interface Props {
    workspace: Workspace;
    tickets: PaginatedData<SupportTicket>;
    query?: {
        sort?: string | null;
        per_page?: number | string;
        page?: number | string;
    };
}

const STATUS_STYLES: Record<SupportTicket['status'], string> = {
    open: 'border-emerald-200/60 bg-emerald-50 text-emerald-700',
    in_progress: 'border-amber-200/60 bg-amber-50 text-amber-700',
    resolved: 'border-sky-200/60 bg-sky-50 text-sky-700',
    closed: 'border-stone-200/60 bg-stone-100 text-stone-700',
};

const CATEGORY_LABELS: Record<SupportTicket['category'], string> = {
    question: 'Question',
    bug: 'Bug',
    feature_request: 'Feature request',
    billing: 'Billing',
    other: 'Other',
};

type TicketFormData = {
    category: SupportTicket['category'];
    subject: string;
    description: string;
    current_url?: string;
    user_agent?: string;
};

export default function SupportTicketsIndex({
    workspace,
    tickets,
    query,
}: Props) {
    const { auth } = usePage<SharedData>().props;
    const permissions = useUserPermissions();
    const initialSorting = useMemo(
        () => toFrontendSort(query?.sort ?? null),
        [query?.sort],
    );
    const [editingTicket, setEditingTicket] = useState<SupportTicket | null>(
        null,
    );
    const [deletingTicket, setDeletingTicket] = useState<SupportTicket | null>(
        null,
    );
    const [createDialogOpen, setCreateDialogOpen] = useState(false);
    const [createProcessing, setCreateProcessing] = useState(false);
    const [selectedPageValue, setSelectedPageValue] = useState('general');
    const createForm = useForm<TicketFormData>({
        category: 'question',
        subject: '',
        description: '',
        current_url: '',
        user_agent: '',
    });
    const editForm = useForm<TicketFormData>({
        category: 'question',
        subject: '',
        description: '',
    });

    const openEditDialog = (ticket: SupportTicket) => {
        editForm.setData({
            category: ticket.category,
            subject: ticket.subject,
            description: ticket.description,
        });
        editForm.clearErrors();
        setEditingTicket(ticket);
    };

    useEffect(() => {
        if (!createDialogOpen || typeof window === 'undefined') return;

        createForm.setData('user_agent', window.navigator.userAgent);
    }, [createDialogOpen]);

    const supportTicketUrl = (ticket: SupportTicket) =>
        `/workspaces/${workspace.slug}/support/${ticket.id}`;

    const supportDestinations = useMemo(
        () =>
            getSupportDestinations(
                workspace,
                permissions,
                auth.user.can?.viewAnySupportTickets,
            ),
        [auth.user.can?.viewAnySupportTickets, permissions, workspace],
    );

    const groupedDestinations = useMemo(
        () =>
            supportDestinations.reduce<Record<string, SupportDestination[]>>(
                (groups, destination) => {
                    groups[destination.group] = [
                        ...(groups[destination.group] ?? []),
                        destination,
                    ];

                    return groups;
                },
                {},
            ),
        [supportDestinations],
    );

    const selectedDestination = useMemo(
        () =>
            supportDestinations.find(
                (destination) => destination.value === selectedPageValue,
            ),
        [selectedPageValue, supportDestinations],
    );

    const selectedPageUrl = useMemo(() => {
        if (!selectedDestination) return 'In General';

        if (selectedDestination.value === 'general') {
            return 'In General';
        }

        if (/^https?:\/\//i.test(selectedDestination.value)) {
            return selectedDestination.value;
        }

        return typeof window === 'undefined'
            ? selectedDestination.value
            : new URL(selectedDestination.value, window.location.origin).href;
    }, [selectedDestination]);

    const selectedPageLabel = selectedDestination?.label ?? 'In General';

    useEffect(() => {
        if (
            !supportDestinations.some(
                (destination) => destination.value === selectedPageValue,
            )
        ) {
            setSelectedPageValue('general');
        }
    }, [selectedPageValue, supportDestinations]);

    const formatDestinationLabel = (destination: SupportDestination) => {
        if (destination.group === 'General') {
            return destination.label;
        }

        return `${destination.group} - ${destination.label}`;
    };

    const pageDescription =
        selectedPageValue === 'general'
            ? 'Use this for general feedback, suggestions, or system-wide concerns.'
            : `This ticket will reference ${selectedPageLabel}.`;

    const renderDestinationGroup = (
        group: string,
        destinations: SupportDestination[],
        index: number,
    ) => (
        <SelectGroup key={group}>
            {index > 0 && <SelectSeparator />}
            <SelectLabel className="font-mono text-[10px] tracking-wider text-gray-400 uppercase">
                {group}
            </SelectLabel>
            {destinations.map((destination) => (
                <SelectItem key={destination.value} value={destination.value}>
                    {formatDestinationLabel(destination)}
                </SelectItem>
            ))}
        </SelectGroup>
    );

    const handleCreateSubmit = (event: FormEvent) => {
        event.preventDefault();

        createForm.setData({
            ...createForm.data,
            current_url: selectedPageUrl,
            user_agent:
                typeof window === 'undefined'
                    ? createForm.data.user_agent
                    : window.navigator.userAgent,
        });

        router.post(
            `/workspaces/${workspace.slug}/support`,
            {
                ...createForm.data,
                current_url: selectedPageUrl,
                user_agent:
                    typeof window === 'undefined'
                        ? createForm.data.user_agent
                        : window.navigator.userAgent,
            },
            {
                preserveScroll: true,
                onStart: () => {
                    setCreateProcessing(true);
                    createForm.clearErrors();
                },
                onSuccess: () => {
                    toast.success('Support ticket submitted.');
                    createForm.reset(
                        'category',
                        'subject',
                        'description',
                        'current_url',
                        'user_agent',
                    );
                    setSelectedPageValue('general');
                    setCreateDialogOpen(false);
                },
                onError: (errors) => {
                    createForm.setError(errors);
                    toast.error('Unable to submit support ticket.');
                },
                onFinish: () => setCreateProcessing(false),
            },
        );
    };

    const handleEditSubmit = (event: FormEvent) => {
        event.preventDefault();

        if (!editingTicket) return;

        editForm.patch(supportTicketUrl(editingTicket), {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Support ticket updated.');
                setEditingTicket(null);
            },
            onError: () => {
                toast.error('Unable to update support ticket.');
            },
        });
    };

    const handleDelete = () => {
        if (!deletingTicket) return;

        router.delete(supportTicketUrl(deletingTicket), {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Support ticket deleted.');
                setDeletingTicket(null);
            },
            onError: () => {
                toast.error('Unable to delete support ticket.');
            },
        });
    };

    const columns: ColumnDef<SupportTicket>[] = [
        {
            accessorKey: 'reference',
            header: ({ column }) => (
                <SortableHeader column={column} title="Reference" />
            ),
            cell: ({ row }) => (
                <span className="font-mono text-[11px] text-gray-500">
                    {row.original.reference}
                </span>
            ),
        },
        {
            accessorKey: 'subject',
            header: ({ column }) => (
                <SortableHeader column={column} title="Subject" />
            ),
            cell: ({ row }) => (
                <div className="space-y-1">
                    <p className="text-[12px] font-medium text-gray-800 dark:text-gray-100">
                        {row.original.subject}
                    </p>
                    <p className="font-mono text-[11px] text-gray-400">
                        {CATEGORY_LABELS[row.original.category]}
                    </p>
                </div>
            ),
        },
        {
            accessorKey: 'status',
            header: ({ column }) => (
                <SortableHeader column={column} title="Status" />
            ),
            cell: ({ row }) => (
                <Badge className={STATUS_STYLES[row.original.status]}>
                    {row.original.status.replace('_', ' ')}
                </Badge>
            ),
        },
        {
            accessorKey: 'created_at',
            header: ({ column }) => (
                <SortableHeader column={column} title="Created" />
            ),
            cell: ({ row }) =>
                new Date(row.original.created_at).toLocaleDateString(),
        },
        {
            id: 'actions',
            header: () => <div className="text-center">Actions</div>,
            cell: ({ row }) => (
                <div className="flex justify-center">
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <button
                                type="button"
                                className="inline-flex h-7 w-7 items-center justify-center rounded-md text-gray-400 transition-colors hover:bg-black/4 hover:text-gray-600 dark:text-gray-500 dark:hover:bg-white/6 dark:hover:text-gray-300"
                                aria-label={`Open actions for ${row.original.reference}`}
                            >
                                <MoreHorizontal className="h-4 w-4" />
                            </button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" className="w-36">
                            <DropdownMenuItem
                                onClick={() => openEditDialog(row.original)}
                            >
                                <Pencil className="mr-2 h-3.5 w-3.5" />
                                Edit
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem
                                className="text-red-500 focus:text-red-500"
                                onClick={() => setDeletingTicket(row.original)}
                            >
                                <Trash2 className="mr-2 h-3.5 w-3.5" />
                                Delete
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            ),
        },
    ];

    return (
        <AppLayout>
            <Head title={`${workspace.name} - Customer Support`} />
            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
                <PageHeader
                    title="Customer Support"
                    description="View your workspace support requests"
                >
                    <Button
                        type="button"
                        onClick={() => {
                            createForm.clearErrors();
                            setCreateDialogOpen(true);
                        }}
                        className="flex h-8 items-center gap-2 rounded-lg bg-emerald-600 px-3.5 font-mono! text-[12px]! font-medium text-white transition-all hover:bg-emerald-700"
                    >
                        <LifeBuoy className="h-3.5 w-3.5" />
                        Submit Ticket
                    </Button>
                </PageHeader>

                <div className="rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
                    <DataTable
                        columns={columns}
                        enableInternalPagination={false}
                        data={tickets.data || []}
                        initialSorting={initialSorting}
                        meta={{ ...omit(tickets, ['data']) }}
                        onFetch={(params) => {
                            router.get(
                                `/workspaces/${workspace.slug}/support`,
                                {
                                    sort: params?.sort,
                                    page: params?.page ?? 1,
                                    per_page: params?.per_page,
                                },
                                {
                                    preserveState: true,
                                    replace: true,
                                    preserveScroll: true,
                                },
                            );
                        }}
                    />
                </div>
            </div>

            <Dialog open={createDialogOpen} onOpenChange={setCreateDialogOpen}>
                <DialogContent className="sm:max-w-xl">
                    <DialogHeader>
                        <DialogTitle>Submit Support Ticket</DialogTitle>
                        <DialogDescription>
                            Send a ticket to support and include the area you
                            need help with.
                        </DialogDescription>
                    </DialogHeader>

                    <form onSubmit={handleCreateSubmit} className="space-y-4">
                        <div className="space-y-2">
                            <Label htmlFor="support-page">Area</Label>
                            <Select
                                value={selectedPageValue}
                                onValueChange={setSelectedPageValue}
                            >
                                <SelectTrigger id="support-page">
                                    <SelectValue placeholder="Select an area" />
                                </SelectTrigger>
                                <SelectContent className="max-h-80">
                                    {Object.entries(groupedDestinations).map(
                                        ([group, destinations], index) =>
                                            renderDestinationGroup(
                                                group,
                                                destinations,
                                                index,
                                            ),
                                    )}
                                </SelectContent>
                            </Select>
                            <p className="text-[11px] text-gray-400 dark:text-gray-500">
                                {pageDescription}
                            </p>
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="support-category">Category</Label>
                            <Select
                                value={createForm.data.category}
                                onValueChange={(value) =>
                                    createForm.setData(
                                        'category',
                                        value as SupportTicket['category'],
                                    )
                                }
                            >
                                <SelectTrigger id="support-category">
                                    <SelectValue placeholder="Select a category" />
                                </SelectTrigger>
                                <SelectContent>
                                    {Object.entries(CATEGORY_LABELS).map(
                                        ([value, label]) => (
                                            <SelectItem
                                                key={value}
                                                value={value}
                                            >
                                                {label}
                                            </SelectItem>
                                        ),
                                    )}
                                </SelectContent>
                            </Select>
                            <InputError message={createForm.errors.category} />
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="support-subject">Subject</Label>
                            <Input
                                id="support-subject"
                                value={createForm.data.subject}
                                onChange={(event) =>
                                    createForm.setData(
                                        'subject',
                                        event.target.value,
                                    )
                                }
                                placeholder="Short summary"
                            />
                            <InputError message={createForm.errors.subject} />
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="support-description">
                                Description
                            </Label>
                            <Textarea
                                id="support-description"
                                value={createForm.data.description}
                                onChange={(event) =>
                                    createForm.setData(
                                        'description',
                                        event.target.value,
                                    )
                                }
                                placeholder="Tell us what you need help with"
                                rows={6}
                            />
                            <InputError
                                message={createForm.errors.description}
                            />
                        </div>

                        <DialogFooter className="gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setCreateDialogOpen(false)}
                            >
                                Cancel
                            </Button>
                            <Button type="submit" disabled={createProcessing}>
                                {createProcessing
                                    ? 'Sending...'
                                    : 'Submit ticket'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog
                open={!!editingTicket}
                onOpenChange={(open) => !open && setEditingTicket(null)}
            >
                <DialogContent className="sm:max-w-xl">
                    <DialogHeader>
                        <DialogTitle>Edit Support Ticket</DialogTitle>
                        <DialogDescription>
                            Update the category, subject, or description for
                            this request.
                        </DialogDescription>
                    </DialogHeader>

                    <form onSubmit={handleEditSubmit} className="space-y-4">
                        <div className="space-y-2">
                            <Label htmlFor="ticket-category">Category</Label>
                            <Select
                                value={editForm.data.category}
                                onValueChange={(value) =>
                                    editForm.setData(
                                        'category',
                                        value as SupportTicket['category'],
                                    )
                                }
                            >
                                <SelectTrigger id="ticket-category">
                                    <SelectValue placeholder="Select a category" />
                                </SelectTrigger>
                                <SelectContent>
                                    {Object.entries(CATEGORY_LABELS).map(
                                        ([value, label]) => (
                                            <SelectItem
                                                key={value}
                                                value={value}
                                            >
                                                {label}
                                            </SelectItem>
                                        ),
                                    )}
                                </SelectContent>
                            </Select>
                            <InputError message={editForm.errors.category} />
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="ticket-subject">Subject</Label>
                            <Input
                                id="ticket-subject"
                                value={editForm.data.subject}
                                onChange={(event) =>
                                    editForm.setData(
                                        'subject',
                                        event.target.value,
                                    )
                                }
                            />
                            <InputError message={editForm.errors.subject} />
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="ticket-description">
                                Description
                            </Label>
                            <Textarea
                                id="ticket-description"
                                value={editForm.data.description}
                                onChange={(event) =>
                                    editForm.setData(
                                        'description',
                                        event.target.value,
                                    )
                                }
                                rows={6}
                            />
                            <InputError message={editForm.errors.description} />
                        </div>

                        <DialogFooter className="gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setEditingTicket(null)}
                            >
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                disabled={editForm.processing}
                            >
                                {editForm.processing
                                    ? 'Saving...'
                                    : 'Save changes'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <AlertDialog
                open={!!deletingTicket}
                onOpenChange={(open) => !open && setDeletingTicket(null)}
            >
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>
                            Delete Support Ticket
                        </AlertDialogTitle>
                        <AlertDialogDescription>
                            This will permanently delete{' '}
                            {deletingTicket?.reference ?? 'this ticket'}. This
                            action cannot be undone.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction
                            className="bg-red-600 text-white hover:bg-red-700"
                            onClick={handleDelete}
                        >
                            Delete ticket
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </AppLayout>
    );
}
