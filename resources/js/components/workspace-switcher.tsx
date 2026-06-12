import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
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
import { PERMISSIONS } from '@/constants/permissions';
import { usePermission } from '@/hooks/use-permission';
import type { User } from '@/types';
import { Workspace } from '@/types/models/Workspace';
import { Link, useForm, usePage } from '@inertiajs/react';
import {
    Check,
    ChevronsUpDown,
    Eye,
    EyeOff,
    KeyRound,
    LayoutGrid,
    Lock,
    Plus,
    Users,
} from 'lucide-react';
import { type FormEvent, useState } from 'react';
import { toast } from 'sonner';

const WorkspaceSwitcher = () => {
    const { auth, currentWorkspace, workspaces } = usePage<{
        auth: { user: User };
        currentWorkspace: Workspace;
        workspaces: Workspace[];
    }>().props;
    const canViewMembers = usePermission(PERMISSIONS.ViewMembers);
    const canManageApiKeys = usePermission(PERMISSIONS.ManageApiKeys);
    const canEditSettings = usePermission(PERMISSIONS.EditWorkspaceSettings);

    const [pwOpen, setPwOpen] = useState(false);
    const [showPw, setShowPw] = useState(false);
    const pwForm = useForm({ password: '' });

    const submitPassword = (e: FormEvent) => {
        e.preventDefault();
        if (!currentWorkspace) return;
        pwForm.post(`/workspaces/${currentWorkspace.slug}/public-password`, {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Public pages password updated.');
                pwForm.reset();
                setShowPw(false);
                setPwOpen(false);
            },
            onError: () => toast.error('Failed to update password.'),
        });
    };

    const canCreateWorkspace =
        currentWorkspace &&
        (!!auth.user?.is_super_admin ||
            !!auth.user?.is_workspace_owner ||
            !!auth.user?.is_workspace_admin);

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
        <>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <button className="inline-flex h-8 max-w-full min-w-0 items-center gap-2 rounded-[8px] bg-black/[0.04] px-2 transition-colors duration-150 outline-none hover:bg-black/[0.07] dark:bg-white/[0.05] dark:hover:bg-white/[0.08]">
                        <span className="flex h-5 w-5 shrink-0 items-center justify-center rounded-[5px] bg-emerald-600 text-[10px] font-semibold text-white select-none dark:bg-emerald-500">
                            {initials}
                        </span>
                        <span className="hidden min-w-0 truncate text-[13px] font-medium text-gray-700 sm:inline dark:text-gray-200">
                            {currentWorkspace.name}
                        </span>
                        <ChevronsUpDown className="hidden h-3 w-3 shrink-0 text-gray-300 sm:block dark:text-gray-600" />
                    </button>
                </DropdownMenuTrigger>

                <DropdownMenuContent
                    align="start"
                    className="w-64 rounded-[14px] border border-black/6 bg-white p-0 shadow-[0_8px_30px_rgba(0,0,0,0.08)] dark:border-white/6 dark:bg-zinc-900 dark:shadow-[0_8px_30px_rgba(0,0,0,0.4)]"
                >
                    {/* Header */}
                    <div className="px-3 pt-3 pb-2">
                        <p className="px-1 font-mono text-[10px] font-medium tracking-wider text-gray-300 uppercase dark:text-gray-600">
                            Workspaces
                        </p>
                    </div>

                    {/* Workspace list */}
                    <div className="custom-scrollbar max-h-[280px] space-y-0.5 overflow-y-auto px-2 pb-2">
                        {workspaces.map((workspace) => {
                            const isCurrent =
                                workspace.id === currentWorkspace.id;
                            const ws_initials = workspace.name
                                .split(' ')
                                .slice(0, 2)
                                .map((w) => w[0])
                                .join('')
                                .toUpperCase();

                            return (
                                <DropdownMenuItem
                                    key={workspace.id}
                                    asChild
                                    className="p-0 focus:bg-transparent"
                                >
                                    <Link
                                        href={`/workspaces/${workspace.slug}/switch`}
                                        method="post"
                                        type="button"
                                        className={[
                                            'flex w-full cursor-pointer items-center gap-2.5 rounded-[8px] px-2 py-2 transition-colors',
                                            isCurrent
                                                ? 'bg-emerald-500/[0.08] dark:bg-emerald-500/[0.10]'
                                                : 'hover:bg-black/[0.03] dark:hover:bg-white/[0.04]',
                                        ].join(' ')}
                                    >
                                        <span
                                            className={[
                                                'flex h-6 w-6 shrink-0 items-center justify-center rounded-[6px] text-[11px] font-semibold select-none',
                                                isCurrent
                                                    ? 'bg-emerald-500/[0.15] text-emerald-700 dark:bg-emerald-500/[0.20] dark:text-emerald-400'
                                                    : 'bg-stone-100 text-gray-500 dark:bg-zinc-800 dark:text-gray-400',
                                            ].join(' ')}
                                        >
                                            {ws_initials}
                                        </span>
                                        <span
                                            className={[
                                                'flex-1 truncate text-[13px]',
                                                isCurrent
                                                    ? 'font-medium text-emerald-600 dark:text-emerald-400'
                                                    : 'text-gray-600 dark:text-gray-400',
                                            ].join(' ')}
                                        >
                                            {workspace.name}
                                        </span>
                                        {isCurrent && (
                                            <Check className="h-3.5 w-3.5 shrink-0 text-emerald-600 dark:text-emerald-400" />
                                        )}
                                    </Link>
                                </DropdownMenuItem>
                            );
                        })}
                    </div>

                    <DropdownMenuSeparator className="bg-black/6 dark:bg-white/6" />

                    {(canViewMembers ||
                        canManageApiKeys ||
                        canEditSettings) && (
                        <>
                            {/* Actions */}
                            <div className="space-y-0.5 px-2 py-2">
                                {canViewMembers && (
                                    <DropdownMenuItem
                                        asChild
                                        className="p-0 focus:bg-transparent"
                                    >
                                        <Link
                                            href={`/workspaces/${currentWorkspace.slug}/members`}
                                            className="flex w-full cursor-pointer items-center gap-2.5 rounded-[8px] px-2 py-2 text-[13px] text-gray-500 transition-colors hover:bg-black/[0.03] hover:text-gray-700 dark:text-gray-400 dark:hover:bg-white/[0.04] dark:hover:text-gray-200"
                                        >
                                            <Users className="h-3.5 w-3.5 shrink-0" />
                                            View Members
                                        </Link>
                                    </DropdownMenuItem>
                                )}
                                {canManageApiKeys && (
                                    <DropdownMenuItem
                                        asChild
                                        className="p-0 focus:bg-transparent"
                                    >
                                        <Link
                                            href={`/workspaces/${currentWorkspace.slug}/api-keys`}
                                            className="flex w-full cursor-pointer items-center gap-2.5 rounded-[8px] px-2 py-2 text-[13px] text-gray-500 transition-colors hover:bg-black/[0.03] hover:text-gray-700 dark:text-gray-400 dark:hover:bg-white/[0.04] dark:hover:text-gray-200"
                                        >
                                            <KeyRound className="h-3.5 w-3.5 shrink-0" />
                                            API Keys
                                        </Link>
                                    </DropdownMenuItem>
                                )}
                                {canEditSettings && (
                                    <DropdownMenuItem
                                        onSelect={(e) => {
                                            e.preventDefault();
                                            setPwOpen(true);
                                        }}
                                        className="flex w-full cursor-pointer items-center gap-2.5 rounded-[8px] px-2 py-2 text-[13px] text-gray-500 transition-colors hover:bg-black/[0.03] hover:text-gray-700 dark:text-gray-400 dark:hover:bg-white/[0.04] dark:hover:text-gray-200"
                                    >
                                        <Lock className="h-3.5 w-3.5 shrink-0" />
                                        Public Pages Access
                                        {currentWorkspace.public_password_set && (
                                            <span className="ml-auto inline-flex h-1.5 w-1.5 rounded-full bg-emerald-500" />
                                        )}
                                    </DropdownMenuItem>
                                )}
                            </div>

                            <DropdownMenuSeparator className="bg-black/6 dark:bg-white/6" />
                        </>
                    )}

                    <div className="space-y-0.5 px-2 py-2">
                        <DropdownMenuItem
                            asChild
                            className="p-0 focus:bg-transparent"
                        >
                            <Link
                                href="/workspaces"
                                className="flex w-full cursor-pointer items-center gap-2.5 rounded-[8px] px-2 py-2 text-[13px] text-gray-500 transition-colors hover:bg-black/[0.03] hover:text-gray-700 dark:text-gray-400 dark:hover:bg-white/[0.04] dark:hover:text-gray-200"
                            >
                                <LayoutGrid className="h-3.5 w-3.5 shrink-0" />
                                All workspaces
                            </Link>
                        </DropdownMenuItem>
                    </div>

                    {/* New workspace CTA */}
                    {canCreateWorkspace && (
                        <>
                            <DropdownMenuSeparator className="bg-black/6 dark:bg-white/6" />
                            <div className="px-2 py-2">
                                <DropdownMenuItem
                                    asChild
                                    className="p-0 focus:bg-transparent"
                                >
                                    <Link
                                        href="/workspaces/create"
                                        className="flex w-full cursor-pointer items-center gap-2.5 rounded-[8px] px-2 py-2 text-[13px] text-gray-500 transition-colors hover:bg-black/[0.03] hover:text-gray-700 dark:text-gray-400 dark:hover:bg-white/[0.04] dark:hover:text-gray-200"
                                    >
                                        <span className="flex h-4 w-4 shrink-0 items-center justify-center rounded-[4px] border border-dashed border-black/20 dark:border-white/20">
                                            <Plus className="h-2.5 w-2.5" />
                                        </span>
                                        New workspace
                                    </Link>
                                </DropdownMenuItem>
                            </div>
                        </>
                    )}
                </DropdownMenuContent>
            </DropdownMenu>

            <Dialog open={pwOpen} onOpenChange={setPwOpen}>
                <DialogContent>
                    <form onSubmit={submitPassword}>
                        <DialogHeader>
                            <DialogTitle>Public Pages Access</DialogTitle>
                            <DialogDescription>
                                Require a password to open the public RMO
                                management and leaderboard pages for{' '}
                                <span className="font-medium">
                                    {currentWorkspace.name}
                                </span>
                                .
                            </DialogDescription>
                        </DialogHeader>

                        <div className="space-y-2 py-4">
                            <Label htmlFor="public-password">
                                {currentWorkspace.public_password_set
                                    ? 'New password'
                                    : 'Password'}
                            </Label>
                            <div className="relative">
                                <Input
                                    id="public-password"
                                    type={showPw ? 'text' : 'password'}
                                    autoComplete="new-password"
                                    placeholder={
                                        currentWorkspace.public_password_set
                                            ? '••••••••'
                                            : 'Enter a password (min 4 characters)'
                                    }
                                    value={pwForm.data.password}
                                    onChange={(e) =>
                                        pwForm.setData(
                                            'password',
                                            e.target.value,
                                        )
                                    }
                                    className="pr-10"
                                />
                                <button
                                    type="button"
                                    onClick={() => setShowPw((v) => !v)}
                                    className="absolute inset-y-0 right-0 flex items-center px-3 text-gray-400 transition-colors hover:text-gray-600 dark:hover:text-gray-200"
                                    aria-label={
                                        showPw
                                            ? 'Hide password'
                                            : 'Show password'
                                    }
                                >
                                    {showPw ? (
                                        <EyeOff className="h-4 w-4" />
                                    ) : (
                                        <Eye className="h-4 w-4" />
                                    )}
                                </button>
                            </div>
                            <InputError message={pwForm.errors.password} />
                            {currentWorkspace.public_password_set && (
                                <p className="text-[11px] text-emerald-600 dark:text-emerald-400">
                                    A password is currently set. Enter a new one
                                    to replace it.
                                </p>
                            )}
                        </div>

                        <DialogFooter>
                            <Button
                                type="submit"
                                disabled={
                                    pwForm.processing || !pwForm.data.password
                                }
                            >
                                {pwForm.processing
                                    ? 'Updating…'
                                    : currentWorkspace.public_password_set
                                      ? 'Update'
                                      : 'Set password'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
};

export default WorkspaceSwitcher;
