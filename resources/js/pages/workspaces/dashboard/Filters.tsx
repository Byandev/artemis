import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { Button } from '@/components/ui/button'

import {
    Package,
    Store,
    Users,
    Filter
} from 'lucide-react';

const Filters  = () => {
    const options = [
        { name: 'Teams', icon: Users },
        { name: 'Products', icon: Package },
        { name: 'Shop', icon: Store },
        { name: 'Pages', icon: Store },
        { name: 'User', icon: Users },
    ];

    return (
        <Popover>
            <PopoverTrigger asChild>
                <Button variant="outline" className='text-gray-800'>
                    <Filter />
                    <span className='font-medium'>Filters</span>
                </Button>
            </PopoverTrigger>
            <PopoverContent className="w-48">
                <div className="space-y-4 text-sm">
                    {options.map((option) => {
                        return (
                            <div
                                className="flex items-center gap-x-2 text-gray-800 cursor-pointer"
                                key={option.name}
                            >
                                {option.icon && (
                                    <option.icon className={'h-4 w-4'} />
                                )}
                                {option.name}
                            </div>
                        );
                    })}
                </div>
            </PopoverContent>
        </Popover>
    );
}

export default Filters;
