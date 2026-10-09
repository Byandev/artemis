import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';
import type { SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import {
    ChevronRight,
    Folder as FolderIcon,
    List,
    MoreHorizontal,
    Plus,
    SlidersHorizontal,
    Trash2,
    Users,
} from 'lucide-react';
import { useState } from 'react';
import { initials } from '../lib/task-format';
import { FIELD_ACTIVE } from '../lib/ui';
import type { Folder, Space, TaskList } from '../types';
import { ConfirmDialog } from './confirm-dialog';
import { ManageMembers } from './manage-members';
import { ManageStatuses } from './manage-statuses';
import { useTaskWorkspace } from './task-workspace';

const TREE_BUTTON =
    'flex h-8 w-full items-center gap-2 rounded-md px-2 text-left text-[13px] text-gray-600 transition-colors hover:bg-stone-100 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-zinc-800 dark:hover:text-gray-100';

const TREE_ACTIVE =
    'bg-stone-100 font-medium text-gray-900 dark:bg-zinc-800 dark:text-gray-100';

const ROW_ICON =
    'flex size-6 items-center justify-center rounded-md text-gray-400 transition-colors hover:bg-stone-200 hover:text-gray-700 dark:hover:bg-zinc-700 dark:hover:text-gray-200';

function ListButton({
    list,
    active,
    onOpen,
    onMenu,
}: {
    list: TaskList;
    active: boolean;
    onOpen: () => void;
    onMenu: (event: React.MouseEvent) => void;
}) {
    return (
        <button
            type="button"
            onClick={onOpen}
            onContextMenu={onMenu}
            className={cn(TREE_BUTTON, active && TREE_ACTIVE)}
        >
            <List className="size-3.5" />
            <span className="truncate">{list.name}</span>
        </button>
    );
}

type Draft = { kind: 'space' | 'folder' | 'list'; folderId: number | null };

/** What a right-click landed on, and where the menu should appear. */
type MenuTarget =
    | { kind: 'space'; space: Space }
    | { kind: 'folder'; folder: Folder }
    | { kind: 'list'; list: TaskList };

type Menu = { target: MenuTarget; x: number; y: number };

type Confirmation = {
    title: string;
    description: string;
    confirmLabel: string;
    run: () => Promise<void>;
};

/** The Space > Folder > List tree, rendered as the tasks pages' side panel. */
export function NavTaskTree() {
    const board = useTaskWorkspace();
    const { auth } = usePage<SharedData>().props;
    const [draft, setDraft] = useState<Draft | null>(null);
    const [value, setValue] = useState('');
    const [closed, setClosed] = useState<number[]>([]);
    const [statusTarget, setStatusTarget] = useState<number | null>(null);
    const [memberTarget, setMemberTarget] = useState<number | null>(null);
    const [menu, setMenu] = useState<Menu | null>(null);
    const [confirmation, setConfirmation] = useState<Confirmation | null>(null);

    const looseLists = board.lists.filter((list) => list.folder_id === null);

    /**
     * Only admins and the owner may edit membership. The API enforces this too;
     * the dialog reads as a roster rather than a dead form for everyone else.
     */
    const memberRole = board.spaces.find(
        (item) => item.id === memberTarget,
    )?.role;
    const canManageMembers =
        board.canManageTasks &&
        (memberRole === 'owner' || memberRole === 'admin');

    /** Without Manage Tasks the tree is for navigating only. */
    const canManage = board.canManageTasks;

    /** Open the tree menu at the pointer, suppressing the browser's own. */
    function openMenu(event: React.MouseEvent, target: MenuTarget) {
        event.preventDefault();
        event.stopPropagation();

        // A folder or list menu holds nothing but changes.
        if (!canManage && target.kind !== 'space') {
            return;
        }

        setMenu({ target, x: event.clientX, y: event.clientY });
    }

    /**
     * Folder and list actions only make sense against the open space, and the
     * draft forms render inside its subtree, so switch to it first.
     */
    function inSpace(spaceId: number, action: () => void) {
        if (spaceId !== board.spaceId) {
            board.openSpace(spaceId);
        }

        action();
    }

    function confirmDeleteSpace(space: Space) {
        setConfirmation({
            title: `Delete ${space.name}?`,
            description:
                'This removes the space along with every folder, list and task inside it. It cannot be undone.',
            confirmLabel: 'Delete space',
            run: () => board.deleteSpace(space),
        });
    }

    function confirmDeleteFolder(folder: Folder) {
        const count = board.lists.filter(
            (list) => list.folder_id === folder.id,
        ).length;

        setConfirmation({
            title: `Delete ${folder.name}?`,
            description:
                count === 0
                    ? 'This removes the folder. It cannot be undone.'
                    : `This removes the folder along with ${count} list${count === 1 ? '' : 's'} inside it and their tasks. It cannot be undone.`,
            confirmLabel: 'Delete folder',
            run: () => board.deleteFolder(folder),
        });
    }

    function confirmDeleteList(list: TaskList) {
        setConfirmation({
            title: `Delete ${list.name}?`,
            description:
                'This removes the list and every task inside it. It cannot be undone.',
            confirmLabel: 'Delete list',
            run: () => board.deleteList(list),
        });
    }

    function start(kind: Draft['kind'], folderId: number | null = null) {
        setDraft({ kind, folderId });
        setValue('');
    }

    async function commit(event: React.FormEvent) {
        event.preventDefault();

        if (draft === null || value.trim() === '') {
            return;
        }

        try {
            if (draft.kind === 'space') {
                await board.createSpace(value.trim());
            } else if (draft.kind === 'folder') {
                await board.createFolder(value.trim());
            } else {
                await board.createList(value.trim(), draft.folderId);
            }

            setDraft(null);
            setValue('');
        } catch (cause) {
            board.report(cause);
        }
    }

    const draftForm = (placeholder: string) => (
        <form onSubmit={commit} className="px-1 py-1">
            <input
                autoFocus
                value={value}
                onChange={(event) => setValue(event.target.value)}
                onBlur={() => value.trim() === '' && setDraft(null)}
                onKeyDown={(event) => event.key === 'Escape' && setDraft(null)}
                placeholder={placeholder}
                aria-label={placeholder}
                className={cn(
                    FIELD_ACTIVE,
                    'h-8 w-full rounded-lg px-2 text-sm',
                )}
            />
        </form>
    );

    const target = menu?.target ?? null;

    return (
        <nav aria-label="Spaces" className="flex flex-col gap-1">
            <div className="flex items-center justify-between px-2 pb-1">
                <span className="font-mono text-[10px] font-medium tracking-wider text-gray-400 uppercase dark:text-gray-500">
                    Spaces
                </span>
                {canManage && (
                    <button
                        type="button"
                        title="New space"
                        onClick={() => start('space')}
                        className={ROW_ICON}
                    >
                        <Plus className="size-3.5" />
                        <span className="sr-only">New space</span>
                    </button>
                )}
            </div>

            <ul className="flex flex-col gap-0.5">
                {board.loadingSpaces &&
                    board.spaces.length === 0 &&
                    ['w-4/5', 'w-3/5'].map((width) => (
                        <li
                            key={width}
                            className="flex h-8 items-center gap-2 px-2"
                        >
                            <Skeleton className="size-4 rounded-md" />
                            <Skeleton className={cn('h-4', width)} />
                        </li>
                    ))}

                {board.spaces.map((space) => {
                    const isActive = space.id === board.spaceId;

                    return (
                        <li key={space.id} className="group/space relative">
                            <button
                                type="button"
                                title={space.name}
                                onClick={() => board.openSpace(space.id)}
                                onContextMenu={(event) =>
                                    openMenu(event, { kind: 'space', space })
                                }
                                className={cn(
                                    TREE_BUTTON,
                                    'h-9 gap-2.5 pr-8',
                                    isActive && TREE_ACTIVE,
                                    isActive &&
                                        'shadow-[inset_2px_0_0_var(--color-emerald-500)]',
                                )}
                            >
                                <span
                                    className={cn(
                                        'flex size-[18px] shrink-0 items-center justify-center rounded-md text-[9px] font-bold text-white',
                                        space.color === null &&
                                            'bg-emerald-600 dark:bg-emerald-500',
                                    )}
                                    style={
                                        space.color === null
                                            ? undefined
                                            : { backgroundColor: space.color }
                                    }
                                >
                                    {initials(space.name)}
                                </span>
                                <span className="truncate">{space.name}</span>
                            </button>

                            <button
                                type="button"
                                title={`Actions for ${space.name}`}
                                onClick={(event) =>
                                    openMenu(event, { kind: 'space', space })
                                }
                                className={cn(
                                    ROW_ICON,
                                    'absolute top-1.5 right-1 opacity-0 group-hover/space:opacity-100 focus-visible:opacity-100',
                                )}
                            >
                                <MoreHorizontal className="size-3.5" />
                                <span className="sr-only">
                                    Actions for {space.name}
                                </span>
                            </button>

                            {isActive && (
                                <ul className="mt-0.5 ml-3.5 flex flex-col gap-0.5 border-l border-black/6 pl-2 dark:border-white/6">
                                    <li>
                                        <button
                                            type="button"
                                            onClick={() => board.openList(null)}
                                            className={cn(
                                                TREE_BUTTON,
                                                board.listId === null &&
                                                    TREE_ACTIVE,
                                            )}
                                        >
                                            <List className="size-3.5" />
                                            <span>All tasks</span>
                                        </button>
                                    </li>

                                    {looseLists.map((list) => (
                                        <li key={list.id}>
                                            <ListButton
                                                list={list}
                                                active={
                                                    board.listId === list.id
                                                }
                                                onOpen={() =>
                                                    board.openList(list.id)
                                                }
                                                onMenu={(event) =>
                                                    openMenu(event, {
                                                        kind: 'list',
                                                        list,
                                                    })
                                                }
                                            />
                                        </li>
                                    ))}

                                    {board.folders.map((folder) => {
                                        const children = board.lists.filter(
                                            (list) =>
                                                list.folder_id === folder.id,
                                        );
                                        const isOpen = !closed.includes(
                                            folder.id,
                                        );

                                        return (
                                            <li
                                                key={folder.id}
                                                className="group/folder relative"
                                            >
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        setClosed((current) =>
                                                            current.includes(
                                                                folder.id,
                                                            )
                                                                ? current.filter(
                                                                      (id) =>
                                                                          id !==
                                                                          folder.id,
                                                                  )
                                                                : [
                                                                      ...current,
                                                                      folder.id,
                                                                  ],
                                                        )
                                                    }
                                                    onContextMenu={(event) =>
                                                        openMenu(event, {
                                                            kind: 'folder',
                                                            folder,
                                                        })
                                                    }
                                                    aria-expanded={isOpen}
                                                    className={cn(
                                                        TREE_BUTTON,
                                                        'pr-8',
                                                    )}
                                                >
                                                    <ChevronRight
                                                        className={cn(
                                                            'size-3.5 transition-transform',
                                                            isOpen &&
                                                                'rotate-90',
                                                        )}
                                                    />
                                                    <FolderIcon className="size-3.5" />
                                                    <span className="truncate">
                                                        {folder.name}
                                                    </span>
                                                </button>

                                                {canManage && (
                                                    <button
                                                        type="button"
                                                        title={`New list in ${folder.name}`}
                                                        onClick={() =>
                                                            start(
                                                                'list',
                                                                folder.id,
                                                            )
                                                        }
                                                        className={cn(
                                                            ROW_ICON,
                                                            'absolute top-0.5 right-1 opacity-0 group-hover/folder:opacity-100 focus-visible:opacity-100',
                                                        )}
                                                    >
                                                        <Plus className="size-3.5" />
                                                        <span className="sr-only">
                                                            New list in{' '}
                                                            {folder.name}
                                                        </span>
                                                    </button>
                                                )}

                                                {isOpen &&
                                                    children.length > 0 && (
                                                        <ul className="ml-2 flex flex-col gap-0.5 border-l border-black/6 pl-1.5 dark:border-white/6">
                                                            {children.map(
                                                                (list) => (
                                                                    <li
                                                                        key={
                                                                            list.id
                                                                        }
                                                                    >
                                                                        <ListButton
                                                                            list={
                                                                                list
                                                                            }
                                                                            active={
                                                                                board.listId ===
                                                                                list.id
                                                                            }
                                                                            onOpen={() =>
                                                                                board.openList(
                                                                                    list.id,
                                                                                )
                                                                            }
                                                                            onMenu={(
                                                                                event,
                                                                            ) =>
                                                                                openMenu(
                                                                                    event,
                                                                                    {
                                                                                        kind: 'list',
                                                                                        list,
                                                                                    },
                                                                                )
                                                                            }
                                                                        />
                                                                    </li>
                                                                ),
                                                            )}
                                                        </ul>
                                                    )}

                                                {draft?.kind === 'list' &&
                                                    draft.folderId ===
                                                        folder.id &&
                                                    draftForm('List name')}
                                            </li>
                                        );
                                    })}

                                    {draft?.kind === 'folder' && (
                                        <li>{draftForm('Folder name')}</li>
                                    )}

                                    {draft?.kind === 'list' &&
                                        draft.folderId === null && (
                                            <li>{draftForm('List name')}</li>
                                        )}

                                    {canManage && (
                                        <li className="flex flex-wrap items-center gap-1 pt-1">
                                            <TreeAction
                                                label="Folder"
                                                onClick={() => start('folder')}
                                            />
                                            <TreeAction
                                                label="List"
                                                onClick={() =>
                                                    start('list', null)
                                                }
                                            />
                                        </li>
                                    )}
                                </ul>
                            )}
                        </li>
                    );
                })}

                {draft?.kind === 'space' && <li>{draftForm('Space name')}</li>}

                {!board.loadingSpaces && board.spaces.length === 0 && (
                    <li className="mx-1 my-1 rounded-lg border border-dashed border-black/12 px-3 py-3 text-xs leading-relaxed text-muted-foreground dark:border-white/12">
                        {canManage
                            ? 'No spaces yet — create one to start organising work.'
                            : 'You have not been added to any space yet.'}
                    </li>
                )}
            </ul>

            {/*
             * One menu for the whole tree, anchored to a zero-sized element at
             * the pointer. Radix needs a real trigger to position against, and
             * this keeps the app on its existing dropdown primitive rather than
             * pulling in a separate context-menu package.
             */}
            <DropdownMenu
                open={menu !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setMenu(null);
                    }
                }}
            >
                <DropdownMenuTrigger asChild>
                    <span
                        aria-hidden="true"
                        className="pointer-events-none fixed size-0"
                        style={{ left: menu?.x ?? 0, top: menu?.y ?? 0 }}
                    />
                </DropdownMenuTrigger>
                <DropdownMenuContent
                    align="start"
                    side="bottom"
                    sideOffset={2}
                    className="w-52"
                >
                    {target?.kind === 'space' && (
                        <SpaceMenuItems
                            space={target.space}
                            canManage={canManage}
                            onNewList={() =>
                                inSpace(target.space.id, () =>
                                    start('list', null),
                                )
                            }
                            onNewFolder={() =>
                                inSpace(target.space.id, () => start('folder'))
                            }
                            onStatuses={() =>
                                inSpace(target.space.id, () =>
                                    setStatusTarget(target.space.id),
                                )
                            }
                            onMembers={() =>
                                inSpace(target.space.id, () =>
                                    setMemberTarget(target.space.id),
                                )
                            }
                            onDelete={() => confirmDeleteSpace(target.space)}
                        />
                    )}

                    {target?.kind === 'folder' && (
                        <>
                            <DropdownMenuItem
                                onSelect={() => start('list', target.folder.id)}
                            >
                                <Plus />
                                New list in folder
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem
                                variant="destructive"
                                onSelect={() =>
                                    confirmDeleteFolder(target.folder)
                                }
                            >
                                <Trash2 />
                                Delete folder
                            </DropdownMenuItem>
                        </>
                    )}

                    {target?.kind === 'list' && (
                        <DropdownMenuItem
                            variant="destructive"
                            onSelect={() => confirmDeleteList(target.list)}
                        >
                            <Trash2 />
                            Delete list
                        </DropdownMenuItem>
                    )}
                </DropdownMenuContent>
            </DropdownMenu>

            <ManageStatuses
                open={statusTarget !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setStatusTarget(null);
                    }
                }}
                spaceName={
                    board.spaces.find((item) => item.id === statusTarget)
                        ?.name ?? 'this space'
                }
                statuses={board.statuses}
                loading={board.loadingSpace || board.spaceId !== statusTarget}
                onCreate={board.createStatus}
                onUpdate={board.updateStatus}
                onDelete={board.deleteStatus}
                onError={board.report}
            />

            <ManageMembers
                open={memberTarget !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setMemberTarget(null);
                    }
                }}
                spaceName={
                    board.spaces.find((item) => item.id === memberTarget)
                        ?.name ?? 'this space'
                }
                members={board.members}
                currentUserId={auth.user.id}
                canManage={canManageMembers}
                loading={board.loadingSpace || board.spaceId !== memberTarget}
                onAdd={board.addMember}
                onSearch={board.searchMemberCandidates}
                onUpdateRole={board.updateMemberRole}
                onRemove={board.removeMember}
                onError={board.report}
            />

            {confirmation !== null && (
                <ConfirmDialog
                    open
                    onOpenChange={(open) => {
                        if (!open) {
                            setConfirmation(null);
                        }
                    }}
                    title={confirmation.title}
                    description={confirmation.description}
                    confirmLabel={confirmation.confirmLabel}
                    onConfirm={confirmation.run}
                    onError={board.report}
                />
            )}
        </nav>
    );
}

function TreeAction({
    label,
    icon: Icon = Plus,
    onClick,
}: {
    label: string;
    icon?: LucideIcon;
    onClick: () => void;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            className="flex items-center gap-1 rounded-md px-1.5 py-1 text-[0.6875rem] font-medium text-muted-foreground transition-colors duration-200 ease-out hover:bg-stone-100 hover:text-foreground dark:hover:bg-zinc-800"
        >
            <Icon className="size-3" />
            {label}
        </button>
    );
}

function SpaceMenuItems({
    space,
    canManage,
    onNewList,
    onNewFolder,
    onStatuses,
    onMembers,
    onDelete,
}: {
    space: Space;
    canManage: boolean;
    onNewList: () => void;
    onNewFolder: () => void;
    onStatuses: () => void;
    onMembers: () => void;
    onDelete: () => void;
}) {
    // Without Manage Tasks the members roster is the only thing left to see.
    if (!canManage) {
        return (
            <DropdownMenuItem onSelect={onMembers}>
                <Users />
                Members
            </DropdownMenuItem>
        );
    }

    return (
        <>
            <DropdownMenuItem onSelect={onNewList}>
                <Plus />
                New list
            </DropdownMenuItem>
            <DropdownMenuItem onSelect={onNewFolder}>
                <FolderIcon />
                New folder
            </DropdownMenuItem>
            <DropdownMenuSeparator />
            <DropdownMenuItem onSelect={onStatuses}>
                <SlidersHorizontal />
                Edit statuses
            </DropdownMenuItem>
            <DropdownMenuItem onSelect={onMembers}>
                <Users />
                Members
            </DropdownMenuItem>
            <DropdownMenuSeparator />
            <DropdownMenuItem variant="destructive" onSelect={onDelete}>
                <Trash2 />
                Delete {space.name}
            </DropdownMenuItem>
        </>
    );
}
