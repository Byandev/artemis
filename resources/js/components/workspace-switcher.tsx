import {
  DropdownMenu,
  DropdownMenuTrigger,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
} from "@/components/ui/dropdown-menu";
import { ChevronsUpDown, Check, Plus, Users, LayoutGrid, KeyRound } from "lucide-react";
import { Workspace } from '@/types/models/Workspace';
import { Link, usePage } from '@inertiajs/react';

const WorkspaceSwitcher = () => {
    const { currentWorkspace, workspaces } = usePage<{
        currentWorkspace: Workspace;
        workspaces: Workspace[];
    }>().props;

    if (!currentWorkspace || !workspaces || workspaces.length === 0) {
        return null;
    }

    const initials = currentWorkspace.name
        .split(' ')
        .slice(0, 2)
        .map((w) => w[0])
        .join('')
        .toUpperCase();

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <button className="inline-flex items-center gap-2 h-8 px-2 rounded-[8px] bg-black/[0.04] dark:bg-white/[0.05] hover:bg-black/[0.07] dark:hover:bg-white/[0.08] transition-colors duration-150 outline-none">
                    <span className="flex items-center justify-center h-5 w-5 rounded-[5px] bg-emerald-600 dark:bg-emerald-500 text-white text-[10px] font-semibold shrink-0 select-none">
                        {initials}
                    </span>
                    <span className="text-[13px] font-medium text-gray-700 dark:text-gray-200 max-w-[120px] truncate">
                        {currentWorkspace.name}
                    </span>
                    <ChevronsUpDown className="h-3 w-3 text-gray-300 dark:text-gray-600 shrink-0" />
                </button>
            </DropdownMenuTrigger>

            <DropdownMenuContent
                align="start"
                className="w-64 p-0 rounded-[14px] border border-black/6 dark:border-white/6 bg-white dark:bg-zinc-900 shadow-[0_8px_30px_rgba(0,0,0,0.08)] dark:shadow-[0_8px_30px_rgba(0,0,0,0.4)]"
            >
                {/* Header */}
                <div className="px-3 pt-3 pb-2">
                    <p className="text-[10px] font-mono font-medium uppercase tracking-wider text-gray-300 dark:text-gray-600 px-1">
                        Workspaces
                    </p>
                </div>

                {/* Workspace list */}
                <div className="px-2 pb-2 space-y-0.5">
                    {workspaces.map((workspace) => {
                        const isCurrent = workspace.id === currentWorkspace.id;
                        const ws_initials = workspace.name
                            .split(' ')
                            .slice(0, 2)
                            .map((w) => w[0])
                            .join('')
                            .toUpperCase();

                        return (
                            <DropdownMenuItem key={workspace.id} asChild className="p-0 focus:bg-transparent">
                                <Link
                                    href={`/workspaces/${workspace.slug}/switch`}
                                    method="post"
                                    type="button"
                                    className={[
                                        'flex items-center gap-2.5 w-full px-2 py-2 rounded-[8px] transition-colors cursor-pointer',
                                        isCurrent
                                            ? 'bg-emerald-500/[0.08] dark:bg-emerald-500/[0.10]'
                                            : 'hover:bg-black/[0.03] dark:hover:bg-white/[0.04]',
                                    ].join(' ')}
                                >
                                    <span className={[
                                        'flex items-center justify-center h-6 w-6 rounded-[6px] text-[11px] font-semibold shrink-0 select-none',
                                        isCurrent
                                            ? 'bg-emerald-500/[0.15] dark:bg-emerald-500/[0.20] text-emerald-700 dark:text-emerald-400'
                                            : 'bg-stone-100 dark:bg-zinc-800 text-gray-500 dark:text-gray-400',
                                    ].join(' ')}>
                                        {ws_initials}
                                    </span>
                                    <span className={[
                                        'flex-1 text-[13px] truncate',
                                        isCurrent
                                            ? 'font-medium text-emerald-600 dark:text-emerald-400'
                                            : 'text-gray-600 dark:text-gray-400',
                                    ].join(' ')}>
                                        {workspace.name}
                                    </span>
                                    {isCurrent && (
                                        <Check className="h-3.5 w-3.5 text-emerald-600 dark:text-emerald-400 shrink-0" />

                                    )}
                                </Link>
                            </DropdownMenuItem>
                        );
                    })}
                </div>

                <DropdownMenuSeparator className="bg-black/6 dark:bg-white/6" />

                {/* Actions */}
                <div className="px-2 py-2 space-y-0.5">
                    <DropdownMenuItem asChild className="p-0 focus:bg-transparent">
                        <Link
                            href={`/workspaces/${currentWorkspace.slug}/members`}
                            className="flex items-center gap-2.5 w-full px-2 py-2 rounded-[8px] text-[13px] text-gray-500 dark:text-gray-400 hover:bg-black/[0.03] dark:hover:bg-white/[0.04] hover:text-gray-700 dark:hover:text-gray-200 transition-colors cursor-pointer"
                        >
                            <Users className="h-3.5 w-3.5 shrink-0" />
                            Manage members
                        </Link>
                    </DropdownMenuItem>
                    <DropdownMenuItem asChild className="p-0 focus:bg-transparent">
                        <Link
                            href={`/workspaces/${currentWorkspace.slug}/api-keys`}
                            className="flex items-center gap-2.5 w-full px-2 py-2 rounded-[8px] text-[13px] text-gray-500 dark:text-gray-400 hover:bg-black/[0.03] dark:hover:bg-white/[0.04] hover:text-gray-700 dark:hover:text-gray-200 transition-colors cursor-pointer"
                        >
                            <KeyRound className="h-3.5 w-3.5 shrink-0" />
                            API Keys
                        </Link>
                    </DropdownMenuItem>
                    <DropdownMenuItem asChild className="p-0 focus:bg-transparent">
                        <Link
                            href="/workspaces"
                            className="flex items-center gap-2.5 w-full px-2 py-2 rounded-[8px] text-[13px] text-gray-500 dark:text-gray-400 hover:bg-black/[0.03] dark:hover:bg-white/[0.04] hover:text-gray-700 dark:hover:text-gray-200 transition-colors cursor-pointer"
                        >
                            <LayoutGrid className="h-3.5 w-3.5 shrink-0" />
                            All workspaces
                        </Link>
                    </DropdownMenuItem>
                </div>

                <DropdownMenuSeparator className="bg-black/6 dark:bg-white/6" />

                {/* New workspace CTA */}
                <div className="px-2 py-2">
                    <DropdownMenuItem asChild className="p-0 focus:bg-transparent">
                        <Link
                            href="/workspaces/create"
                            className="flex items-center gap-2.5 w-full px-2 py-2 rounded-[8px] text-[13px] text-gray-500 dark:text-gray-400 hover:bg-black/[0.03] dark:hover:bg-white/[0.04] hover:text-gray-700 dark:hover:text-gray-200 transition-colors cursor-pointer"
                        >
                            <span className="flex items-center justify-center h-4 w-4 rounded-[4px] border border-dashed border-black/20 dark:border-white/20 shrink-0">
                                <Plus className="h-2.5 w-2.5" />
                            </span>
                            New workspace
                        </Link>
                    </DropdownMenuItem>
                </div>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

export default WorkspaceSwitcher;
