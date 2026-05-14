import {
    InputGroup,
    InputGroupAddon,
    InputGroupInput,
} from '@/components/ui/input-group';
import { Search, XIcon } from 'lucide-react';
import React, { useEffect, useState } from 'react';

type Item = {
    id: number;
    name: string;
};

type Props = {
    items: Item[];
    selected: number[];
    setSelected: React.Dispatch<React.SetStateAction<number[]>>;
};

const SearchSelect: React.FC<Props> = ({ items, selected, setSelected }) => {
    const [query, setQuery] = useState<string>('');
    const [results, setResults] = useState<Item[]>([]);

    useEffect(() => {
        if (query.trim() === '') {
            setResults([]);
            return;
        }

        const r = items.filter((item) =>
            item.name.toLowerCase().includes(query.trim().toLowerCase()),
        );

        setResults(r.filter((item) => !selected.includes(item.id)));
    }, [query, items, selected]);

    return (
        <div className="max-h-[200px]">
            <div className="relative">
                <InputGroup>
                    <InputGroupInput
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        placeholder="Search..."
                    />
                    <InputGroupAddon>
                        <Search />
                    </InputGroupAddon>
                    <InputGroupAddon align="inline-end">
                        {query.trim() === ''
                            ? null
                            : `${results.length} results`}
                    </InputGroupAddon>
                </InputGroup>

                {query.trim() !== '' && (
                    <div className="absolute right-0 left-0 z-50 mt-1 max-h-16 overflow-auto rounded-md border bg-white shadow-lg dark:bg-slate-800">
                        {(() => {
                            const filtered = results.filter(
                                (p) => !selected.includes(p.id),
                            );

                            if (filtered.length === 0) {
                                return (
                                    <div className="p-3 text-sm text-muted-foreground">
                                        No result
                                    </div>
                                );
                            }

                            return filtered.map((item) => (
                                <div
                                    key={item.id}
                                    className="flex items-center justify-between px-3 py-2 hover:bg-muted/50"
                                >
                                    <label className="flex w-full items-center gap-2 truncate">
                                        <span
                                            className="cursor-pointer truncate"
                                            onClick={() => {
                                                setSelected((prev) => [
                                                    ...prev,
                                                    item.id,
                                                ]);
                                                setQuery('');
                                            }}
                                        >
                                            {item.name}
                                        </span>
                                    </label>
                                </div>
                            ));
                        })()}
                    </div>
                )}
            </div>

            {selected.length === 0 ? (
                <p className="mt-2 py-5 text-center text-sm text-muted-foreground">
                    No filter selected.
                </p>
            ) : (
                <div className="mt-2 max-h-[160px] space-y-3 overflow-auto">
                    {selected.map((id) => {
                        const item = items.find((i) => i.id === id);
                        if (!item) return null;
                        return (
                            <div
                                key={id}
                                className="mb-1 flex items-center justify-between rounded-md px-2 py-1"
                            >
                                <span className="truncate">{item.name}</span>
                                <button
                                    onClick={() =>
                                        setSelected((prev) =>
                                            prev.filter((p) => p !== id),
                                        )
                                    }
                                    className="text-sm"
                                >
                                    <XIcon className="h-4 w-4" />
                                </button>
                            </div>
                        );
                    })}
                </div>
            )}
        </div>
    );
};

export default SearchSelect;
