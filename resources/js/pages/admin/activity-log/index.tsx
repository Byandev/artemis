import { router, usePage } from '@inertiajs/react';
import React, { useState } from 'react';

const EVENT_TYPES = [
    { value: 'created', label: 'Created' },
    { value: 'updated', label: 'Updated' },
    { value: 'deleted', label: 'Deleted' },
    { value: 'restored', label: 'Restored' },
    { value: 'logged in', label: 'Logged In' },
    { value: 'logged out', label: 'Logged Out' },
];

export default function AdminActivityLogPage() {
    const { props, url } = usePage();
    const { activities, workspaces = [], filters = {} } = props as any;
    const [workspace, setWorkspace] = useState(filters.workspace || '');
    const [eventType, setEventType] = useState(filters.event || '');
    const [causer, setCauser] = useState(filters.causer || '');
    const [fromDate, setFromDate] = useState(filters.from || '');
    const [toDate, setToDate] = useState(filters.to || '');

    const handleFilter = (e: React.FormEvent) => {
        e.preventDefault();
        const params = new URLSearchParams();
        if (workspace) params.append('workspace', workspace);
        if (eventType) params.append('event', eventType);
        if (causer) params.append('causer', causer);
        if (fromDate) params.append('from', fromDate);
        if (toDate) params.append('to', toDate);
        router.get(`${url}?${params.toString()}`);
    };

    const handleClear = () => {
        router.get(url);
    };

    return (
        <div className="space-y-6">
            <div>
                <h1 className="text-3xl font-bold">Global Activity Log</h1>
                <p className="mt-1 text-gray-600">
                    View all activities across all workspaces
                </p>
            </div>

            <div className="rounded-lg border border-gray-200 bg-white p-6">
                <h2 className="mb-4 text-lg font-semibold">Filters</h2>
                <form onSubmit={handleFilter} className="space-y-4">
                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-5">
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">
                                Workspace
                            </label>
                            <select
                                value={workspace}
                                onChange={(e) => setWorkspace(e.target.value)}
                                className="w-full rounded-md border border-gray-300 px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:outline-none"
                            >
                                <option value="">All Workspaces</option>
                                {workspaces.map((w: any) => (
                                    <option key={w.id} value={w.id}>
                                        {w.name}
                                    </option>
                                ))}
                            </select>
                        </div>

                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">
                                Event Type
                            </label>
                            <select
                                value={eventType}
                                onChange={(e) => setEventType(e.target.value)}
                                className="w-full rounded-md border border-gray-300 px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:outline-none"
                            >
                                <option value="">All Events</option>
                                {EVENT_TYPES.map((t) => (
                                    <option key={t.value} value={t.value}>
                                        {t.label}
                                    </option>
                                ))}
                            </select>
                        </div>

                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">
                                Causer (User)
                            </label>
                            <input
                                type="text"
                                placeholder="Search by name..."
                                value={causer}
                                onChange={(e) => setCauser(e.target.value)}
                                className="w-full rounded-md border border-gray-300 px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:outline-none"
                            />
                        </div>

                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">
                                From Date
                            </label>
                            <input
                                type="date"
                                value={fromDate}
                                onChange={(e) => setFromDate(e.target.value)}
                                className="w-full rounded-md border border-gray-300 px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:outline-none"
                            />
                        </div>

                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">
                                To Date
                            </label>
                            <input
                                type="date"
                                value={toDate}
                                onChange={(e) => setToDate(e.target.value)}
                                className="w-full rounded-md border border-gray-300 px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:outline-none"
                            />
                        </div>
                    </div>

                    <div className="flex gap-2">
                        <button
                            type="submit"
                            className="rounded-md bg-blue-600 px-4 py-2 text-white transition hover:bg-blue-700"
                        >
                            Apply Filters
                        </button>
                        <button
                            type="button"
                            onClick={handleClear}
                            className="rounded-md bg-gray-200 px-4 py-2 text-gray-700 transition hover:bg-gray-300"
                        >
                            Clear
                        </button>
                    </div>
                </form>
            </div>

            <div className="overflow-hidden rounded-lg border border-gray-200 bg-white">
                <div className="overflow-x-auto">
                    <table className="w-full">
                        <thead className="border-b bg-gray-50">
                            <tr>
                                <th className="px-6 py-3 text-left text-sm font-semibold text-gray-900">
                                    Timestamp
                                </th>
                                <th className="px-6 py-3 text-left text-sm font-semibold text-gray-900">
                                    Workspace
                                </th>
                                <th className="px-6 py-3 text-left text-sm font-semibold text-gray-900">
                                    Event
                                </th>
                                <th className="px-6 py-3 text-left text-sm font-semibold text-gray-900">
                                    User
                                </th>
                                <th className="px-6 py-3 text-left text-sm font-semibold text-gray-900">
                                    Description
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {activities.data.map((a: any) => (
                                <tr key={a.id} className="hover:bg-gray-50">
                                    <td className="px-6 py-4 text-sm text-gray-600">
                                        {new Date(
                                            a.created_at,
                                        ).toLocaleString()}
                                    </td>
                                    <td className="px-6 py-4 text-sm text-gray-600">
                                        {a.workspace?.name ?? 'Global'}
                                    </td>
                                    <td className="px-6 py-4 text-sm">
                                        <span className="inline-flex rounded-full bg-blue-100 px-2 py-1 text-xs font-semibold text-blue-800">
                                            {a.event}
                                        </span>
                                    </td>
                                    <td className="px-6 py-4 text-sm text-gray-600">
                                        {a.causer?.name ?? 'System'}
                                    </td>
                                    <td className="px-6 py-4 text-sm text-gray-600">
                                        {a.description}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                {activities.data.length === 0 && (
                    <div className="px-6 py-12 text-center text-gray-500">
                        No activities found. Try adjusting your filters.
                    </div>
                )}
            </div>

            {activities.links && activities.links.length > 1 && (
                <div className="flex justify-center gap-2">
                    {activities.links.map((link: any, i: number) => (
                        <button
                            key={i}
                            onClick={() => link.url && router.get(link.url)}
                            disabled={!link.url}
                            className={`rounded-md px-3 py-2 text-sm ${
                                link.active
                                    ? 'bg-blue-600 text-white'
                                    : 'bg-gray-200 text-gray-700 hover:bg-gray-300'
                            } ${!link.url ? 'cursor-not-allowed opacity-50' : ''}`}
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    ))}
                </div>
            )}
        </div>
    );
}
