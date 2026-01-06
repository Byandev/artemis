import React from 'react';
import { FilterIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import SearchSelect from '@/pages/workspaces/rts/partials/SearchSelect';
import { DropdownMenu, DropdownMenuContent, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from '@/components/ui/accordion';

type Props = {
    availableTeams: { id: number; name: string }[];
    availableProducts: { id: number; name: string }[];
    availablePages: { id: number; name: string }[];
    availableShops: { id: number; name: string }[];
    selectedTeams: number[];
    setSelectedTeams: React.Dispatch<React.SetStateAction<number[]>>;
    selectedProducts: number[];
    setSelectedProducts: React.Dispatch<React.SetStateAction<number[]>>;
    selectedPages: number[];
    setSelectedPages: React.Dispatch<React.SetStateAction<number[]>>;
    selectedShops: number[];
    setSelectedShops: React.Dispatch<React.SetStateAction<number[]>>;
}

const DashboardFilters = ({
    availableTeams,
    availableProducts,
    availablePages,
    availableShops,
    selectedTeams,
    setSelectedTeams,
    selectedProducts,
    setSelectedProducts,
    selectedPages,
    setSelectedPages,
    selectedShops,
    setSelectedShops,
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
                        <AccordionTrigger className='py-2'>Team</AccordionTrigger>
                        <AccordionContent className="flex flex-col gap-4 text-balance">
                            <SearchSelect
                                items={availableTeams}
                                selected={selectedTeams}
                                setSelected={setSelectedTeams}
                            />
                        </AccordionContent>
                    </AccordionItem>
                    <AccordionItem value="item-2">
                        <AccordionTrigger className='py-2'>Product</AccordionTrigger>
                        <AccordionContent className="flex flex-col gap-4 text-balance">
                            <SearchSelect
                                items={availableProducts}
                                selected={selectedProducts}
                                setSelected={setSelectedProducts}
                            />
                        </AccordionContent>
                    </AccordionItem>
                    <AccordionItem value="item-3">
                        <AccordionTrigger className='py-2'>Page</AccordionTrigger>
                        <AccordionContent className="flex flex-col gap-4 text-balance">
                            <SearchSelect
                                items={availablePages}
                                selected={selectedPages}
                                setSelected={setSelectedPages}
                            />
                        </AccordionContent>
                    </AccordionItem>
                    <AccordionItem value="item-4">
                        <AccordionTrigger className='py-2'>Shop</AccordionTrigger>
                        <AccordionContent className="flex flex-col gap-4 text-balance">
                            <SearchSelect
                                items={availableShops}
                                selected={selectedShops}
                                setSelected={setSelectedShops}
                            />
                        </AccordionContent>
                    </AccordionItem>
                </Accordion>
            </DropdownMenuContent>
        </DropdownMenu>
    )
}

export default DashboardFilters;
