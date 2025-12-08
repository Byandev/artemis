import React, { useEffect, useState } from 'react';
import { InputGroup, InputGroupAddon, InputGroupInput } from '@/components/ui/input-group';
import { Search, XIcon } from 'lucide-react';

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
            item.name.toLowerCase().includes(query.trim().toLowerCase())
        );

        setResults(r);
    }, [query, items]);

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
                        {query.trim() === '' ? null : `${results.length} results`}
                    </InputGroupAddon>
                </InputGroup>

                {query.trim() !== '' && (
                    <div className="absolute left-0 right-0 mt-1 z-50 bg-white dark:bg-slate-800 border rounded-md shadow-lg max-h-48 overflow-auto">
                        {(() => {
                            const filtered = results.filter((p) => !selected.includes(p.id));

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
                                    <label className="flex items-center gap-2 w-full truncate">
                                        <span
                                            className="truncate cursor-pointer"
                                            onClick={() => {
                                                setSelected((prev) => [...prev, item.id]);
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
                <p className="text-sm text-muted-foreground mt-2 text-center py-5">
                    No item selected.
                </p>
            ) : (
                <div className="mt-2 max-h-[160px] overflow-auto space-y-3">
                    {selected.map((id) => {
                        const item = items.find((i) => i.id === id);
                        if (!item) return null;
                        return (
                            <div
                                key={id}
                                className="flex items-center justify-between px-2 py-1 rounded-md mb-1"
                            >
                                <span className="truncate">{item.name}</span>
                                <button
                                    onClick={() =>
                                        setSelected((prev) => prev.filter((p) => p !== id))
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
