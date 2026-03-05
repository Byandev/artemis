import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';

type IdLike = string | number;

type FilterGroupProps<T> = {
    getId: (item: T) => IdLike;
    getLabel: (item: T) => string;
    selected: IdLike[];
    onSelect: (id: IdLike) => void;
    options: T[]
};

export function FilterGroup<T>({
    options,
    getId,
    getLabel,
    selected,
    onSelect,
}: FilterGroupProps<T>) {
    return (
        <div>
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
                            className="text-sm text-gray-800"
                        >
                            {getLabel(item)}
                        </Label>
                    </div>
                );
            })}
        </div>
    );
}
