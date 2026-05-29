import AppLogo from '@/components/app-logo';
import AppLogoIcon from '@/components/app-logo-icon';
import AppearanceToggleDropdown from '@/components/appearance-dropdown';
import { Icon } from '@/components/icon';
import SubscriptionExpiredModal from '@/components/subscription-expired-modal';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    NavigationMenu,
    NavigationMenuItem,
    NavigationMenuList,
    navigationMenuTriggerStyle,
} from '@/components/ui/navigation-menu';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { UserMenuContent } from '@/components/user-menu-content';
import { useInitials } from '@/hooks/use-initials';
import { cn } from '@/lib/utils';
import { type NavItem, type SharedData } from '@/types';
import { Workspace } from '@/types/models/Workspace';
import { Link, usePage } from '@inertiajs/react';
import { LayoutDashboard, Menu, Truck } from 'lucide-react';
import { type ReactNode } from 'react';
import { Toaster } from 'sonner';

interface CsrLayoutProps {
    children: ReactNode;
}

export default function CsrLayout({ children }: CsrLayoutProps) {
    const page = usePage<SharedData>();
    const { auth, currentWorkspace } = page.props as any;
    const getInitials = useInitials();

    const slug = (currentWorkspace as Workspace | undefined)?.slug ?? '';

    const csrNavItems: NavItem[] = [
        {
            title: 'Dashboard',
            href: `/workspaces/${slug}/csr/dashboard`,
            icon: LayoutDashboard,
        },
        {
            title: 'RMO Management',
            href: `/workspaces/${slug}/csr/rmo-management`,
            icon: Truck,
        },
    ];

    const activeItemStyles =
        'text-neutral-900 dark:bg-neutral-800 dark:text-neutral-100';

    return (
        <div className="flex min-h-screen w-full flex-col bg-white text-gray-900 dark:bg-[#0F0F11] dark:text-gray-100">
            {/* Top bar */}
            <div className="border-b border-sidebar-border/80 dark:border-white/8">
                <div className="flex h-16 items-center px-4">
                    {/* Mobile menu */}
                    <div className="lg:hidden">
                        <Sheet>
                            <SheetTrigger asChild>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    className="mr-2 h-[34px] w-[34px]"
                                >
                                    <Menu className="h-5 w-5" />
                                </Button>
                            </SheetTrigger>
                            <SheetContent
                                side="left"
                                className="flex h-full w-64 flex-col items-stretch justify-between bg-white dark:bg-[#0F0F11]"
                            >
                                <SheetTitle className="sr-only">
                                    Navigation Menu
                                </SheetTitle>
                                <SheetHeader className="flex justify-start text-left">
                                    <AppLogoIcon className="h-6 w-6 fill-current text-black dark:text-white" />
                                </SheetHeader>
                                <div className="flex h-full flex-1 flex-col space-y-4 p-4">
                                    <div className="flex h-full flex-col justify-between text-sm">
                                        <div className="flex flex-col space-y-4">
                                            {csrNavItems.map((item) => (
                                                <Link
                                                    key={item.title}
                                                    href={item.href}
                                                    className="flex items-center space-x-2 font-medium text-gray-800 dark:text-gray-200"
                                                >
                                                    {item.icon && (
                                                        <Icon
                                                            iconNode={item.icon}
                                                            className="h-5 w-5"
                                                        />
                                                    )}
                                                    <span>{item.title}</span>
                                                </Link>
                                            ))}
                                        </div>
                                    </div>
                                </div>
                            </SheetContent>
                        </Sheet>
                    </div>

                    <Link
                        href={`/workspaces/${slug}/csr/dashboard`}
                        className="flex items-center space-x-2"
                    >
                        <AppLogo />
                    </Link>

                    {/* Desktop nav */}
                    <div className="ml-6 hidden h-full items-center space-x-6 lg:flex">
                        <NavigationMenu className="flex h-full items-stretch">
                            <NavigationMenuList className="flex h-full items-stretch space-x-2">
                                {csrNavItems.map((item, index) => (
                                    <NavigationMenuItem
                                        key={index}
                                        className="relative flex h-full items-center"
                                    >
                                        <Link
                                            href={item.href}
                                            className={cn(
                                                navigationMenuTriggerStyle(),
                                                page.url === item.href &&
                                                    activeItemStyles,
                                                'h-9 cursor-pointer px-3',
                                            )}
                                        >
                                            {item.icon && (
                                                <Icon
                                                    iconNode={item.icon}
                                                    className="mr-2 h-4 w-4"
                                                />
                                            )}
                                            {item.title}
                                        </Link>
                                        {page.url === item.href && (
                                            <div className="absolute bottom-0 left-0 h-0.5 w-full translate-y-px bg-black dark:bg-white" />
                                        )}
                                    </NavigationMenuItem>
                                ))}
                            </NavigationMenuList>
                        </NavigationMenu>
                    </div>

                    <div className="ml-auto flex min-w-0 items-center gap-1.5">
                        <div className="hidden sm:flex">
                            <AppearanceToggleDropdown />
                        </div>
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <button className="flex h-9 max-w-full min-w-0 items-center gap-2.5 rounded-full border border-black/8 bg-stone-50 pr-3 pl-1 transition-all outline-none hover:bg-stone-100 dark:border-white/8 dark:bg-zinc-800 dark:hover:bg-zinc-700">
                                    <div className="flex h-7 w-7 items-center justify-center rounded-full bg-emerald-500 font-mono text-[11px] font-bold text-white">
                                        {getInitials(auth.user.name)}
                                    </div>
                                    <span className="hidden min-w-0 truncate font-mono! text-[12px]! font-medium text-gray-700 sm:inline dark:text-gray-300">
                                        {auth.user.name}
                                    </span>
                                </button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent className="w-56" align="end">
                                <UserMenuContent user={auth.user} />
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                </div>
            </div>

            {/* Page content */}
            <main className="flex h-full w-full flex-1 flex-col">
                {children}
            </main>

            <SubscriptionExpiredModal />
            <Toaster position="top-right" richColors closeButton />
        </div>
    );
}
