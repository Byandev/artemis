import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { User } from '@/types/models/Pancake/User';
import React, { useEffect, useRef, useState } from 'react';
import Select from 'react-select';

interface Props {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    users: User[];
    onSubmit?: (userId: number) => void;
    autoAssignIfSaved?: boolean; // New prop to control auto-assign behavior
}

interface SelectOption {
    value: number;
    label: string;
}

export default function FormModal({
    open,
    onOpenChange,
    users,
    onSubmit,
    autoAssignIfSaved = false, // Default to false to prevent auto-closing
}: Props) {
    const [selectedUser, setSelectedUser] = useState<SelectOption | null>(null);
    const modalContentRef = useRef<HTMLDivElement>(null);

    const userOptions: SelectOption[] = users.map((user) => ({
        value: user.id,
        label: user.name,
    }));


    // Load saved user if exists
    useEffect(() => {
        if (open) {
            const savedId = localStorage.getItem('user_id');
            const savedName = localStorage.getItem('user_name');

            if (savedId && savedName) {
                const user = {
                    value: Number(savedId),
                    label: savedName,
                };

                console.log('FormModal - Found saved user:', user);
                setSelectedUser(user);

                // Only auto-assign and close if explicitly enabled
                if (autoAssignIfSaved && onSubmit) {
                    console.log('FormModal - Auto-assigning and closing modal');
                    onSubmit(user.value);
                    onOpenChange(false);
                } else {
                    console.log(
                        'FormModal - Saved user loaded but not auto-assigning',
                    );
                }
            } else {
                console.log('FormModal - No saved user found');
            }
        }
    }, [open, autoAssignIfSaved, onSubmit, onOpenChange]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        console.log('FormModal - Form submitted with user:', selectedUser);

        if (!selectedUser) return;

        // Save to localStorage
        localStorage.setItem('user_id', selectedUser.value.toString());
        localStorage.setItem('user_name', selectedUser.label);
        console.log('FormModal - Saved user to localStorage');

        if (onSubmit) {
            onSubmit(selectedUser.value);
        }

        onOpenChange(false);
    };

    const handleCancel = () => {
        console.log('FormModal - Cancel clicked');
        onOpenChange(false);
    };

    // Don't render if no users are available
    if (users.length === 0) {
        console.warn('FormModal - No users available to display');
        return null;
    }

    return (
        <Dialog
            open={open}
            onOpenChange={(newOpen) => {
                console.log(
                    'FormModal - Dialog onOpenChange triggered:',
                    newOpen,
                );
                onOpenChange(newOpen);
            }}
        >
            <DialogContent
                ref={modalContentRef}
                className="max-h-[90vh] overflow-y-auto sm:max-w-[600px]"
            >
                <form onSubmit={handleSubmit}>
                    <DialogHeader>
                        <DialogTitle>Assign to me</DialogTitle>
                        <DialogDescription>
                            Select your profile from the list below to assign
                            this item to yourself
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-4 py-4">
                        <Select
                            options={userOptions}
                            value={selectedUser}
                            onChange={(option) => {
                                console.log(
                                    'FormModal - User selected:',
                                    option,
                                );
                                setSelectedUser(option as SelectOption);
                            }}
                            placeholder="Select a user..."
                            isClearable
                            className="react-select-container"
                            classNamePrefix="react-select"
                            menuPortal={
                                modalContentRef.current
                                    ? modalContentRef.current.parentElement
                                    : null
                            }
                            menuPosition="fixed"
                            styles={{
                                menuPortal: (base) => ({
                                    ...base,
                                    zIndex: 9999,
                                }),
                                menu: (base) => ({
                                    ...base,
                                    zIndex: 9999,
                                }),
                            }}
                        />
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={handleCancel}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={!selectedUser}>
                            Assign
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
