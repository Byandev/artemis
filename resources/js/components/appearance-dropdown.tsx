import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useAppearance } from '@/hooks/use-appearance';
import { Monitor, Moon, Sun } from 'lucide-react';
import { HTMLAttributes } from 'react';

export default function AppearanceToggleDropdown({
    className = '',
    ...props
}: HTMLAttributes<HTMLDivElement>) {
    const { appearance, updateAppearance } = useAppearance();

    const getCurrentIcon = () => {
        switch (appearance) {
            case 'dark':
                return <Moon className="h-5 w-5" />;
            case 'light':
                return <Sun className="h-5 w-5" />;
            default:
                return <Monitor className="h-5 w-5" />;
        }
    };

    return (
        <div className={className} {...props}>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button
                        variant="ghost"
                        size="icon"
                        className="h-9 w-9 rounded-[10px] border border-black/8 dark:border-white/8 bg-white dark:bg-zinc-900 shadow-[0_1px_3px_rgba(0,0,0,0.06),0_1px_2px_rgba(0,0,0,0.04)] dark:shadow-none hover:border-black/14 dark:hover:border-white/14 text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300 transition-all duration-150 [&_svg]:h-3.5 [&_svg]:w-3.5"
                    >
                        {getCurrentIcon()}
                        <span className="sr-only">Toggle theme</span>
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" className="rounded-[12px] border border-black/6 dark:border-white/6 bg-white dark:bg-zinc-900 shadow-[0_8px_30px_rgba(0,0,0,0.08)] dark:shadow-[0_8px_30px_rgba(0,0,0,0.4)] min-w-36 p-1">
                    <DropdownMenuItem onClick={() => updateAppearance('light')} className={`flex items-center gap-2 rounded-[8px] text-[13px] cursor-pointer ${appearance === 'light' ? 'text-emerald-700 dark:text-emerald-400 bg-emerald-500/[0.06]' : 'text-gray-600 dark:text-gray-400'}`}>
                        <Sun className="h-3.5 w-3.5" />
                        Light
                    </DropdownMenuItem>
                    <DropdownMenuItem onClick={() => updateAppearance('dark')} className={`flex items-center gap-2 rounded-[8px] text-[13px] cursor-pointer ${appearance === 'dark' ? 'text-emerald-700 dark:text-emerald-400 bg-emerald-500/[0.06]' : 'text-gray-600 dark:text-gray-400'}`}>
                        <Moon className="h-3.5 w-3.5" />
                        Dark
                    </DropdownMenuItem>
                    <DropdownMenuItem onClick={() => updateAppearance('system')} className={`flex items-center gap-2 rounded-[8px] text-[13px] cursor-pointer ${appearance === 'system' ? 'text-emerald-700 dark:text-emerald-400 bg-emerald-500/[0.06]' : 'text-gray-600 dark:text-gray-400'}`}>
                        <Monitor className="h-3.5 w-3.5" />
                        System
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        </div>
    );
}
