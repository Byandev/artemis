import { useState, useMemo } from 'react';
import { Search, X } from 'lucide-react';

interface User {
    id: number;
    name: string;
    email: string;
}

interface MemberSelectorProps {
    workspaceMembers: User[];
    selectedMemberIds: number[];
    onAddMember: (memberId: number) => void;
    onRemoveMember: (memberId: number) => void;
}

export function MemberSelector({ workspaceMembers, selectedMemberIds, onAddMember, onRemoveMember }: MemberSelectorProps) {
    const [memberSearch, setMemberSearch] = useState('');

    const availableMembers = useMemo(() => {
        return workspaceMembers.filter((member) => {
            const matchesSearch =
                !memberSearch.trim() ||
                member.name.toLowerCase().includes(memberSearch.toLowerCase()) ||
                member.email.toLowerCase().includes(memberSearch.toLowerCase());
            return matchesSearch && !selectedMemberIds.includes(member.id);
        });
    }, [workspaceMembers, memberSearch, selectedMemberIds]);

    const selectedMembers = useMemo(() => {
        return workspaceMembers.filter((member) => selectedMemberIds.includes(member.id));
    }, [workspaceMembers, selectedMemberIds]);

    const handleAddMember = (memberId: number) => {
        onAddMember(memberId);
        setMemberSearch('');
    };

    return (
        <div className="space-y-2.5">
            <p className="font-mono text-[10px] font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500">
                Members
            </p>

            {/* Search */}
            <div className="relative">
                <Search className="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-gray-400 dark:text-gray-500" />
                <input
                    type="text"
                    placeholder="Search members…"
                    value={memberSearch}
                    onChange={(e) => setMemberSearch(e.target.value)}
                    className="h-9 w-full rounded-[10px] border border-black/8 bg-stone-50 pl-8 pr-3 font-mono! text-[12px]! text-gray-800 placeholder:text-gray-300 outline-none transition-all focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-100 dark:placeholder:text-gray-600 dark:focus:border-emerald-400"
                />
            </div>

            {/* Dropdown Results */}
            {memberSearch.trim() && availableMembers.length > 0 && (
                <div className="max-h-40 overflow-y-auto rounded-[10px] border border-black/8 bg-white p-1 dark:border-white/8 dark:bg-zinc-900">
                    {availableMembers.map((member) => (
                        <button
                            key={member.id}
                            type="button"
                            onClick={() => handleAddMember(member.id)}
                            className="flex w-full items-center gap-2.5 rounded-md px-2.5 py-1.5 text-left transition-colors hover:bg-stone-50 dark:hover:bg-zinc-800"
                        >
                            <span className="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-emerald-100 font-mono text-[10px] font-semibold text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-400">
                                {member.name.charAt(0).toUpperCase()}
                            </span>
                            <div>
                                <p className="font-mono text-[12px] font-medium text-gray-800 dark:text-gray-100">{member.name}</p>
                                <p className="font-mono text-[10px] text-gray-400 dark:text-gray-500">{member.email}</p>
                            </div>
                        </button>
                    ))}
                </div>
            )}

            {memberSearch.trim() && availableMembers.length === 0 && (
                <p className="font-mono text-[11px] text-gray-400 dark:text-gray-500">No members found</p>
            )}

            {/* Selected Members */}
            {selectedMembers.length > 0 && (
                <div className="space-y-1.5">
                    {selectedMembers.map((member) => (
                        <div
                            key={member.id}
                            className="flex items-center justify-between rounded-[10px] border border-black/6 bg-stone-50 px-3 py-2 dark:border-white/6 dark:bg-zinc-800"
                        >
                            <div className="flex items-center gap-2.5">
                                <span className="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-emerald-100 font-mono text-[10px] font-semibold text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-400">
                                    {member.name.charAt(0).toUpperCase()}
                                </span>
                                <div>
                                    <p className="font-mono text-[12px] font-medium text-gray-800 dark:text-gray-100">{member.name}</p>
                                    <p className="font-mono text-[10px] text-gray-400 dark:text-gray-500">{member.email}</p>
                                </div>
                            </div>
                            <button
                                type="button"
                                onClick={() => onRemoveMember(member.id)}
                                className="flex h-5 w-5 items-center justify-center rounded text-gray-300 transition-colors hover:bg-red-50 hover:text-red-400 dark:text-gray-600 dark:hover:bg-red-500/10 dark:hover:text-red-400"
                            >
                                <X className="h-3 w-3" />
                            </button>
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}
