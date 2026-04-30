import { SidebarTrigger } from '@/components/ui/sidebar';
import { NavUser } from '@/components/nav-user';
import WorkspaceSwitcher from './workspace-switcher';
import AppearanceToggleDropdown from '@/components/appearance-dropdown';
import { ContactSupportModal } from '@/components/contact-support-modal';
import { Button } from '@/components/ui/button';
import { LifeBuoy } from 'lucide-react';

export function AppSidebarHeader() {
    return (
        <header className="flex h-16 shrink-0 items-center gap-2 border-b border-black/6 dark:border-white/6 bg-white dark:bg-zinc-900 px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4">
            <div className="flex items-center w-full gap-2">
                <div className="flex items-center gap-2">
                    <SidebarTrigger className="-ml-1" />
                    <WorkspaceSwitcher />
                </div>

                <div className="flex items-center gap-2 ml-auto">
                    <ContactSupportModal
                        trigger={
                            <Button
                                variant="ghost"
                                size="icon"
                                className="h-9 w-9 rounded-full"
                                aria-label="Contact support"
                            >
                                <LifeBuoy className="h-4 w-4" />
                            </Button>
                        }
                    />
                    <AppearanceToggleDropdown />
                    <NavUser />
                </div>
            </div>
        </header>
    );
}
