import AppearanceToggleDropdown from '@/components/appearance-dropdown';
import { NavUser } from '@/components/nav-user';
import TeamSwitcher from '@/components/team-switcher';
import { SidebarTrigger } from '@/components/ui/sidebar';
import WorkspaceSwitcher from './workspace-switcher';

export function AppSidebarHeader() {
    return (
        <header className="flex shrink-0 items-center gap-2 border-b border-black/6 bg-white px-6 py-2 transition-[width,height] ease-linear sm:h-16 sm:py-0 sm:group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4 dark:border-white/6 dark:bg-zinc-900">
            <div className="flex w-full min-w-0 items-center gap-1.5">
                <div className="flex min-w-0 items-center gap-1.5">
                    <SidebarTrigger className="-ml-1" />
                    <WorkspaceSwitcher />
                    <TeamSwitcher />
                </div>

                <div className="ml-auto flex min-w-0 items-center gap-1.5">
                    <div className="hidden sm:flex">
                        <AppearanceToggleDropdown />
                    </div>
                    <NavUser />
                </div>
            </div>
        </header>
    );
}
