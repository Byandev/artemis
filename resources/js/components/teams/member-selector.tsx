import { useState, useMemo } from 'react';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { X, Search } from 'lucide-react';

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

export function MemberSelector({ 
    workspaceMembers, 
    selectedMemberIds, 
    onAddMember, 
    onRemoveMember 
}: MemberSelectorProps) {
    const [memberSearch, setMemberSearch] = useState('');

    // Filter workspace members for dropdown (exclude already selected)
    const availableMembers = useMemo(() => {
        return workspaceMembers.filter(member => {
            const matchesSearch = !memberSearch.trim() ||
                member.name.toLowerCase().includes(memberSearch.toLowerCase()) ||
                member.email.toLowerCase().includes(memberSearch.toLowerCase());
            const notSelected = !selectedMemberIds.includes(member.id);
            return matchesSearch && notSelected;
        });
    }, [workspaceMembers, memberSearch, selectedMemberIds]);

    // Get selected members details
    const selectedMembers = useMemo(() => {
        return workspaceMembers.filter(member => selectedMemberIds.includes(member.id));
    }, [workspaceMembers, selectedMemberIds]);

    const handleAddMember = (memberId: number) => {
        onAddMember(memberId);
        setMemberSearch('');
    };

    return (
        <div className="space-y-3">
            <Label>Members</Label>

            {/* Search Input */}
            <div className="relative">
                <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                    type="text"
                    placeholder="Search team members..."
                    value={memberSearch}
                    onChange={(e) => setMemberSearch(e.target.value)}
                    className="pl-9"
                />
            </div>

            {/* Dropdown Results */}
            {memberSearch.trim() && availableMembers.length > 0 && (
                <div className="border rounded-md max-h-40 overflow-y-auto">
                    {availableMembers.map(member => (
                        <button
                            key={member.id}
                            type="button"
                            onClick={() => handleAddMember(member.id)}
                            className="w-full px-3 py-2 text-left hover:bg-accent flex items-center gap-3"
                        >
                            <div className="h-8 w-8 rounded-full bg-primary/10 flex items-center justify-center text-sm font-medium">
                                {member.name.charAt(0).toUpperCase()}
                            </div>
                            <div>
                                <div className="font-medium text-sm">{member.name}</div>
                                <div className="text-xs text-muted-foreground">{member.email}</div>
                            </div>
                        </button>
                    ))}
                </div>
            )}

            {memberSearch.trim() && availableMembers.length === 0 && (
                <div className="text-sm text-muted-foreground py-2">
                    No members found
                </div>
            )}

            {/* Selected Members */}
            {selectedMembers.length > 0 && (
                <div className="space-y-2">
                    {selectedMembers.map(member => (
                        <div
                            key={member.id}
                            className="flex items-center justify-between p-2 border rounded-md bg-muted/50"
                        >
                            <div className="flex items-center gap-3">
                                <div className="h-8 w-8 rounded-full bg-primary/10 flex items-center justify-center text-sm font-medium">
                                    {member.name.charAt(0).toUpperCase()}
                                </div>
                                <div>
                                    <div className="font-medium text-sm">{member.name}</div>
                                    <div className="text-xs text-muted-foreground">{member.email}</div>
                                </div>
                            </div>
                            <button
                                type="button"
                                onClick={() => onRemoveMember(member.id)}
                                className="h-6 w-6 rounded-full hover:bg-destructive/10 flex items-center justify-center"
                            >
                                <X className="h-4 w-4 text-muted-foreground hover:text-destructive" />
                            </button>
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}
