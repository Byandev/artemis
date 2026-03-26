import { SidebarTrigger } from '@/components/ui/sidebar';
import { NavUser } from '@/components/nav-user';
import WorkspaceSwitcher from './workspace-switcher';
import AppearanceToggleDropdown from '@/components/appearance-dropdown';

export function AppSidebarHeader() {
    return (
        <header className="flex h-16 shrink-0 items-center gap-2 border-b border-black/6 dark:border-white/6 bg-white dark:bg-zinc-900 px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4">
            <div className="flex items-center w-full gap-2">
                <div className="flex items-center gap-2">
                    <SidebarTrigger className="-ml-1" />
                    <WorkspaceSwitcher />
                </div>

                <div className="flex items-center gap-2 ml-auto">
                    <AppearanceToggleDropdown />
                    <NavUser />
                </div>
            </div>
        </header>
    );
}
