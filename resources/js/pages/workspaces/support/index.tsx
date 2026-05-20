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
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { toFrontendSort } from '@/lib/sort';
import { PaginatedData } from '@/types';
import { SupportTicket } from '@/types/models/SupportTicket';
import { Workspace } from '@/types/models/Workspace';
import { Head, router, useForm } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import { omit } from 'lodash';
import { MoreHorizontal, Pencil, Trash2 } from 'lucide-react';
import { FormEvent, useMemo, useState } from 'react';
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
};

export default function SupportTicketsIndex({
    workspace,
    tickets,
    query,
}: Props) {
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

    const supportTicketUrl = (ticket: SupportTicket) =>
        `/workspaces/${workspace.slug}/support/${ticket.id}`;

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
                />

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
