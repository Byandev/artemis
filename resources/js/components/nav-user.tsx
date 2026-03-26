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
                <button className="flex h-9 items-center gap-2 rounded-xl border border-black/8 dark:border-white/8 bg-stone-50 dark:bg-zinc-800 pl-1 pr-2.5 transition-all hover:bg-stone-100 dark:hover:bg-zinc-700 outline-none cursor-pointer">
                    <div className="flex h-7 w-7 items-center justify-center rounded-lg bg-emerald-500 font-mono text-[11px] font-bold text-white shrink-0">
                        {getInitials(auth.user.name)}
                    </div>
                    <span className="font-mono text-[12px] font-semibold text-gray-800 dark:text-gray-100 max-w-[140px] truncate">
                        {auth.user.name}
                    </span>
                    <ChevronDown className="ml-1 h-3.5 w-3.5 text-gray-400 dark:text-gray-500 shrink-0" />
                </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent className="w-56" align="end" side="bottom">
                <UserMenuContent user={auth.user} />
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
