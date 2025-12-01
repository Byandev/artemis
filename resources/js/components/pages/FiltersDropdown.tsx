import React from 'react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { FilterIcon } from 'lucide-react';
import { User } from '@/types/models/User';

interface Props {
    owners: User[];
    ownerId: string;
    setOwnerId: (id: string) => void;
    status: string;
    setStatus: (s: string) => void;
    handleApply: () => void;
    handleClear: () => void;
}

const FiltersDropdown = ({ owners, ownerId, setOwnerId, status, setStatus, handleApply, handleClear }: Props) => {
    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="outline" size="sm">
                    <FilterIcon className="mr-2 h-4 w-4" />
                    Filters
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent className="w-64 p-4 space-y-2">
                <DropdownMenuLabel>Owner</DropdownMenuLabel>
                {owners.map((owner) => (
                    <DropdownMenuCheckboxItem
                        key={owner.id}
                        checked={ownerId === String(owner.id)}
                        onCheckedChange={(checked) => setOwnerId(checked ? String(owner.id) : "")}
                    >
                        {owner.name}
                    </DropdownMenuCheckboxItem>
                ))}
                <DropdownMenuSeparator />
                <DropdownMenuLabel>Status</DropdownMenuLabel>
                <DropdownMenuCheckboxItem
                    checked={status === "active"}
                    onCheckedChange={(checked) => setStatus(checked ? "active" : "")}
                >
                    Active
                </DropdownMenuCheckboxItem>
                <DropdownMenuCheckboxItem
                    checked={status === "inactive"}
                    onCheckedChange={(checked) => setStatus(checked ? "inactive" : "")}
                >
                    Inactive
                </DropdownMenuCheckboxItem>
                <div className="flex justiy-between mt-2">
                    <Button variant="ghost" onClick={handleClear}>Clear</Button>
                    <Button onClick={handleApply}>Apply</Button>
                </div>
            </DropdownMenuContent>
        </DropdownMenu>
    );
};

export default FiltersDropdown;
