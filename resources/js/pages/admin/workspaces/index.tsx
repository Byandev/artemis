import AdminSidebarLayout from '@/layouts/admin/admin-sidebar-layout';
import { Head, router } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import { Search, Globe, User, Files, LayoutGrid } from 'lucide-react';
import PageHeader from '@/components/common/PageHeader';
import ComponentCard from '@/components/common/ComponentCard';

interface Workspace {
    id: number;
    name: string;
    slug: string;
    owner?: { name: string };
    pages_count: number;
}

interface Props {
    workspaces: {
        data: Workspace[];
    };
    filters: {
        search: string;
    };
}

export default function Index({ workspaces, filters }: Props) {
    const [search, setSearch] = useState(filters.search || '');

    useEffect(() => {
        const delayDebounceFn = setTimeout(() => {
            if (search !== (filters.search || '')) {
                router.get(
                    '/admin/workspaces',
                    { search },
                    { preserveState: true, replace: true }
                );
            }
        }, 300);
        return () => clearTimeout(delayDebounceFn);
    }, [search]);

    return (
        <AdminSidebarLayout>
            <Head title="Admin | Workspaces" />

            <div className="p-4 md:p-6">
                {/* Aligned Page Header */}
                <PageHeader
                    title="Workspace Management"
                    description="Platform-wide overview of all created workspaces and their owners."
                >
                    {/* Search Input aligned with your Header's action area */}
                    <div className="relative w-full sm:w-64">
                        <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-zinc-400" />
                        <input
                            type="text"
                            placeholder="Search workspaces..."
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            className="w-full rounded-md border border-zinc-200 bg-white py-2 pl-10 pr-4 text-sm outline-none focus:ring-2 focus:ring-brand-500/20 dark:border-zinc-800 dark:bg-zinc-950"
                        />
                    </div>
                </PageHeader>

                {/* Main Content wrapped in ComponentCard to match Dashboard style */}
                <ComponentCard className="mt-6">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="border-b border-zinc-100 bg-zinc-50/30 dark:border-zinc-800 dark:bg-zinc-900/50">
                                <tr className="text-zinc-500 uppercase text-[11px] font-bold tracking-wider">
                                    <th className="px-6 py-4">Workspace Info</th>
                                    <th className="px-6 py-4">Primary Owner</th>
                                    <th className="px-6 py-4 text-center">Resources</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-zinc-100 dark:divide-zinc-800">
                                {workspaces.data.length > 0 ? (
                                    workspaces.data.map((ws) => (
                                        <tr key={ws.id} className="group hover:bg-zinc-50/50 dark:hover:bg-zinc-800/20 transition-colors">
                                            <td className="px-6 py-4">
                                                <div className="flex items-center gap-3">
                                                    <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-400">
                                                        <LayoutGrid className="h-4 w-4" />
                                                    </div>
                                                    <div>
                                                        <div className="font-semibold text-zinc-900 dark:text-zinc-100">{ws.name}</div>
                                                        <div className="text-xs text-zinc-500 font-mono tracking-tighter">/{ws.slug}</div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td className="px-6 py-4">
                                                <div className="flex items-center gap-2 text-zinc-600 dark:text-zinc-400">
                                                    <div className="h-2 w-2 rounded-full bg-brand-500" />
                                                    {ws.owner?.name || 'Platform Admin'}
                                                </div>
                                            </td>
                                            <td className="px-6 py-4 text-center">
                                                <div className="inline-flex items-center gap-1.5 rounded-full bg-brand-50/50 dark:bg-brand-500/10 px-3 py-1 text-xs font-bold text-brand-600 dark:text-brand-400 border border-brand-100 dark:border-brand-500/20">
                                                    <Files className="h-3 w-3" />
                                                    {ws.pages_count} Pages
                                                </div>
                                            </td>
                                        </tr>
                                    ))
                                ) : (
                                    <tr>
                                        <td colSpan={3} className="px-6 py-20 text-center">
                                            <div className="flex flex-col items-center gap-2">
                                                <p className="text-zinc-500 italic">No workspaces found matching "{search}"</p>
                                            </div>
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </ComponentCard>
            </div>
        </AdminSidebarLayout>
    );
}