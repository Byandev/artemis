
import { useEffect, useState } from 'react';
import axios, { AxiosResponse } from 'axios';

import { PaginatedData } from '@/types';

import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import { Workspace } from '@/types/models/Workspace';
import { useDebouncedState } from '@/hooks/use-debounced-state';

type IdLike = string | number;

type EntityFilterProps<T> = {
    workspace: Workspace;
    endpoint: string;
    placeholder?: string;
    getId: (item: T) => IdLike;
    getLabel: (item: T) => string;
    queryParam?: string;
    selected: IdLike[],
    onSelect: (id: IdLike) => void
};

export function EntityFilter<T>({
    workspace,
    endpoint,
    placeholder = 'Search',
    getId,
    getLabel,
    queryParam = 'filter[search]',
    selected,
    onSelect
}: EntityFilterProps<T>) {
    const [items, setItems] = useState<T[]>([]);
    const {
        value: search,
        setValue: setSearch,
        debounced,
    } = useDebouncedState('', { delay: 350 });

    useEffect(() => {
        let cancelled = false;

        axios
            .get(`/api/workspaces/${workspace.slug}${endpoint}`, {
                params: { [queryParam]: debounced },
            })
            .then((response: AxiosResponse<PaginatedData<T>>) => {
                if (!cancelled) setItems(response.data.data);
            });

        return () => {
            cancelled = true;
        };
    }, [workspace.slug, endpoint, debounced, queryParam]);

    return (
        <div>
            <div className="mb-2 border-b pb-2">
                <Input
                    value={search}
                    placeholder={placeholder}
                    className="text-xs placeholder:text-xs"
                    onChange={(e) => setSearch(e.target.value)}
                />
            </div>

            {items.length > 0 ? (
                <div className="space-y-3">
                    {items.map((item) => {
                        const id = getId(item);
                        const idStr = String(id);

                        return (
                            <div
                                key={idStr}
                                className="flex items-center gap-x-2 text-xs"
                            >
                                <Checkbox id={idStr} name={idStr} checked={selected.includes(idStr)} onSelect={() => onSelect(idStr)}/>
                                <Label htmlFor={idStr} className="text-xs text-gray-800">
                                    {getLabel(item)}
                                </Label>
                            </div>
                        );
                    })}
                </div>
            ) : (
                <p className="text-center text-sm">No Result</p>
            )}
        </div>
    );
}
