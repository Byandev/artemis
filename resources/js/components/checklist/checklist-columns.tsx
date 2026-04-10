import { SortableHeader } from '@/components/ui/data-table';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { ColumnDef } from '@tanstack/react-table';
import { Eye, MoreHorizontal, Pencil, Trash2 } from 'lucide-react';
import { ChecklistItem } from './types';

type ChecklistColumnActions = {
    onView: (item: ChecklistItem) => void;
    onEdit: (item: ChecklistItem) => void;
    onDelete: (item: ChecklistItem) => void;
};

export function getChecklistColumns({ onView, onEdit, onDelete }: ChecklistColumnActions): ColumnDef<ChecklistItem>[] {
    return [
        {
            accessorKey: 'title',
            header: ({ column }) => <SortableHeader column={column} title="Title" />,
            cell: ({ row }) => (
                <span className="block truncate text-[12px] text-gray-800 dark:text-gray-100" title={row.original.title}>
                    {row.original.title}
                </span>
            ),
        },
        {
            accessorKey: 'target',
            header: ({ column }) => <SortableHeader column={column} title="Target" />,
            cell: ({ row }) => (
                <span className="block truncate whitespace-nowrap text-[12px] text-gray-700 dark:text-gray-200" title={row.original.target}>
                    {row.original.target}
                </span>
            ),
        },
        {
            accessorKey: 'required',
            header: () => <div className="text-center">Required</div>,
            cell: ({ row }) => (
                <div className="flex justify-center">
                    <span
                        className={[
                            'inline-flex min-w-14 justify-center rounded-2xl px-2 py-0.5 text-[11px] font-medium',
                            row.original.required
                                ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300'
                                : 'bg-gray-100 text-gray-500 dark:bg-zinc-800 dark:text-gray-400',
                        ].join(' ')}
                    >
                        {row.original.required ? 'Yes' : 'No'}
                    </span>
                </div>
            ),
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
                                aria-label={`Open actions for ${row.original.title}`}
                            >
                                <MoreHorizontal className="h-4 w-4" />
                            </button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" className="w-[165px] p-1.5">
                            <DropdownMenuItem onClick={() => onView(row.original)}>
                                <Eye className="mr-1.5 h-3.5 w-3.5" />
                                View
                            </DropdownMenuItem>
                            <DropdownMenuItem onClick={() => onEdit(row.original)}>
                                <Pencil className="mr-1.5 h-3.5 w-3.5" />
                                Edit
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                className="text-red-500 focus:text-red-500"
                                onClick={() => onDelete(row.original)}
                            >
                                <Trash2 className="mr-1.5 h-3.5 w-3.5" />
                                Delete
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            ),
        },
    ];
}
