import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { type SharedData } from '@/types';
import { Workspace } from '@/types/models/Workspace';
import { Link, usePage } from '@inertiajs/react';
import { Settings } from 'lucide-react';
import { HTMLAttributes } from 'react';

/**
 * Topbar shortcut to the settings area. Mirrors the appearance toggle styling
 * so the two sit together as a matched pair of header controls.
 */
export default function SettingsButton({
    className = '',
    ...props
}: HTMLAttributes<HTMLDivElement>) {
    const { currentWorkspace } = usePage<SharedData>().props;
    const workspaceSlug = (currentWorkspace as Workspace | undefined)?.slug;
    const href = workspaceSlug
        ? `/workspaces/${workspaceSlug}/settings`
        : '/settings';

    return (
        <div className={className} {...props}>
            <Tooltip>
                <TooltipTrigger asChild>
                    <Button
                        variant="ghost"
                        size="icon"
                        asChild
                        className="h-9 w-9 rounded-[10px] border border-black/8 bg-white text-gray-400 shadow-[0_1px_3px_rgba(0,0,0,0.06),0_1px_2px_rgba(0,0,0,0.04)] transition-all duration-150 hover:border-black/14 hover:text-gray-600 dark:border-white/8 dark:bg-zinc-900 dark:text-gray-500 dark:shadow-none dark:hover:border-white/14 dark:hover:text-gray-300 [&_svg]:h-3.5 [&_svg]:w-3.5"
                    >
                        <Link href={href}>
                            <Settings className="h-5 w-5" />
                            <span className="sr-only">Settings</span>
                        </Link>
                    </Button>
                </TooltipTrigger>
                <TooltipContent>Settings</TooltipContent>
            </Tooltip>
        </div>
    );
}
