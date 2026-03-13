import { Checkbox } from '@/components/ui/checkbox';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { Label } from '@/components/ui/label';
import { ChevronDown, ChevronUp } from 'lucide-react';
import { useState } from 'react';

type IdLike = string | number;

type FilterGroupProps<T> = {
    getId: (item: T) => IdLike;
    getLabel: (item: T) => string;
    selected: IdLike[];
    onSelect: (id: IdLike) => void;
    options: T[];
    name: string;
};

export function FilterGroup<T>({
    name,
    options,
    getId,
    getLabel,
    selected,
    onSelect,
}: FilterGroupProps<T>) {
    const [open, setOpen] = useState(false);

    return (
        <Collapsible
            open={open}
            onOpenChange={setOpen}
            className="rounded-lg border bg-gray-50 p-4"
        >
            <CollapsibleTrigger className="flex w-full items-center justify-between text-sm font-medium">
                {name}
                {open ? (
                    <ChevronUp className="h-4 w-4" />
                ) : (
                    <ChevronDown className="h-4 w-4" />
                )}
            </CollapsibleTrigger>

            <CollapsibleContent className="mt-4 border-t border-emerald-200 pt-2">
                <div className="space-y-2">
                    {options.map((item) => {
                        const id = getId(item);
                        const idStr = String(id);

                        return (
                            <div
                                key={idStr}
                                className="flex items-center gap-x-2 text-sm"
                            >
                                <Checkbox
                                    id={idStr}
                                    name={idStr}
                                    checked={selected.includes(idStr)}
                                    onCheckedChange={() => onSelect(idStr)}
                                />
                                <Label
                                    htmlFor={idStr}
                                    className="truncate text-sm text-gray-800"
                                >
                                    {getLabel(item)}
                                </Label>
                            </div>
                        );
                    })}
                </div>
            </CollapsibleContent>
        </Collapsible>
    );
}
