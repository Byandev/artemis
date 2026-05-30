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

    return (
        <div className="flex h-screen w-full flex-col bg-gray-50 text-gray-900 dark:bg-[#09090b] dark:text-gray-100">
            {/* Slim top bar */}
            <header className="flex h-11 shrink-0 items-center gap-3 border-b border-black/8 bg-white px-4 dark:border-white/8 dark:bg-zinc-950">
                {/* Mobile menu */}
                <div className="lg:hidden">
                    <Sheet>
                        <SheetTrigger asChild>
                            <Button
                                variant="ghost"
                                size="icon"
                                className="h-8 w-8"
                            >
                                <Menu className="h-4 w-4" />
                            </Button>
                        </SheetTrigger>
                        <SheetContent
                            side="left"
                            className="flex h-full w-60 flex-col bg-white dark:bg-zinc-950"
                        >
                            <SheetTitle className="sr-only">
                                Navigation Menu
                            </SheetTitle>
                            <SheetHeader className="flex justify-start text-left">
                                <AppLogoIcon className="h-5 w-5 fill-current text-black dark:text-white" />
                            </SheetHeader>
                            <div className="flex flex-col gap-1 p-3">
                                {csrNavItems.map((item) => (
                                    <Link
                                        key={item.title}
                                        href={item.href}
                                        className={cn(
                                            'flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium transition-colors',
                                            page.url.startsWith(typeof item.href === 'string' ? item.href : '')
                                                ? 'bg-gray-100 text-gray-900 dark:bg-zinc-800 dark:text-white'
                                                : 'text-gray-500 hover:bg-gray-50 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-zinc-800/50 dark:hover:text-white',
                                        )}
                                    >
                                        {item.icon && (
                                            <Icon
                                                iconNode={item.icon}
                                                className="h-4 w-4"
                                            />
                                        )}
                                        {item.title}
                                    </Link>
                                ))}
                            </div>
                        </SheetContent>
                    </Sheet>
                </div>

                {/* Logo + label */}
                <Link
                    href={`/workspaces/${slug}/csr/dashboard`}
                    className="flex shrink-0 items-center gap-2"
                >
                    <AppLogoIcon className="h-5 w-5 fill-current text-black dark:text-white" />
                    <span className="hidden text-[13px] font-semibold tracking-tight text-gray-800 dark:text-gray-100 sm:block">
                        CSR Portal
                    </span>
                </Link>

                <div className="hidden h-4 w-px bg-black/10 dark:bg-white/10 lg:block" />

                {/* Desktop nav */}
                <nav className="hidden items-center gap-1 lg:flex">
                    {csrNavItems.map((item) => {
                        const isActive = page.url.startsWith(typeof item.href === 'string' ? item.href : '');
                        return (
                            <Link
                                key={item.title}
                                href={item.href}
                                className={cn(
                                    'flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-[12px] font-medium transition-colors',
                                    isActive
                                        ? 'bg-gray-100 text-gray-900 dark:bg-zinc-800 dark:text-white'
                                        : 'text-gray-500 hover:bg-gray-50 hover:text-gray-800 dark:text-gray-400 dark:hover:bg-zinc-800/60 dark:hover:text-gray-200',
                                )}
                            >
                                {item.icon && (
                                    <Icon
                                        iconNode={item.icon}
                                        className="h-3.5 w-3.5"
                                    />
                                )}
                                {item.title}
                            </Link>
                        );
                    })}
                </nav>

                {/* Right actions */}
                <div className="ml-auto flex items-center gap-2">
                    <AppearanceToggleDropdown />
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <button className="flex h-7 items-center gap-2 rounded-full border border-black/8 bg-stone-50 pr-2.5 pl-0.5 transition-colors outline-none hover:bg-stone-100 dark:border-white/8 dark:bg-zinc-800 dark:hover:bg-zinc-700">
                                <div className="flex h-6 w-6 items-center justify-center rounded-full bg-emerald-500 font-mono text-[10px] font-bold text-white">
                                    {getInitials(auth.user.name)}
                                </div>
                                <span className="hidden max-w-[100px] truncate font-mono text-[11px] font-medium text-gray-700 sm:block dark:text-gray-300">
                                    {auth.user.name}
                                </span>
                            </button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent className="w-56" align="end">
                            <UserMenuContent user={auth.user} />
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </header>

            {/* Page content */}
            <main className="flex flex-1 flex-col overflow-y-auto">
                {children}
            </main>

            <SubscriptionExpiredModal />
            <Toaster position="top-right" richColors closeButton />
        </div>
    );
}
