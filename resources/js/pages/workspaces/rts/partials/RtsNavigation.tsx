import React, { useMemo } from 'react';
import { Workspace } from '@/types/models/Workspace';
import { Link, usePage } from '@inertiajs/react';
import { extractPathFromUrl } from '@/lib/utils';

type Tab = {
    key: string;
    label: string;
    href: string;
};

const TabItem = ({ href, label, isActive }: { href: string; label: React.ReactNode; isActive: boolean }) => {
    return (
        <Link
            href={href}
            className={`inline-flex items-center border-b-2 px-2.5 py-2 text-sm font-medium transition-colors duration-200 ease-in-out ${isActive
                    ? 'text-brand-500 dark:text-brand-400 border-brand-500 dark:border-brand-400'
                    : 'bg-transparent text-gray-500 border-transparent hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'
                }`}
            aria-current={isActive ? 'page' : undefined}
            role="tab"
        >
            {label}
        </Link>
    );
};

const RtsNavigation = ({ workspace }: { workspace: Workspace }) => {
    const { url } = usePage();

    const currentPath = useMemo(() => extractPathFromUrl(url), [url]);

    const tabs: Tab[] = useMemo(
        () => [
            { key: 'analytics', label: 'Analytics', href: `/workspaces/${workspace.slug}/rts/analytics` },
            { key: 'for-delivery-today', label: 'For Delivery Today', href: `/workspaces/${workspace.slug}/rts/for-delivery-today` },
            { key: 'parcel-journey-notifications', label: 'Parcel Updates', href: `/workspaces/${workspace.slug}/rts/parcel-journey-notifications` },
        ],
        [workspace.slug]
    );

    const activeKey = useMemo(() => {
        if (currentPath.includes('/rts/analytics')) return 'analytics';
        if (currentPath.includes('/rts/for-delivery-today')) return 'for-delivery-today';
        if (currentPath.includes('/rts/parcel-journey-notifications')) return 'parcel-journey-notifications';
        return 'analytics';
    }, [currentPath]);

    return (
        <div className="border-b border-gray-200 dark:border-gray-800">
            <nav className="-mb-px flex space-x-2 overflow-x-auto [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-thumb]:bg-gray-200 dark:[&::-webkit-scrollbar-thumb]:bg-gray-600 dark:[&::-webkit-scrollbar-track]:bg-transparent [&::-webkit-scrollbar]:h-1.5" role="tablist" aria-label="RTS navigation">
                {tabs.map((t) => (
                    <TabItem key={t.key} href={t.href} label={t.label} isActive={t.key === activeKey} />
                ))}
            </nav>
        </div>
    );
};

export default RtsNavigation;
