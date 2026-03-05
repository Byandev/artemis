import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';

type IdLike = string | number;

type FilterGroupProps<T> = {
    getId: (item: T) => IdLike;
    getLabel: (item: T) => string;
    selected: IdLike[];
    onSelect: (id: IdLike) => void;
    options: T[]
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
    return (
        <Collapsible className="rounded-xl bg-gray-50 p-2">
            <CollapsibleTrigger className='w-full text-left text-sm font-medium'>
                {name}
            </CollapsibleTrigger>

            <CollapsibleContent className="mt-2">
                <div className='space-y-1.5'>
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
                                    className="text-sm text-gray-800 truncate"
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
