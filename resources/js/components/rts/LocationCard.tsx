import { useEffect, useMemo, useRef, useState } from 'react';
import { omit } from 'lodash';
import { ColumnDef } from '@tanstack/react-table';
import { DataTable, SortableHeader } from '@/components/ui/data-table';
import { PaginatedData } from '@/types';
import { toFrontendSort } from '@/lib/sort';
import { buildBaseParams, CityRow, ProvinceRow, RtsCell, RtsQueryParams } from './rts-shared';

type GroupBy = 'province' | 'city';

interface Props {
    workspaceSlug: string;
    queryParams: RtsQueryParams;
}

export default function LocationCard({ workspaceSlug, queryParams }: Props) {
    const [groupBy, setGroupBy] = useState<GroupBy>('province');

    const [provinces, setProvinces] = useState<PaginatedData<ProvinceRow> | null>(null);
    const [provincesLoading, setProvincesLoading] = useState(true);
    const [provinceSort, setProvinceSort] = useState('-total_orders');
    const [provinceSearch, setProvinceSearch] = useState('');

    const [cities, setCities] = useState<PaginatedData<CityRow> | null>(null);
    const [citiesLoading, setCitiesLoading] = useState(true);
    const [citySort, setCitySort] = useState('-total_orders');
    const [citySearch, setCitySearch] = useState('');

    const fetchProvinces = (page = 1, search = provinceSearch, sort = provinceSort) => {
        setProvincesLoading(true);
        const p = buildBaseParams(queryParams);
        p.append('page', String(page));
        p.append('per_page', '10');
        if (search) p.append('search', search);
        p.append('sort', sort);
        fetch(`/workspaces/${workspaceSlug}/rts/analytics/group-by/provinces?${p}`, { credentials: 'same-origin' })
            .then((res) => (res.ok ? res.json() : null))
            .then((data) => { setProvinces(data); setProvincesLoading(false); })
            .catch(() => setProvincesLoading(false));
    };

    const fetchCities = (page = 1, search = citySearch, sort = citySort) => {
        setCitiesLoading(true);
        const p = buildBaseParams(queryParams);
        p.append('page', String(page));
        p.append('per_page', '10');
        if (search) p.append('search', search);
        p.append('sort', sort);
        fetch(`/workspaces/${workspaceSlug}/rts/analytics/group-by/cities?${p}`, { credentials: 'same-origin' })
            .then((res) => (res.ok ? res.json() : null))
            .then((data) => { setCities(data); setCitiesLoading(false); })
            .catch(() => setCitiesLoading(false));
    };

    // eslint-disable-next-line react-hooks/exhaustive-deps
    useEffect(() => {
        fetchProvinces(1, '', provinceSort);
        fetchCities(1, '', citySort);
    }, [workspaceSlug, JSON.stringify(queryParams)]);

    useEffect(() => {
        if (groupBy === 'province') fetchProvinces(1);
        else fetchCities(1);
    // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [groupBy]);

    const provinceSearchTimer = useRef<ReturnType<typeof setTimeout> | null>(null);
    useEffect(() => {
        if (provinceSearchTimer.current) clearTimeout(provinceSearchTimer.current);
        provinceSearchTimer.current = setTimeout(() => fetchProvinces(1, provinceSearch), 400);
        return () => { if (provinceSearchTimer.current) clearTimeout(provinceSearchTimer.current); };
    // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [provinceSearch]);

    const citySearchTimer = useRef<ReturnType<typeof setTimeout> | null>(null);
    useEffect(() => {
        if (citySearchTimer.current) clearTimeout(citySearchTimer.current);
        citySearchTimer.current = setTimeout(() => fetchCities(1, citySearch), 400);
        return () => { if (citySearchTimer.current) clearTimeout(citySearchTimer.current); };
    // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [citySearch]);

    const provinceColumns: ColumnDef<ProvinceRow>[] = useMemo(() => [
        {
            accessorKey: 'province_name',
            header: ({ column }) => <SortableHeader column={column} title="Province" />,
            cell: ({ row }) => row.original.province_name || <span className="text-gray-400">Unknown</span>,
        },
        {
            accessorKey: 'total_orders',
            header: ({ column }) => <SortableHeader column={column} title="Total Orders" />,
        },
        {
            accessorKey: 'delivered_count',
            header: ({ column }) => <SortableHeader column={column} title="Delivered" />,
            cell: ({ row }) => <span className="text-green-600 dark:text-green-400">{row.original.delivered_count}</span>,
        },
        {
            accessorKey: 'returned_count',
            header: ({ column }) => <SortableHeader column={column} title="Returned" />,
            cell: ({ row }) => <span className="text-red-500">{row.original.returned_count}</span>,
        },
        {
            accessorKey: 'rts_rate_percentage',
            header: ({ column }) => <SortableHeader column={column} title="RTS Rate" />,
            cell: ({ row }) => <RtsCell value={row.original.rts_rate_percentage} />,
        },
    ], []);

    const cityColumns: ColumnDef<CityRow>[] = useMemo(() => [
        {
            accessorKey: 'city_name',
            header: ({ column }) => <SortableHeader column={column} title="City" />,
            cell: ({ row }) => row.original.city_name || <span className="text-gray-400">Unknown</span>,
        },
        {
            accessorKey: 'province_name',
            header: ({ column }) => <SortableHeader column={column} title="Province" />,
            cell: ({ row }) => <span className="text-gray-500 dark:text-gray-400">{row.original.province_name || '—'}</span>,
        },
        {
            accessorKey: 'total_orders',
            header: ({ column }) => <SortableHeader column={column} title="Total Orders" />,
        },
        {
            accessorKey: 'delivered_count',
            header: ({ column }) => <SortableHeader column={column} title="Delivered" />,
            cell: ({ row }) => <span className="text-green-600 dark:text-green-400">{row.original.delivered_count}</span>,
        },
        {
            accessorKey: 'returned_count',
            header: ({ column }) => <SortableHeader column={column} title="Returned" />,
            cell: ({ row }) => <span className="text-red-500">{row.original.returned_count}</span>,
        },
        {
            accessorKey: 'rts_rate_percentage',
            header: ({ column }) => <SortableHeader column={column} title="RTS Rate" />,
            cell: ({ row }) => <RtsCell value={row.original.rts_rate_percentage} />,
        },
    ], []);

    return (
        <div className="rounded-2xl border border-black/6 dark:border-white/6 bg-white dark:bg-zinc-900">
            <div className="flex items-center justify-between border-b border-black/6 dark:border-white/6 px-5 py-4">
                <div>
                    <h2 className="text-[14px] font-semibold text-gray-900 dark:text-gray-100">
                        {groupBy === 'province' ? 'By Province' : 'By City'}
                    </h2>
                    <p className="mt-0.5 text-[12px] text-gray-400 dark:text-gray-500">
                        {groupBy === 'province' ? 'RTS rate grouped by province' : 'RTS rate grouped by city'}
                    </p>
                </div>
                <div className="flex items-center gap-3">
                    <div className="relative">
                        <svg className="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-gray-300 dark:text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                            <path strokeLinecap="round" strokeLinejoin="round" d="M21 21l-4.35-4.35M17 11A6 6 0 111 11a6 6 0 0116 0z" />
                        </svg>
                        {groupBy === 'province' ? (
                            <input
                                type="text"
                                value={provinceSearch}
                                onChange={(e) => setProvinceSearch(e.target.value)}
                                placeholder="Search province…"
                                className="h-8 w-48 rounded-lg border border-black/8 bg-stone-50 pl-8 pr-3 text-[12px]! text-gray-700 placeholder-gray-300 outline-none transition-colors focus:border-black/20 dark:border-white/8 dark:bg-white/3 dark:text-gray-300 dark:placeholder-gray-600 dark:focus:border-white/20"
                            />
                        ) : (
                            <input
                                type="text"
                                value={citySearch}
                                onChange={(e) => setCitySearch(e.target.value)}
                                placeholder="Search city or province…"
                                className="h-8 w-52 rounded-lg border border-black/8 bg-stone-50 pl-8 pr-3 text-[12px]! text-gray-700 placeholder-gray-300 outline-none transition-colors focus:border-black/20 dark:border-white/8 dark:bg-white/3 dark:text-gray-300 dark:placeholder-gray-600 dark:focus:border-white/20"
                            />
                        )}
                    </div>
                    <div className="flex overflow-hidden rounded-lg border border-black/8 text-[12px] font-medium dark:border-white/8">
                        <button
                            onClick={() => setGroupBy('province')}
                            className={`px-3 py-1.5 transition-colors ${groupBy === 'province' ? 'bg-gray-100 text-gray-900 dark:bg-white/10 dark:text-gray-100' : 'text-gray-400 hover:text-gray-700 dark:text-gray-500 dark:hover:text-gray-300'}`}
                        >
                            Province
                        </button>
                        <button
                            onClick={() => setGroupBy('city')}
                            className={`border-l border-black/8 px-3 py-1.5 transition-colors dark:border-white/8 ${groupBy === 'city' ? 'bg-gray-100 text-gray-900 dark:bg-white/10 dark:text-gray-100' : 'text-gray-400 hover:text-gray-700 dark:text-gray-500 dark:hover:text-gray-300'}`}
                        >
                            City
                        </button>
                    </div>
                </div>
            </div>
            <div className="p-4">
                {groupBy === 'province' ? (
                    provincesLoading ? (
                        <div className="flex h-32 items-center justify-center text-[13px] text-gray-400">Loading…</div>
                    ) : (
                        <DataTable
                            columns={provinceColumns}
                            data={provinces?.data ?? []}
                            enableInternalPagination={false}
                            meta={provinces ? { ...omit(provinces, ['data']) } : undefined}
                            initialSorting={toFrontendSort(provinceSort)}
                            onFetch={(params) => {
                                const s = params?.sort as string ?? '-total_orders';
                                setProvinceSort(s);
                                fetchProvinces(Number(params?.page ?? 1), provinceSearch, s);
                            }}
                        />
                    )
                ) : (
                    citiesLoading ? (
                        <div className="flex h-32 items-center justify-center text-[13px] text-gray-400">Loading…</div>
                    ) : (
                        <DataTable
                            columns={cityColumns}
                            data={cities?.data ?? []}
                            enableInternalPagination={false}
                            meta={cities ? { ...omit(cities, ['data']) } : undefined}
                            initialSorting={toFrontendSort(citySort)}
                            onFetch={(params) => {
                                const s = params?.sort as string ?? '-total_orders';
                                setCitySort(s);
                                fetchCities(Number(params?.page ?? 1), citySearch, s);
                            }}
                        />
                    )
                )}
            </div>
        </div>
    );
}
