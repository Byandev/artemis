import { SidebarTrigger } from '@/components/ui/sidebar';
import { NavUser } from '@/components/nav-user';
import { Button } from "@/components/ui/button"
import { DropdownMenu, DropdownMenuContent, DropdownMenuTrigger } from "@/components/ui/dropdown-menu"
import { Bell } from "lucide-react"
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
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button variant="ghost" className="h-8 w-8 p-1 rounded-full bg-neutral-200 text-black dark:bg-neutral-700 dark:text-white">
                                <Bell />
                            </Button>
                        </DropdownMenuTrigger>

                        <DropdownMenuContent className="w-80">
                            <div className="p-2 space-y-2">
                                <p className="text-sm font-semibold">Notifications</p>

                                <div className="text-sm">
                                    <p>You have a new message</p>
                                    <p className="text-muted-foreground text-xs">2 minutes ago</p>
                                </div>

                                <div className="text-sm">
                                    <p>Your order has shipped</p>
                                    <p className="text-muted-foreground text-xs">1 hour ago</p>
                                </div>
                            </div>
                        </DropdownMenuContent>
                    </DropdownMenu>
                    <NavUser />
                </div>
            </div>
        </header>
    );
}
