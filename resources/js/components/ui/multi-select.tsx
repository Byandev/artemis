import * as React from 'react';
import { X } from 'lucide-react';
import { Badge } from '@/components/ui/badge';

export interface Option {
    value: string;
    label: string;
}

interface MultiSelectProps {
    options: Option[];
    selected: string[];
    onChange: (selected: string[]) => void;
    placeholder?: string;
    className?: string;
}

export function MultiSelect({
    options,
    selected,
    onChange,
    placeholder = 'Select items...',
    className = '',
}: MultiSelectProps) {
    const [open, setOpen] = React.useState(false);
    const [search, setSearch] = React.useState('');
    const dropdownRef = React.useRef<HTMLDivElement>(null);

    // Close dropdown when clicking outside
    React.useEffect(() => {
        const handleClickOutside = (event: MouseEvent) => {
            if (dropdownRef.current && !dropdownRef.current.contains(event.target as Node)) {
                setOpen(false);
            }
        };

        if (open) {
            document.addEventListener('mousedown', handleClickOutside);
            return () => document.removeEventListener('mousedown', handleClickOutside);
        }
    }, [open]);

    const handleSelect = (value: string) => {
        if (selected.includes(value)) {
            onChange(selected.filter(v => v !== value));
        } else {
            onChange([...selected, value]);
        }
        setSearch('');
    };

    const handleRemove = (value: string) => {
        onChange(selected.filter(v => v !== value));
    };

    // Filter options based on search and exclude selected
    const filteredOptions = options.filter(option =>
        !selected.includes(option.value) &&
        option.label.toLowerCase().includes(search.toLowerCase())
    );

    return (
        <div className={`relative ${className}`} ref={dropdownRef}>
            {/* Selected items and input */}
            <div
                className="min-h-10 rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-within:ring-2 focus-within:ring-ring focus-within:ring-offset-2 cursor-text"
                onClick={() => setOpen(true)}
            >
                <div className="flex flex-wrap gap-1 items-center">
                    {selected.map((value) => {
                        const option = options.find(o => o.value === value);
                        return (
                            <Badge key={value} variant="secondary" className="gap-1">
                                {option?.label || value}
                                <X
                                    className="h-3 w-3 cursor-pointer hover:text-destructive"
                                    onClick={(e) => {
                                        e.stopPropagation();
                                        handleRemove(value);
                                    }}
                                />
                            </Badge>
                        );
                    })}
                    <input
                        type="text"
                        className="flex-1 bg-transparent outline-none placeholder:text-muted-foreground min-w-[120px]"
                        placeholder={selected.length === 0 ? placeholder : 'Search...'}
                        value={search}
                        onChange={(e) => {
                            setSearch(e.target.value);
                            setOpen(true);
                        }}
                        onFocus={() => setOpen(true)}
                    />
                </div>
            </div>

            {/* Dropdown options */}
            {open && filteredOptions.length > 0 && (
                <div className="absolute z-50 w-full mt-1 max-h-60 overflow-auto rounded-md border bg-popover text-popover-foreground shadow-md">
                    {filteredOptions.map((option) => (
                        <div
                            key={option.value}
                            className="relative flex cursor-pointer select-none items-center rounded-sm px-2 py-1.5 text-sm outline-none hover:bg-accent hover:text-accent-foreground"
                            onClick={() => handleSelect(option.value)}
                        >
                            {option.label}
                        </div>
                    ))}
                </div>
            )}

            {open && filteredOptions.length === 0 && search && (
                <div className="absolute z-50 w-full mt-1 rounded-md border bg-popover text-popover-foreground shadow-md p-6 text-center text-sm text-muted-foreground">
                    No results found.
                </div>
            )}
        </div>
    );
}
