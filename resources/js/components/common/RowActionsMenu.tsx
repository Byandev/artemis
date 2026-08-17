import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Link } from '@inertiajs/react';
import { MoreHorizontal } from 'lucide-react';
import { Fragment, ReactNode } from 'react';

export interface RowAction {
    label: ReactNode;
    /** Bare lucide icon, e.g. `<Pencil />` — the menu sizes and spaces it. */
    icon: ReactNode;
    /** Inertia navigation target. Renders the item as a `<Link>`. */
    href?: string;
    /**
     * Renders `href` as a plain `<a>` instead of an Inertia `<Link>`. Needed for
     * file downloads and anything else that must hit the server directly.
     */
    external?: boolean;
    onSelect?: () => void;
    destructive?: boolean;
    disabled?: boolean;
    /** Draws a divider above this item — used to fence off destructive actions. */
    separatorBefore?: boolean;
}

interface Props {
    actions: RowAction[];
    /** Width of the menu panel. Widen it for longer labels. */
    width?: string;
}

/**
 * Three-dot row actions menu for DataTable `actions` columns. Collapses what
 * would otherwise be a row of icon buttons into a single trigger, so the column
 * stays one cell wide no matter how many actions a row has.
 */
export default function RowActionsMenu({ actions, width = 'w-52' }: Props) {
    if (actions.length === 0) return null;

    return (
        <div className="flex justify-end">
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <button
                        type="button"
                        aria-label="Row actions"
                        className="flex h-7 w-7 items-center justify-center rounded-lg border border-black/6 bg-stone-50 text-gray-400 transition-all hover:border-black/12 hover:bg-stone-100 hover:text-gray-600 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-500 dark:hover:border-white/12 dark:hover:bg-zinc-700 dark:hover:text-gray-300"
                    >
                        <MoreHorizontal className="h-3.5 w-3.5" />
                    </button>
                </DropdownMenuTrigger>

                <DropdownMenuContent align="end" className={width}>
                    {actions.map((action, index) => {
                        const content = (
                            <>
                                {action.icon}
                                {action.label}
                            </>
                        );

                        return (
                            <Fragment key={index}>
                                {action.separatorBefore && index > 0 && (
                                    <DropdownMenuSeparator />
                                )}

                                {action.href ? (
                                    <DropdownMenuItem
                                        asChild
                                        variant={
                                            action.destructive
                                                ? 'destructive'
                                                : 'default'
                                        }
                                        disabled={action.disabled}
                                    >
                                        {action.external ? (
                                            <a href={action.href}>{content}</a>
                                        ) : (
                                            <Link href={action.href}>
                                                {content}
                                            </Link>
                                        )}
                                    </DropdownMenuItem>
                                ) : (
                                    <DropdownMenuItem
                                        variant={
                                            action.destructive
                                                ? 'destructive'
                                                : 'default'
                                        }
                                        disabled={action.disabled}
                                        // Deferred a tick so the menu finishes
                                        // closing first. Several of these open a
                                        // blocking confirm() or a dialog, which
                                        // otherwise races the close and can leave
                                        // pointer-events: none stuck on <body>.
                                        onSelect={() => {
                                            const run = action.onSelect;
                                            if (run) setTimeout(run, 0);
                                        }}
                                    >
                                        {content}
                                    </DropdownMenuItem>
                                )}
                            </Fragment>
                        );
                    })}
                </DropdownMenuContent>
            </DropdownMenu>
        </div>
    );
}
