import AppLayout from '@/layouts/app-layout';
import type { ReactNode } from 'react';
import { Link, usePage } from '@inertiajs/react';
import { Workspace } from '@/types/models/Workspace';

interface LayoutProps {
    children: ReactNode;
    workspace: Workspace
}

const Layout = ({  children, workspace }: LayoutProps) => {
    const page = usePage();

    return (
        <AppLayout>
            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
                <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                    <h2
                        className="text-[22px] font-semibold tracking-tight text-gray-900 dark:text-gray-100"
                        x-text="pageName"
                    >
                        Products
                    </h2>
                </div>

                <div className="">
                    <div className="border-b border-gray-200 dark:border-gray-800">
                        <nav className="-mb-px flex space-x-2 overflow-x-auto [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-thumb]:bg-gray-200 dark:[&::-webkit-scrollbar-thumb]:bg-gray-600 dark:[&::-webkit-scrollbar-track]:bg-transparent [&::-webkit-scrollbar]:h-1.5">
                            <Link
                                href={`/workspaces/${workspace.slug}/products/analytics`}
                                className={`inline-flex items-center border-b-2 px-2.5 py-2 text-sm font-medium transition-colors duration-200 ease-in-out ${
                                page.url.includes("/analytics")
                                    ? "text-brand-500 dark:text-brand-400 border-brand-500 dark:border-brand-400"
                                    : "bg-transparent text-gray-500 border-transparent hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                            }`}>
                                Analytics
                            </Link>

                            <Link
                                href={`/workspaces/${workspace.slug}/products/list`}
                                className={`inline-flex items-center border-b-2 px-2.5 py-2 text-sm font-medium transition-colors duration-200 ease-in-out ${
                                page.url.includes("/list") || page.url.includes("/create") || page.url.includes("/edit")
                                    ? "text-brand-500 dark:text-brand-400 border-brand-500 dark:border-brand-400"
                                    : "bg-transparent text-gray-500 border-transparent hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                            }`}>
                                Products
                            </Link>
                        </nav>
                    </div>

                    <div className="pt-4 dark:border-gray-800">
                        {children}
                    </div>
                </div>
            </div>
        </AppLayout>
    )
}

export default Layout;
