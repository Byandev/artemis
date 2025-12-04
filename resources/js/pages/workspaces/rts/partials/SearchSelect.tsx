import React, { useEffect, useState } from 'react';
import { InputGroup, InputGroupAddon, InputGroupInput } from '@/components/ui/input-group';
import { Search, XIcon } from 'lucide-react';

type Props = {
    items: string[];
    selected: string[];
    setSelected: React.Dispatch<React.SetStateAction<string[]>>;
};

const SearchSelect: React.FC<Props> = ({ items, selected, setSelected }) => {
    const [query, setQuery] = useState<string>('');
    const [results, setResults] = useState<string[]>([]);

    useEffect(() => {
        if (query.trim() === '') {
            setResults([]);
            return;
        }

        const r = items
            .filter((name) => name.toLowerCase().includes(query.trim().toLowerCase()));

        setResults(r);
    }, [query, items]);

    return (
        <div className='max-h-[200px]'>
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
                            const filtered = results.filter((p) => !selected.includes(p));

                            if (filtered.length === 0) {
                                return <div className="p-3 text-sm text-muted-foreground">No results</div>;
                            }

                            return filtered.map((item) => (
                                <div key={item} className="flex items-center justify-between px-3 py-2 hover:bg-muted/50">
                                    <label className="flex items-center gap-2 w-full truncate">
                                        <span className="truncate" onClick={() => {
                                            if (selected.includes(item)) {
                                                setSelected((prev) => prev.filter((p) => p !== item));
                                            } else {
                                                setSelected((prev) => [...prev, item]);
                                            }
                                            setQuery('');
                                        }}>{item}</span>
                                    </label>
                                </div>
                            ));
                        })()}
                    </div>
                )}
            </div>

            {selected.length === 0 ? (
                <p className="text-sm text-muted-foreground mt-2 text-center py-5">No items selected.</p>
            ) : (
                <div className="mt-2 max-h-[160px] overflow-auto space-y-3  ">
                    {selected.map((item) => (
                        <div key={item} className="flex items-center justify-between px-2 py-1 rounded-md mb-1">
                            <span className="truncate">{item}</span>
                            <button
                                onClick={() => setSelected((prev) => prev.filter((p) => p !== item))}
                                className="text-sm"
                            >
                                <XIcon className="h-4 w-4" />
                            </button>
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
};

export default SearchSelect;
