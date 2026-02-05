import { Workspace } from '@/types/models/Workspace';
import { useEffect, useState } from 'react';
import axios, { AxiosResponse } from 'axios';
import { Team } from '@/types/models/Team';
import { PaginatedData } from '@/types';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { useDebouncedState } from '@/hooks/use-debounced-state';
import { Product } from '@/types/models/Product';

const ProductFilter = ({ workspace }: { workspace: Workspace }) => {
    const [products, setProducts] = useState<Product[]>([]);
    const {
        value: search,
        setValue: setSearch,
        debounced,
    } = useDebouncedState('', { delay: 350 });

    useEffect(() => {
        axios
            .get(`/api/workspaces/${workspace.slug}/products`, {
                params: {
                    'filter[search]': debounced,
                },
            })
            .then((response: AxiosResponse<PaginatedData<Product>>) =>
                setProducts(response.data.data),
            );
    }, [workspace.slug, debounced]);

    return (
        <div>
            <div className="border-b mb-2 pb-2">
                <Input
                    value={search}
                    placeholder="Search product"
                    className="text-xs placeholder:text-xs"
                    onChange={(e) => setSearch(e.target.value)}
                />
            </div>

            {products.length > 0 ? (
                <div className="space-y-2">
                    {products.map((product) => {
                        return (
                            <div
                                key={product.id}
                                className="flex items-center gap-x-2 text-xs"
                            >
                                <Checkbox
                                    id={product.id.toString()}
                                    name={product.id.toString()}
                                />
                                <Label
                                    htmlFor={product.id.toString()}
                                    className="text-xs"
                                >
                                    {product.code}
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

export default ProductFilter;
