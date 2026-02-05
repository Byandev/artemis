import { PaginatedData } from '@/types';
import { useEffect, useState } from 'react';
import axios, { AxiosResponse } from 'axios';
import { Workspace } from '@/types/models/Workspace';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { useDebouncedState } from '@/hooks/use-debounced-state';
import { Shop } from '@/types/models/Shop';

const ShopFilter = ({ workspace }: { workspace: Workspace }) => {
    const [shops, setShops] = useState<Shop[]>([]);
    const {
        value: search,
        setValue: setSearch,
        debounced,
    } = useDebouncedState('', { delay: 350 });

    useEffect(() => {
        axios
            .get(`/api/workspaces/${workspace.slug}/shops`, {
                params: {
                    'filter[search]': debounced,
                },
            })
            .then((response: AxiosResponse<PaginatedData<Shop>>) =>
                setShops(response.data.data),
            );
    }, [workspace.slug, debounced]);

    return (
        <div>
            <div className="mb-2 border-b pb-2">
                <Input
                    value={search}
                    placeholder="Search product"
                    className="text-xs placeholder:text-xs"
                    onChange={(e) => setSearch(e.target.value)}
                />
            </div>

            {shops.length > 0 ? (
                <div className="space-y-2">
                    {shops.map((shop) => {
                        return (
                            <div
                                key={shop.id}
                                className="flex items-center gap-x-2 text-xs"
                            >
                                <Checkbox
                                    id={shop.id.toString()}
                                    name={shop.id.toString()}
                                />
                                <Label
                                    htmlFor={shop.id.toString()}
                                    className="text-xs"
                                >
                                    {shop.name}
                                </Label>
                            </div>
                        );
                    })}
                </div>
            ) : (
                <p className="text-center text-xs">No Result</p>
            )}
        </div>
    );
}

export default ShopFilter;
