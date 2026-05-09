import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { UserMenuContent } from '@/components/user-menu-content';
import { useInitials } from '@/hooks/use-initials';
import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import { ChevronDown } from 'lucide-react';

export function NavUser() {
    const { auth } = usePage<SharedData>().props;
    const getInitials = useInitials();

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <button className="inline-flex h-8 min-w-0 max-w-full items-center gap-2 rounded-[8px] border border-black/8 dark:border-white/8 bg-black/[0.04] dark:bg-white/[0.05] px-2 transition-colors duration-150 outline-none cursor-pointer hover:bg-black/[0.07] dark:hover:bg-white/[0.08]">
                    <div className="flex h-5 w-5 items-center justify-center rounded-[5px] bg-emerald-500 font-mono text-[10px] font-bold text-white shrink-0">
                        {getInitials(auth.user.name)}
                    </div>
                    <span className="min-w-0 truncate font-mono text-[12px] font-medium text-gray-800 dark:text-gray-100">
                        {auth.user.name}
                    </span>
                    <ChevronDown className="ml-1 hidden h-3.5 w-3.5 text-gray-400 dark:text-gray-500 shrink-0 sm:block" />
                </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent className="w-56" align="end" side="bottom">
                <UserMenuContent user={auth.user} />
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
