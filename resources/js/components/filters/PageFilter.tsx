import { PaginatedData } from '@/types';
import { useEffect, useState } from 'react';
import axios, { AxiosResponse } from 'axios';
import { Workspace } from '@/types/models/Workspace';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { useDebouncedState } from '@/hooks/use-debounced-state';
import { Page } from '@/types/models/Page';

const PageFilter = ({ workspace }: { workspace: Workspace }) => {
    const [pages, setPages] = useState<Page[]>([]);
    const {
        value: search,
        setValue: setSearch,
        debounced,
    } = useDebouncedState('', { delay: 350 });

    useEffect(() => {
        axios
            .get(`/api/workspaces/${workspace.slug}/pages`, {
                params: {
                    'filter[search]': debounced,
                },
            })
            .then((response: AxiosResponse<PaginatedData<Page>>) =>
                setPages(response.data.data),
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

            {pages.length > 0 ? (
                <div className="space-y-2">
                    {pages.map((page) => {
                        return (
                            <div
                                key={page.id}
                                className="flex items-center gap-x-2 text-xs"
                            >
                                <Checkbox
                                    id={page.id.toString()}
                                    name={page.id.toString()}
                                />
                                <Label
                                    htmlFor={page.id.toString()}
                                    className="text-xs"
                                >
                                    {page.name}
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

export default PageFilter;
