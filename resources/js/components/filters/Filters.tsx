import { useState } from 'react';
import { SlidersHorizontal } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Workspace } from '@/types/models/Workspace';
import ShopFilter from '@/components/filters/ShopFilter';
import PageFilter from '@/components/filters/PageFilter';

export interface FilterValue {
    teamIds: (string | number)[];
    productIds: (string | number)[];
    shopIds: (string | number)[];
    pageIds: (string | number)[];
    userIds: (string | number)[];
}

interface Props {
    workspace: Workspace;
    onChange: (value: FilterValue) => void
}

const Filters = ({ workspace, onChange }: Props) => {
    const [value, setValue] = useState<FilterValue>({
        teamIds: [],
        productIds: [],
        shopIds: [],
        pageIds: [],
        userIds: [],
    });

    return (
        <Popover>
            <PopoverTrigger asChild>
                <Button
                    className="h-11 w-full appearance-none rounded-2xl border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/20 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800"
                    variant="outline"
                >
                    <SlidersHorizontal />
                    Filter
                </Button>
            </PopoverTrigger>

            <PopoverContent className="w-[calc(100vw-2rem)] rounded-2xl sm:w-80">
                <div className="max-h-72 space-y-1.5 overflow-scroll">
                    <PageFilter
                        workspace={workspace}
                        selected={value.pageIds}
                        onSelect={(id) => {
                            setValue((prev) => ({
                                ...prev,
                                pageIds: prev.pageIds.includes(id)
                                    ? prev.pageIds.filter((i) => i !== id)
                                    : [...prev.pageIds, id],
                            }));
                        }}
                    />

                    <ShopFilter
                        workspace={workspace}
                        selected={value.shopIds}
                        onSelect={(id) => {
                            setValue((prev) => ({
                                ...prev,
                                shopIds: prev.shopIds.includes(id)
                                    ? prev.shopIds.filter((i) => i !== id)
                                    : [...prev.shopIds, id],
                            }));
                        }}
                    />
                </div>

                <div className="flex mt-2 gap-x-2">
                    <Button size={'sm'} onClick={() => onChange(value)}>Apply</Button>
                </div>
            </PopoverContent>
        </Popover>
    );
};

export default Filters;
