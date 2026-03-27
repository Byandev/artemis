import AppLayout from '@/layouts/app-layout';
import PageHeader from '@/components/common/PageHeader';
import DatePicker from '@/components/ui/date-picker';
import Filters, { FilterValue } from '@/components/filters/Filters';
import { DataTable, SortableHeader } from '@/components/ui/data-table';
import RtsBreakdownChart from '@/components/charts/RtsBreakdownChart';
import { Workspace } from '@/types/models/Workspace';
import { Head } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import { formatDate } from 'date-fns';
import flatpickr from 'flatpickr';
import DateOption = flatpickr.Options.DateOption;
import moment from 'moment';
import { useCallback, useEffect, useRef, useState, useMemo } from 'react';
import { omit } from 'lodash';
import { PaginatedData } from '@/types';
import { toFrontendSort } from '@/lib/sort';
import { BarChart2, Table } from 'lucide-react';

type ViewMode = 'table' | 'chart';

type GroupBy = 'province' | 'city';

type DeliveryAttemptRow = {
    delivery_attempts: string | null;
    total_orders: number;
    delivered_count: number;
    returned_count: number;
    rts_rate_percentage: number;
};

type CxRtsRow = {
    cx_rts_bucket: string;
    total_orders: number;
    delivered_count: number;
    returned_count: number;
    rts_rate_percentage: number;
};

type OrderItemRow = {
    item_name: string | null;
    total_orders: number;
    delivered_count: number;
    returned_count: number;
    rts_rate_percentage: number;
};

type PriceRow = {
    price_bucket: string | null;
    total_orders: number;
    delivered_count: number;
    returned_count: number;
    rts_rate_percentage: number;
};

const PRICE_LABELS: Record<string, string> = {
    '0-250':     '₱0 – ₱250',
    '251-500':   '₱251 – ₱500',
    '501-750':   '₱501 – ₱750',
    '751-1000':  '₱751 – ₱1,000',
    '1001-1500': '₱1,001 – ₱1,500',
    '1501-2000': '₱1,501 – ₱2,000',
    '2001-3000': '₱2,001 – ₱3,000',
    '3001-5000': '₱3,001 – ₱5,000',
    '5000+':     '₱5,000+',
};

const CX_RTS_LABELS: Record<string, string> = {
    no_report: 'No Report',
    '0-10': '0 – 10%',
    '11-20': '11 – 20%',
    '21-30': '21 – 30%',
    '31-40': '31 – 40%',
    '41-50': '41 – 50%',
    '51-60': '51 – 60%',
    '61-70': '61 – 70%',
    '71-80': '71 – 80%',
    '81-90': '81 – 90%',
    '91-100': '91 – 100%',
};

interface Props {
    workspace: Workspace;
}

type ProvinceRow = {
    province_name: string;
    total_orders: number;
    delivered_count: number;
    returned_count: number;
    rts_rate_percentage: number;
};

type CityRow = {
    city_name: string;
    province_name: string;
    total_orders: number;
    delivered_count: number;
    returned_count: number;
    rts_rate_percentage: number;
};

function rtsColor(value: number) {
    if (value <= 15) return 'text-green-600 dark:text-green-400';
    if (value <= 20) return 'text-yellow-500 dark:text-yellow-400';
    if (value <= 25) return 'text-orange-500 dark:text-orange-400';
    return 'font-semibold text-red-500';
}


const RtsCell = ({ value }: { value: number }) => (
    <span className={rtsColor(value)}>{value}%</span>
);

const ViewToggle = ({ value, onChange }: { value: ViewMode; onChange: (v: ViewMode) => void }) => (
    <div className="flex rounded-lg border border-black/8 dark:border-white/8 overflow-hidden">
        <button
            onClick={() => onChange('table')}
            className={`flex items-center gap-1.5 px-2.5 py-1.5 transition-colors ${value === 'table' ? 'bg-gray-100 dark:bg-white/10 text-gray-900 dark:text-gray-100' : 'text-gray-400 dark:text-gray-500 hover:text-gray-700 dark:hover:text-gray-300'}`}
        >
            <Table className="h-3.5 w-3.5" />
        </button>
        <button
            onClick={() => onChange('chart')}
            className={`flex items-center gap-1.5 px-2.5 py-1.5 border-l border-black/8 dark:border-white/8 transition-colors ${value === 'chart' ? 'bg-gray-100 dark:bg-white/10 text-gray-900 dark:text-gray-100' : 'text-gray-400 dark:text-gray-500 hover:text-gray-700 dark:hover:text-gray-300'}`}
        >
            <BarChart2 className="h-3.5 w-3.5" />
        </button>
    </div>
);

export default function Analytics({ workspace }: Props) {
    const [dateRange, setDateRange] = useState([
        moment().startOf('month').format('YYYY-MM-DD'),
        moment().endOf('month').format('YYYY-MM-DD'),
    ]);
    const [filter, setFilter] = useState<FilterValue>({
        teamIds: [], productIds: [], shopIds: [], pageIds: [], userIds: [],
    });
    const [groupBy, setGroupBy] = useState<GroupBy>('province');

    const [provinces, setProvinces] = useState<PaginatedData<ProvinceRow> | null>(null);
    const [provincesLoading, setProvincesLoading] = useState(true);
    const [provinceSort, setProvinceSort] = useState('-total_orders');
    const [provinceSearch, setProvinceSearch] = useState('');

    const [cities, setCities] = useState<PaginatedData<CityRow> | null>(null);
    const [citiesLoading, setCitiesLoading] = useState(true);
    const [citySearch, setCitySearch] = useState('');
    const [citySort, setCitySort] = useState('-total_orders');

    const [deliveryAttempts, setDeliveryAttempts] = useState<DeliveryAttemptRow[]>([]);
    const [deliveryAttemptsLoading, setDeliveryAttemptsLoading] = useState(true);

    const [cxRts, setCxRts] = useState<CxRtsRow[]>([]);
    const [cxRtsLoading, setCxRtsLoading] = useState(true);
    const [cxRtsType, setCxRtsType] = useState<'latest' | 'initial'>('latest');

    const [orderItems, setOrderItems] = useState<PaginatedData<OrderItemRow> | null>(null);
    const [orderItemsLoading, setOrderItemsLoading] = useState(true);
    const [orderItemsSort, setOrderItemsSort] = useState('-total_orders');

    const [price, setPrice] = useState<PriceRow[]>([]);
    const [priceLoading, setPriceLoading] = useState(true);

    const [priceView, setPriceView] = useState<ViewMode>('chart');
    const [attemptsView, setAttemptsView] = useState<ViewMode>('chart');
    const [cxRtsView, setCxRtsView] = useState<ViewMode>('chart');

    const buildParams = useCallback(() => {
        const p = new URLSearchParams();
        p.append('start_date', dateRange[0]);
        p.append('end_date', dateRange[1]);
        filter.pageIds.forEach((id) => p.append('page_ids[]', String(id)));
        filter.shopIds.forEach((id) => p.append('shop_ids[]', String(id)));
        return p;
    }, [dateRange, filter]);

    const fetchProvinces = useCallback(async (page = 1, search = provinceSearch, sort = '-total_orders') => {
        setProvincesLoading(true);
        try {
            const p = buildParams();
            p.append('page', String(page));
            p.append('per_page', '10');
            if (search) p.append('search', search);
            if (sort) p.append('sort', sort);
            const res = await fetch(`/workspaces/${workspace.slug}/rts/analytics/group-by/provinces?${p}`, { credentials: 'same-origin' });
            if (res.ok) setProvinces(await res.json());
        } finally {
            setProvincesLoading(false);
        }
    }, [workspace.slug, buildParams, provinceSearch]);

    const fetchCities = useCallback(async (page = 1, search = citySearch, sort = '-total_orders') => {
        setCitiesLoading(true);
        try {
            const p = buildParams();
            p.append('page', String(page));
            p.append('per_page', '10');
            if (search) p.append('search', search);
            if (sort) p.append('sort', sort);
            const res = await fetch(`/workspaces/${workspace.slug}/rts/analytics/group-by/cities?${p}`, { credentials: 'same-origin' });
            if (res.ok) setCities(await res.json());
        } finally {
            setCitiesLoading(false);
        }
    }, [workspace.slug, buildParams, citySearch]);

    const fetchCxRts = useCallback(async (type = cxRtsType) => {
        setCxRtsLoading(true);
        try {
            const p = buildParams();
            p.append('type', type);
            const res = await fetch(`/workspaces/${workspace.slug}/rts/analytics/group-by/cx-rts?${p}`, { credentials: 'same-origin' });
            if (res.ok) setCxRts(await res.json());
        } finally {
            setCxRtsLoading(false);
        }
    }, [workspace.slug, buildParams, cxRtsType]);

    const fetchOrderItems = useCallback(async (page = 1, sort = orderItemsSort) => {
        setOrderItemsLoading(true);
        try {
            const p = buildParams();
            p.append('page', String(page));
            p.append('per_page', '15');
            p.append('sort', sort);
            const res = await fetch(`/workspaces/${workspace.slug}/rts/analytics/group-by/order-item?${p}`, { credentials: 'same-origin' });
            if (res.ok) setOrderItems(await res.json());
        } finally {
            setOrderItemsLoading(false);
        }
    }, [workspace.slug, buildParams, orderItemsSort]);

    const fetchPrice = useCallback(async () => {
        setPriceLoading(true);
        try {
            const p = buildParams();
            const res = await fetch(`/workspaces/${workspace.slug}/rts/analytics/group-by/price?${p}`, { credentials: 'same-origin' });
            if (res.ok) setPrice(await res.json());
        } finally {
            setPriceLoading(false);
        }
    }, [workspace.slug, buildParams]);

    const fetchDeliveryAttempts = useCallback(async () => {
        setDeliveryAttemptsLoading(true);
        try {
            const p = buildParams();
            const res = await fetch(`/workspaces/${workspace.slug}/rts/analytics/group-by/delivery-attempts?${p}`, { credentials: 'same-origin' });
            if (res.ok) setDeliveryAttempts(await res.json());
        } finally {
            setDeliveryAttemptsLoading(false);
        }
    }, [workspace.slug, buildParams]);

    const provinceSearchTimer = useRef<ReturnType<typeof setTimeout> | null>(null);
    useEffect(() => {
        if (provinceSearchTimer.current) clearTimeout(provinceSearchTimer.current);
        provinceSearchTimer.current = setTimeout(() => fetchProvinces(1, provinceSearch), 400);
        return () => { if (provinceSearchTimer.current) clearTimeout(provinceSearchTimer.current); };
    }, [provinceSearch]);

    const citySearchTimer = useRef<ReturnType<typeof setTimeout> | null>(null);
    useEffect(() => {
        if (citySearchTimer.current) clearTimeout(citySearchTimer.current);
        citySearchTimer.current = setTimeout(() => fetchCities(1, citySearch), 400);
        return () => { if (citySearchTimer.current) clearTimeout(citySearchTimer.current); };
    }, [citySearch]);

    useEffect(() => {
        fetchProvinces(1);
        fetchCities(1, '');
        fetchDeliveryAttempts();
        fetchCxRts();
        fetchPrice();
        fetchOrderItems(1, orderItemsSort);
    }, [dateRange, filter]);

    useEffect(() => {
        fetchCxRts(cxRtsType);
    }, [cxRtsType]);

    // Fetch the active tab's data when switching
    useEffect(() => {
        if (groupBy === 'province') fetchProvinces(1);
        else fetchCities(1, citySearch);
    }, [groupBy]);

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
        <AppLayout>
            <Head title={`${workspace.name} — RTS Analytics`} />
            <div className="p-4 md:p-6 space-y-6">
                <PageHeader
                    title="RTS Analytics"
                    description={`${formatDate(new Date(dateRange[0]), 'MMM d')} – ${formatDate(new Date(dateRange[1]), 'MMM d, yyyy')}`}
                >
                    <Filters workspace={workspace} onChange={setFilter} />
                    <DatePicker
                        id="rts-date-range"
                        mode="range"
                        onChange={(dates) => {
                            if (dates.length === 2) {
                                setDateRange([
                                    moment(dates[0]).format('YYYY-MM-DD'),
                                    moment(dates[1]).format('YYYY-MM-DD'),
                                ]);
                            }
                        }}
                        defaultDate={dateRange as never as DateOption}
                    />
                </PageHeader>

                {/* Price + Delivery Attempts side by side */}
                <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    {/* Price breakdown */}
                    <div className="rounded-2xl border border-black/6 dark:border-white/6 bg-white dark:bg-zinc-900">
                        <div className="flex items-center justify-between border-b border-black/6 dark:border-white/6 px-5 py-4">
                            <div>
                                <h2 className="text-[14px] font-semibold text-gray-900 dark:text-gray-100">By Price (Final Amount)</h2>
                                <p className="mt-0.5 text-[12px] text-gray-400 dark:text-gray-500">RTS rate by order price range</p>
                            </div>
                            <ViewToggle value={priceView} onChange={setPriceView} />
                        </div>
                        <div className="p-4">
                            {priceLoading ? (
                                <div className="flex h-24 items-center justify-center text-[13px] text-gray-400">Loading…</div>
                            ) : priceView === 'chart' ? (
                                <RtsBreakdownChart
                                    rows={price.map((r) => ({
                                        label: r.price_bucket ? (PRICE_LABELS[r.price_bucket] ?? r.price_bucket) : 'Unknown',
                                        rts_rate_percentage: r.rts_rate_percentage,
                                    }))}
                                />
                            ) : (
                                <table className="w-full text-[12px]">
                                    <thead>
                                        <tr className="border-b border-black/6 dark:border-white/6">
                                            <th className="px-3 py-2 text-left font-mono text-[10px] uppercase tracking-wider text-gray-300 dark:text-gray-600">Price Range</th>
                                            <th className="px-3 py-2 text-right font-mono text-[10px] uppercase tracking-wider text-gray-300 dark:text-gray-600">Total</th>
                                            <th className="px-3 py-2 text-right font-mono text-[10px] uppercase tracking-wider text-gray-300 dark:text-gray-600">Delivered</th>
                                            <th className="px-3 py-2 text-right font-mono text-[10px] uppercase tracking-wider text-gray-300 dark:text-gray-600">Returned</th>
                                            <th className="px-3 py-2 text-right font-mono text-[10px] uppercase tracking-wider text-gray-300 dark:text-gray-600">RTS Rate</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {price.map((row) => (
                                            <tr key={row.price_bucket ?? 'unknown'} className="border-b border-black/4 dark:border-white/4 last:border-0">
                                                <td className="px-3 py-2.5 font-medium text-gray-700 dark:text-gray-300">
                                                    {row.price_bucket ? (PRICE_LABELS[row.price_bucket] ?? row.price_bucket) : <span className="text-gray-400">Unknown</span>}
                                                </td>
                                                <td className="px-3 py-2.5 text-right text-gray-600 dark:text-gray-400">{row.total_orders}</td>
                                                <td className="px-3 py-2.5 text-right text-green-600 dark:text-green-400">{row.delivered_count}</td>
                                                <td className="px-3 py-2.5 text-right text-red-500">{row.returned_count}</td>
                                                <td className="px-3 py-2.5 text-right"><RtsCell value={row.rts_rate_percentage} /></td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            )}
                        </div>
                    </div>

                    {/* Delivery Attempts breakdown */}
                    <div className="rounded-2xl border border-black/6 dark:border-white/6 bg-white dark:bg-zinc-900">
                        <div className="flex items-center justify-between border-b border-black/6 dark:border-white/6 px-5 py-4">
                            <div>
                                <h2 className="text-[14px] font-semibold text-gray-900 dark:text-gray-100">By Delivery Attempts</h2>
                                <p className="mt-0.5 text-[12px] text-gray-400 dark:text-gray-500">RTS rate by number of delivery attempts</p>
                            </div>
                            <ViewToggle value={attemptsView} onChange={setAttemptsView} />
                        </div>
                        <div className="p-4">
                            {deliveryAttemptsLoading ? (
                                <div className="flex h-24 items-center justify-center text-[13px] text-gray-400">Loading…</div>
                            ) : attemptsView === 'chart' ? (
                                <RtsBreakdownChart
                                    rows={deliveryAttempts.map((r) => ({
                                        label: r.delivery_attempts ?? 'Unknown',
                                        rts_rate_percentage: r.rts_rate_percentage,
                                    }))}
                                />
                            ) : (
                                <table className="w-full text-[12px]">
                                    <thead>
                                        <tr className="border-b border-black/6 dark:border-white/6">
                                            <th className="px-3 py-2 text-left font-mono text-[10px] uppercase tracking-wider text-gray-300 dark:text-gray-600">Attempts</th>
                                            <th className="px-3 py-2 text-right font-mono text-[10px] uppercase tracking-wider text-gray-300 dark:text-gray-600">Total</th>
                                            <th className="px-3 py-2 text-right font-mono text-[10px] uppercase tracking-wider text-gray-300 dark:text-gray-600">Delivered</th>
                                            <th className="px-3 py-2 text-right font-mono text-[10px] uppercase tracking-wider text-gray-300 dark:text-gray-600">Returned</th>
                                            <th className="px-3 py-2 text-right font-mono text-[10px] uppercase tracking-wider text-gray-300 dark:text-gray-600">RTS Rate</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {deliveryAttempts.map((row) => (
                                            <tr key={row.delivery_attempts ?? 'unknown'} className="border-b border-black/4 dark:border-white/4 last:border-0">
                                                <td className="px-3 py-2.5 font-medium text-gray-700 dark:text-gray-300">
                                                    {row.delivery_attempts ?? <span className="text-gray-400">Unknown</span>}
                                                </td>
                                                <td className="px-3 py-2.5 text-right text-gray-600 dark:text-gray-400">{row.total_orders}</td>
                                                <td className="px-3 py-2.5 text-right text-green-600 dark:text-green-400">{row.delivered_count}</td>
                                                <td className="px-3 py-2.5 text-right text-red-500">{row.returned_count}</td>
                                                <td className="px-3 py-2.5 text-right"><RtsCell value={row.rts_rate_percentage} /></td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            )}
                        </div>
                    </div>
                </div>

                {/* Cx RTS breakdown */}
                <div className="rounded-2xl border border-black/6 dark:border-white/6 bg-white dark:bg-zinc-900">
                    <div className="flex items-center justify-between border-b border-black/6 dark:border-white/6 px-5 py-4">
                        <div>
                            <h2 className="text-[14px] font-semibold text-gray-900 dark:text-gray-100">By Customer RTS (Phone Number Report)</h2>
                            <p className="mt-0.5 text-[12px] text-gray-400 dark:text-gray-500">
                                {cxRtsType === 'latest' ? 'Latest report — current cumulative RTS rate per phone number' : 'Initial report — RTS rate at the time the order was placed'}
                            </p>
                        </div>
                        <div className="flex items-center gap-2">
                            <div className="flex rounded-lg border border-black/8 dark:border-white/8 overflow-hidden text-[12px] font-medium">
                                <button
                                    onClick={() => setCxRtsType('latest')}
                                    className={`px-3 py-1.5 transition-colors ${cxRtsType === 'latest' ? 'bg-gray-100 dark:bg-white/10 text-gray-900 dark:text-gray-100' : 'text-gray-400 dark:text-gray-500 hover:text-gray-700 dark:hover:text-gray-300'}`}
                                >
                                    Latest
                                </button>
                                <button
                                    onClick={() => setCxRtsType('initial')}
                                    className={`px-3 py-1.5 transition-colors border-l border-black/8 dark:border-white/8 ${cxRtsType === 'initial' ? 'bg-gray-100 dark:bg-white/10 text-gray-900 dark:text-gray-100' : 'text-gray-400 dark:text-gray-500 hover:text-gray-700 dark:hover:text-gray-300'}`}
                                >
                                    Initial
                                </button>
                            </div>
                            <ViewToggle value={cxRtsView} onChange={setCxRtsView} />
                        </div>
                    </div>
                    <div className="p-4">
                        {cxRtsLoading ? (
                            <div className="flex h-24 items-center justify-center text-[13px] text-gray-400">Loading…</div>
                        ) : cxRtsView === 'chart' ? (
                            <RtsBreakdownChart
                                rows={cxRts.map((r) => ({
                                    label: CX_RTS_LABELS[r.cx_rts_bucket] ?? r.cx_rts_bucket,
                                    rts_rate_percentage: r.rts_rate_percentage,
                                }))}
                            />
                        ) : (
                            <table className="w-full text-[12px]">
                                <thead>
                                    <tr className="border-b border-black/6 dark:border-white/6">
                                        <th className="px-3 py-2 text-left font-mono text-[10px] uppercase tracking-wider text-gray-300 dark:text-gray-600">Cx. RTS Bucket</th>
                                        <th className="px-3 py-2 text-right font-mono text-[10px] uppercase tracking-wider text-gray-300 dark:text-gray-600">Total Orders</th>
                                        <th className="px-3 py-2 text-right font-mono text-[10px] uppercase tracking-wider text-gray-300 dark:text-gray-600">Delivered</th>
                                        <th className="px-3 py-2 text-right font-mono text-[10px] uppercase tracking-wider text-gray-300 dark:text-gray-600">Returned</th>
                                        <th className="px-3 py-2 text-right font-mono text-[10px] uppercase tracking-wider text-gray-300 dark:text-gray-600">RTS Rate</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {cxRts.map((row) => (
                                        <tr key={row.cx_rts_bucket} className="border-b border-black/4 dark:border-white/4 last:border-0">
                                            <td className="px-3 py-2.5 font-medium text-gray-700 dark:text-gray-300">
                                                {CX_RTS_LABELS[row.cx_rts_bucket] ?? row.cx_rts_bucket}
                                            </td>
                                            <td className="px-3 py-2.5 text-right text-gray-600 dark:text-gray-400">{row.total_orders}</td>
                                            <td className="px-3 py-2.5 text-right text-green-600 dark:text-green-400">{row.delivered_count}</td>
                                            <td className="px-3 py-2.5 text-right text-red-500">{row.returned_count}</td>
                                            <td className="px-3 py-2.5 text-right">
                                                <RtsCell value={row.rts_rate_percentage} />
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        )}
                    </div>
                </div>

                {/* Product breakdown */}
                <div className="rounded-2xl border border-black/6 dark:border-white/6 bg-white dark:bg-zinc-900">
                    <div className="flex items-center justify-between border-b border-black/6 dark:border-white/6 px-5 py-4">
                        <div>
                            <h2 className="text-[14px] font-semibold text-gray-900 dark:text-gray-100">By Product</h2>
                            <p className="mt-0.5 text-[12px] text-gray-400 dark:text-gray-500">RTS rate broken down by product/item name</p>
                        </div>
                    </div>
                    <div className="p-4">
                        {orderItemsLoading ? (
                            <div className="flex h-24 items-center justify-center text-[13px] text-gray-400">Loading…</div>
                        ) : (
                            <>
                                <table className="w-full text-[12px]">
                                    <thead>
                                        <tr className="border-b border-black/6 dark:border-white/6">
                                            {(['item_name', 'total_orders', 'delivered_count', 'returned_count', 'rts_rate_percentage'] as const).map((col) => {
                                                const labels: Record<string, string> = { item_name: 'Item Name', total_orders: 'Total Orders', delivered_count: 'Delivered', returned_count: 'Returned', rts_rate_percentage: 'RTS Rate' };
                                                const isActive = orderItemsSort.replace('-', '') === col;
                                                const isDesc = orderItemsSort.startsWith('-') && isActive;
                                                const nextSort = isActive && isDesc ? col : `-${col}`;
                                                return (
                                                    <th
                                                        key={col}
                                                        onClick={() => { setOrderItemsSort(nextSort); fetchOrderItems(1, nextSort); }}
                                                        className={`px-3 py-2 font-mono text-[10px] uppercase tracking-wider cursor-pointer select-none transition-colors hover:text-gray-500 dark:hover:text-gray-400 ${col === 'item_name' ? 'text-left' : 'text-right'} ${isActive ? 'text-gray-500 dark:text-gray-400' : 'text-gray-300 dark:text-gray-600'}`}
                                                    >
                                                        {labels[col]}{isActive ? (isDesc ? ' ↓' : ' ↑') : ''}
                                                    </th>
                                                );
                                            })}
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {(orderItems?.data ?? []).map((row, i) => (
                                            <tr key={`${row.item_name}-${i}`} className="border-b border-black/4 dark:border-white/4 last:border-0">
                                                <td className="px-3 py-2.5 font-medium text-gray-700 dark:text-gray-300">
                                                    {row.item_name ?? <span className="text-gray-400">Unknown</span>}
                                                </td>
                                                <td className="px-3 py-2.5 text-right text-gray-600 dark:text-gray-400">{row.total_orders}</td>
                                                <td className="px-3 py-2.5 text-right text-green-600 dark:text-green-400">{row.delivered_count}</td>
                                                <td className="px-3 py-2.5 text-right text-red-500">{row.returned_count}</td>
                                                <td className="px-3 py-2.5 text-right"><RtsCell value={row.rts_rate_percentage} /></td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                                {orderItems && orderItems.last_page > 1 && (
                                    <div className="mt-4 flex items-center justify-between border-t border-black/4 dark:border-white/4 pt-4">
                                        <p className="font-mono text-[11px] text-gray-400">
                                            Showing {orderItems.from}–{orderItems.to} of {orderItems.total}
                                        </p>
                                        <div className="flex gap-1">
                                            <button
                                                disabled={orderItems.current_page === 1}
                                                onClick={() => fetchOrderItems(orderItems.current_page - 1)}
                                                className="rounded px-2.5 py-1 font-mono text-[11px] text-gray-500 transition-colors hover:bg-gray-100 disabled:opacity-30 dark:hover:bg-white/6"
                                            >
                                                Previous
                                            </button>
                                            <button
                                                disabled={orderItems.current_page === orderItems.last_page}
                                                onClick={() => fetchOrderItems(orderItems.current_page + 1)}
                                                className="rounded px-2.5 py-1 font-mono text-[11px] text-gray-500 transition-colors hover:bg-gray-100 disabled:opacity-30 dark:hover:bg-white/6"
                                            >
                                                Next
                                            </button>
                                        </div>
                                    </div>
                                )}
                            </>
                        )}
                    </div>
                </div>

                {/* Location breakdown */}
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
                            {/* Search — changes placeholder based on active view */}
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
                                        className="h-8 w-48 rounded-lg border border-black/8 dark:border-white/8 bg-stone-50 dark:bg-white/3 pl-8 pr-3 text-[12px]! text-gray-700 dark:text-gray-300 placeholder-gray-300 dark:placeholder-gray-600 outline-none focus:border-black/20 dark:focus:border-white/20 transition-colors"
                                    />
                                ) : (
                                    <input
                                        type="text"
                                        value={citySearch}
                                        onChange={(e) => setCitySearch(e.target.value)}
                                        placeholder="Search city or province…"
                                        className="h-8 w-52 rounded-lg border border-black/8 dark:border-white/8 bg-stone-50 dark:bg-white/3 pl-8 pr-3 text-[12px]! text-gray-700 dark:text-gray-300 placeholder-gray-300 dark:placeholder-gray-600 outline-none focus:border-black/20 dark:focus:border-white/20 transition-colors"
                                    />
                                )}
                            </div>
                            {/* Toggle */}
                            <div className="flex rounded-lg border border-black/8 dark:border-white/8 overflow-hidden text-[12px] font-medium">
                                <button
                                    onClick={() => setGroupBy('province')}
                                    className={`px-3 py-1.5 transition-colors ${groupBy === 'province' ? 'bg-gray-100 dark:bg-white/10 text-gray-900 dark:text-gray-100' : 'text-gray-400 dark:text-gray-500 hover:text-gray-700 dark:hover:text-gray-300'}`}
                                >
                                    Province
                                </button>
                                <button
                                    onClick={() => setGroupBy('city')}
                                    className={`px-3 py-1.5 transition-colors border-l border-black/8 dark:border-white/8 ${groupBy === 'city' ? 'bg-gray-100 dark:bg-white/10 text-gray-900 dark:text-gray-100' : 'text-gray-400 dark:text-gray-500 hover:text-gray-700 dark:hover:text-gray-300'}`}
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
                                        const sort = params?.sort as string ?? '-total_orders';
                                        setProvinceSort(sort);
                                        fetchProvinces(Number(params?.page ?? 1), provinceSearch, sort);
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
                                        const sort = params?.sort as string ?? '-total_orders';
                                        setCitySort(sort);
                                        fetchCities(Number(params?.page ?? 1), citySearch, sort);
                                    }}
                                />
                            )
                        )}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
