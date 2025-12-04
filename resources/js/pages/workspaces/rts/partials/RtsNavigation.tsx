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
            className={`px-3 py-1 rounded-md font-medium focus:outline-none focus:ring-2 focus:ring-offset-1 ${isActive ? 'bg-white shadow-sm' : 'hover:bg-white/50'
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
            { key: 'for-delivery-today', label: 'For Delivery today', href: `/workspaces/${workspace.slug}/rts/for-delivery-today` },
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
        <div className="mb-2 w-full md:w-fit" role="tablist" aria-label="RTS navigation">
            <div className="flex gap-2 bg-gray-100 p-1 rounded-md
                            w-full sm:w-auto
                            overflow-x-auto md:overflow-visible
                            whitespace-nowrap">
                {tabs.map((t) => (
                    <div key={t.key} className="flex-shrink-0">
                        <TabItem href={t.href} label={t.label} isActive={t.key === activeKey} />
                    </div>
                ))}
            </div>
        </div>
    );
};

export default RtsNavigation;
