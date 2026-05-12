import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { User } from '@/types/models/Pancake/User';
import { Check, Search } from 'lucide-react';
import { useEffect, useState } from 'react';

interface Props {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    users: User[];
    onSubmit?: (userId: string) => void;
}

export default function FormModal({
    open,
    onOpenChange,
    users,
    onSubmit,
}: Props) {
    const [search, setSearch] = useState('');
    const [selectedUser, setSelectedUser] = useState<User | null>(null);

    // Pre-select saved user when modal opens
    useEffect(() => {
        if (!open) return;
        const savedId = localStorage.getItem('user_id');
        if (savedId) {
            const found = users.find((u) => String(u.id) === savedId);
            if (found) setSelectedUser(found);
        }
        setSearch('');
    }, [open, users]);

    const filtered = users.filter((u) =>
        u.name.toLowerCase().includes(search.toLowerCase()),
    );

    const handleSubmit = () => {
        if (!selectedUser) return;
        localStorage.setItem('user_id', String(selectedUser.id));
        localStorage.setItem('user_name', selectedUser.name);
        onSubmit?.(String(selectedUser.id));
        onOpenChange(false);
    };

    if (users.length === 0) return null;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="gap-0 overflow-hidden p-0 sm:max-w-sm">
                <div className="border-b border-black/6 px-5 pt-5 pb-4 dark:border-white/6">
                    <DialogHeader className="mb-4">
                        <DialogTitle className="text-[15px] font-semibold text-gray-900 dark:text-gray-100">
                            Who are you?
                        </DialogTitle>
                        <DialogDescription className="mt-0.5 text-[12px] text-gray-400 dark:text-gray-500">
                            Select your name to track actions in this session.
                        </DialogDescription>
                    </DialogHeader>

                    {/* Current selection preview */}
                    {selectedUser && (
                        <div className="mb-3 flex items-center gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2.5 dark:border-emerald-500/20 dark:bg-emerald-500/10">
                            <span className="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-emerald-500 text-[11px] font-bold text-white">
                                {selectedUser.name
                                    .split(' ')
                                    .slice(0, 2)
                                    .map((w) => w[0])
                                    .join('')
                                    .toUpperCase()}
                            </span>
                            <div className="flex flex-col">
                                <span className="font-mono text-[9px] tracking-wider text-emerald-500 uppercase dark:text-emerald-400">
                                    Selected
                                </span>
                                <span className="text-[13px] leading-tight font-semibold text-emerald-800 dark:text-emerald-300">
                                    {selectedUser.name}
                                </span>
                            </div>
                            <Check className="ml-auto h-4 w-4 text-emerald-500" />
                        </div>
                    )}

                    {/* Search */}
                    <div className="relative">
                        <Search className="pointer-events-none absolute top-1/2 left-3 h-3.5 w-3.5 -translate-y-1/2 text-gray-400 dark:text-gray-500" />
                        <input
                            autoFocus
                            type="text"
                            placeholder="Search name…"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            className="h-9 w-full rounded-[10px] border border-black/6 bg-stone-100 pr-3 pl-8 font-mono! text-[12px]! text-gray-800 transition-all outline-none placeholder:text-gray-400 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-100 dark:placeholder:text-gray-600 dark:focus:border-emerald-400"
                        />
                    </div>
                </div>

                {/* User list */}
                <div className="max-h-64 overflow-y-auto p-1.5">
                    {filtered.length === 0 ? (
                        <p className="py-6 text-center text-[12px] text-gray-400 dark:text-gray-500">
                            No users found
                        </p>
                    ) : (
                        filtered.map((user) => {
                            const isSelected = selectedUser?.id === user.id;
                            const initials = user.name
                                .split(' ')
                                .slice(0, 2)
                                .map((w) => w[0])
                                .join('')
                                .toUpperCase();
                            return (
                                <button
                                    key={user.id}
                                    onClick={() => setSelectedUser(user)}
                                    className={`flex w-full items-center gap-3 rounded-lg px-3 py-2 text-left transition-colors ${
                                        isSelected
                                            ? 'bg-emerald-50 dark:bg-emerald-500/10'
                                            : 'hover:bg-stone-50 dark:hover:bg-zinc-800'
                                    }`}
                                >
                                    <span
                                        className={`inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-[11px] font-semibold ${
                                            isSelected
                                                ? 'bg-emerald-500 text-white'
                                                : 'bg-stone-200 text-gray-600 dark:bg-zinc-700 dark:text-gray-300'
                                        }`}
                                    >
                                        {initials}
                                    </span>
                                    <span
                                        className={`flex-1 text-[13px] font-medium ${
                                            isSelected
                                                ? 'text-emerald-700 dark:text-emerald-400'
                                                : 'text-gray-700 dark:text-gray-300'
                                        }`}
                                    >
                                        {user.name}
                                    </span>
                                    {isSelected && (
                                        <Check className="h-3.5 w-3.5 text-emerald-500" />
                                    )}
                                </button>
                            );
                        })
                    )}
                </div>

                {/* Footer */}
                <div className="flex items-center justify-end gap-2 border-t border-black/6 px-4 py-3 dark:border-white/6">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() => onOpenChange(false)}
                        className="text-[12px]"
                    >
                        Cancel
                    </Button>
                    <Button
                        size="sm"
                        disabled={!selectedUser}
                        onClick={handleSubmit}
                        className="bg-emerald-600 text-[12px] text-white hover:bg-emerald-700 disabled:opacity-40"
                    >
                        Confirm
                    </Button>
                </div>
            </DialogContent>
        </Dialog>
    );
}
