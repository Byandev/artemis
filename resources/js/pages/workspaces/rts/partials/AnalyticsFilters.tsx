import React, { useEffect, useState } from 'react';
import { FilterIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import SearchSelect from './SearchSelect';
import { DropdownMenu, DropdownMenuContent, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from '@/components/ui/accordion';
import { Workspace } from '@/types/models/Workspace';

type Props = {
    workspace: Workspace;
    selectedPagesFilter: number[];
    setSelectedPagesFilter: React.Dispatch<React.SetStateAction<number[]>>;
    selectedUsersFilter: number[];
    setSelectedUsersFilter: React.Dispatch<React.SetStateAction<number[]>>;
    selectedShopFilter: number[];
    setSelectedShopFilter: React.Dispatch<React.SetStateAction<number[]>>;
}

const AnalyticsFilters = ({
    workspace,
    selectedPagesFilter,
    setSelectedPagesFilter,
    selectedUsersFilter,
    setSelectedUsersFilter,
    selectedShopFilter,
    setSelectedShopFilter,
}: Props) => {
    const [filterOptions, setFilterOptions] = useState<{
        pages: { id: number; name: string }[];
        users: { id: number; name: string }[];
        shops: { id: number; name: string }[];
    }>({ pages: [], users: [], shops: [] });

    // Fetch filter options on mount
    useEffect(() => {
        const fetchFilterOptions = async () => {
            try {
                const res = await fetch(
                    `/workspaces/${workspace.slug}/rts/analytics/group-by/pages`,
                    { credentials: 'same-origin' }
                );
                if (res.ok) {
                    const result = await res.json();
                    setFilterOptions(prev => ({ ...prev, pages: result.filter_options ?? [] }));
                }
            } catch (error) {
                console.error('Error fetching page filter options:', error);
            }

            try {
                const res = await fetch(
                    `/workspaces/${workspace.slug}/rts/analytics/group-by/users`,
                    { credentials: 'same-origin' }
                );
                if (res.ok) {
                    const result = await res.json();
                    setFilterOptions(prev => ({ ...prev, users: result.filter_options ?? [] }));
                }
            } catch (error) {
                console.error('Error fetching user filter options:', error);
            }

            try {
                const res = await fetch(
                    `/workspaces/${workspace.slug}/rts/analytics/group-by/shops`,
                    { credentials: 'same-origin' }
                );
                if (res.ok) {
                    const result = await res.json();
                    setFilterOptions(prev => ({ ...prev, shops: result.filter_options ?? [] }));
                }
            } catch (error) {
                console.error('Error fetching shop filter options:', error);
            }
        };

        fetchFilterOptions();
    }, [workspace.slug]);

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="outline">
                    <FilterIcon className='mr-2 h-4 w-4' />
                    Filter
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-54 p-3">
                <Accordion
                    type="multiple"
                    className="w-full"
                >
                    <AccordionItem value="item-1">
                        <AccordionTrigger className='py-2'>Page</AccordionTrigger>
                        <AccordionContent className="flex flex-col gap-4 text-balance">
                            <SearchSelect
                                items={filterOptions.pages.map((page) => ({
                                    id: page.id,
                                    name: page.name,
                                }))}
                                selected={selectedPagesFilter}
                                setSelected={setSelectedPagesFilter}
                            />
                        </AccordionContent>
                    </AccordionItem>
                    <AccordionItem value="item-2">
                        <AccordionTrigger className='py-2'>User</AccordionTrigger>
                        <AccordionContent className="flex flex-col gap-4 text-balance">
                            <SearchSelect
                                items={filterOptions.users.map((user) => ({
                                    id: user.id,
                                    name: user.name,
                                }))}
                                selected={selectedUsersFilter}
                                setSelected={setSelectedUsersFilter}
                            />
                        </AccordionContent>
                    </AccordionItem>
                    <AccordionItem value="item-3">
                        <AccordionTrigger className='py-2'>Shop</AccordionTrigger>
                        <AccordionContent className="flex flex-col gap-4 text-balance">
                            <SearchSelect
                                items={filterOptions.shops.map((shop) => ({
                                    id: shop.id,
                                    name: shop.name,
                                }))}
                                selected={selectedShopFilter}
                                setSelected={setSelectedShopFilter}
                            />
                        </AccordionContent>
                    </AccordionItem>
                </Accordion>
            </DropdownMenuContent>
        </DropdownMenu>
    )
}

export default AnalyticsFilters;
