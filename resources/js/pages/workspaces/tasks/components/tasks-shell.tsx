import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';
import { PanelLeft } from 'lucide-react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import type { BoardOptions } from '../hooks/use-task-board';
import { NavTaskTree } from './nav-task-tree';
import { TaskWorkspaceProvider } from './task-workspace';

/**
 * The frame both tasks pages share: Artemis's app layout, the board state, and
 * the space tree as a side panel. In Matrix the tree lived in the global
 * sidebar; here that sidebar is the workspace navigation, so the tree sits
 * beside the page instead and folds above it on narrow screens.
 */
export function TasksShell({
    slug,
    breadcrumbs,
    options,
    children,
}: {
    slug: string;
    breadcrumbs: BreadcrumbItem[];
    options: BoardOptions;
    children: ReactNode;
}) {
    const [treeOpen, setTreeOpen] = useState(false);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <TaskWorkspaceProvider slug={slug} {...options}>
                <div className="flex min-h-0 flex-1 flex-col lg:flex-row">
                    <aside className="shrink-0 border-b border-black/6 bg-stone-50/60 lg:w-64 lg:border-r lg:border-b-0 dark:border-white/6 dark:bg-zinc-950/40">
                        <button
                            type="button"
                            onClick={() => setTreeOpen((open) => !open)}
                            aria-expanded={treeOpen}
                            className="flex w-full items-center gap-2 px-4 py-3 text-[13px] font-medium text-gray-600 lg:hidden dark:text-gray-300"
                        >
                            <PanelLeft className="size-4" />
                            Spaces & lists
                        </button>

                        <div
                            className={cn(
                                'px-2 pb-3 lg:sticky lg:top-0 lg:block lg:max-h-[calc(100vh-4rem)] lg:overflow-y-auto lg:py-4',
                                !treeOpen && 'hidden',
                            )}
                        >
                            <NavTaskTree />
                        </div>
                    </aside>

                    <div className="flex min-h-0 min-w-0 flex-1 flex-col">
                        {children}
                    </div>
                </div>
            </TaskWorkspaceProvider>
        </AppLayout>
    );
}
