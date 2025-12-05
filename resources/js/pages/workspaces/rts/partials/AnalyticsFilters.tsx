import React from 'react';
import { FilterIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import SearchSelect from './SearchSelect';
import { DropdownMenu, DropdownMenuContent, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from '@/components/ui/accordion';

type BreakDownAnalytics = {
    id: number;
    name: string;
    total_orders: number;
    rts_rate_percentage: number;
    returned_count: number;
    delivered_count: number;
}

type Props = {
    groupedByPage: BreakDownAnalytics[];
    groupedByUsers: BreakDownAnalytics[];
    groupedByShops: BreakDownAnalytics[];
    selectedPagesFilter: number[];
    setSelectedPagesFilter: React.Dispatch<React.SetStateAction<number[]>>;
    selectedUsersFilter: number[];
    setSelectedUsersFilter: React.Dispatch<React.SetStateAction<number[]>>;
    selectedShopFilter: number[];
    setSelectedShopFilter: React.Dispatch<React.SetStateAction<number[]>>;
    loadingGrouped: boolean;
}

const AnalyticsFilters = ({
    groupedByPage,
    groupedByUsers,
    groupedByShops,
    selectedPagesFilter,
    setSelectedPagesFilter,
    selectedUsersFilter,
    setSelectedUsersFilter,
    selectedShopFilter,
    setSelectedShopFilter,
    loadingGrouped,
}: Props) => {
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
                            {loadingGrouped ? (
                                <div>Loading...</div>
                            ) : (
                                <SearchSelect
                                    items={groupedByPage.map((page) => ({
                                        id: page.id,
                                        name: page.name,
                                    }))}
                                    selected={selectedPagesFilter}
                                    setSelected={setSelectedPagesFilter}
                                />
                            )}
                        </AccordionContent>
                    </AccordionItem>
                    <AccordionItem value="item-2">
                        <AccordionTrigger className='py-2'>User</AccordionTrigger>
                        <AccordionContent className="flex flex-col gap-4 text-balance">
                            {loadingGrouped ? (
                                <div>Loading...</div>
                            ) : (
                                <SearchSelect
                                    items={groupedByUsers.map((user) => ({
                                        id: user.id,
                                        name: user.name,
                                    }))}
                                    selected={selectedUsersFilter}
                                    setSelected={setSelectedUsersFilter}
                                />
                            )}
                        </AccordionContent>
                    </AccordionItem>
                    <AccordionItem value="item-3">
                        <AccordionTrigger className='py-2'>Shop</AccordionTrigger>
                        <AccordionContent className="flex flex-col gap-4 text-balance">
                            {loadingGrouped ? (
                                <div>Loading...</div>
                            ) : (
                                <SearchSelect
                                    items={groupedByShops.map((shop) => ({
                                        id: shop.id,
                                        name: shop.name,
                                    }))}
                                    selected={selectedShopFilter}
                                    setSelected={setSelectedShopFilter}
                                />
                            )}
                        </AccordionContent>
                    </AccordionItem>
                </Accordion>
            </DropdownMenuContent>
        </DropdownMenu>
    )
}

export default AnalyticsFilters;
