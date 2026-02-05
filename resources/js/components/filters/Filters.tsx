import { useState, ComponentType } from 'react';
import { Filter, LucideIcon, Package, Store, Users } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';

import { Workspace } from '@/types/models/Workspace';

import TeamFilter from '@/components/filters/TeamFilter';
import ShopFilter from '@/components/filters/ShopFilter';
import PageFilter from '@/components/filters/PageFilter';
import UserFilter from '@/components/filters/UserFilter';
import ProductFilter from '@/components/filters/ProductFilter';

interface Option {
    name: string;
    icon: LucideIcon;
    component: ComponentType<{ workspace: Workspace }>
}

interface Props {
    workspace: Workspace
}

const Filters = ({ workspace }: Props) => {
    const options: Option[] = [
        { name: 'Teams', icon: Users, component: TeamFilter },
        { name: 'Products', icon: Package, component: ProductFilter },
        { name: 'Shop', icon: Store, component: ShopFilter },
        { name: 'Pages', icon: Store, component: PageFilter },
        { name: 'User', icon: Users, component: UserFilter },
    ];

    const [selectedOption, setSelectedOption] = useState<Option | null>();

    const [value, setValue] = useState({
        teamIds: [],
        productIds: [],
        shopIds: [],
        pageIds: [],
        userIds: [],
    })

    const onClick = (option: Option) => {
        setSelectedOption(option)
    }

    return (
        <Popover>
            <PopoverTrigger asChild>
                <Button variant="outline" className="text-gray-800">
                    <Filter />
                    <span className="font-medium">Filters</span>
                </Button>
            </PopoverTrigger>

            <PopoverContent className="w-48 px-2.5 py-4">
                <div className="max-h-72">
                    {selectedOption ? (
                        <div>
                            <div className="mb-2 flex justify-between">
                                <span className="text-xs font-semibold">
                                    {selectedOption.name}
                                </span>

                                <span
                                    onClick={() => setSelectedOption(null)}
                                    className="cursor-pointer text-xs font-semibold text-blue-700"
                                >
                                    Back
                                </span>
                            </div>

                            <selectedOption.component workspace={workspace} />
                        </div>
                    ) : (
                        <div className="space-y-3 text-xs">
                            {options.map((option) => {
                                return (
                                    <div
                                        key={option.name}
                                        onClick={() => onClick(option)}
                                        className="flex cursor-pointer items-center gap-x-2 text-gray-800"
                                    >
                                        {option.icon && (
                                            <option.icon
                                                className={'h-4 w-4'}
                                            />
                                        )}
                                        <span>{option.name}</span>
                                    </div>
                                );
                            })}
                        </div>
                    )}
                </div>

                <div className="mt-4 flex items-center justify-between gap-x-2 border-t pt-2">
                    <Button
                        className="w-full font-medium"
                        variant="outline"
                        size={'sm'}
                    >
                        <span className="text-xs">Cancel</span>
                    </Button>
                    <Button className="w-full font-medium" size={'sm'}>
                        <span className="text-xs">Apply</span>
                    </Button>
                </div>
            </PopoverContent>
        </Popover>
    );
};

export default Filters;
