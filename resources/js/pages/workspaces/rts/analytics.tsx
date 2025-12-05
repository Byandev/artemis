import AppLayout from '@/layouts/app-layout';
import RtsNavigation from '@/pages/workspaces/rts/partials/RtsNavigation';
import { Workspace } from '@/types/models/Workspace';
import { Card, CardHeader, CardContent } from '@/components/ui/card';
import { useEffect, useMemo, useState } from 'react';
import { ChartConfig } from '@/components/ui/chart';
import BreakdownAnalyticsView from './partials/BreakdownAnalyticsView';
import { ColumnDef } from '@tanstack/react-table';
import { getLatLng } from '@/lib/cities';
import { HeatPoint } from './partials/HeatmapMap';
import { CalendarIcon, FilterIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import SearchSelect from './partials/SearchSelect';
import { DropdownMenu, DropdownMenuContent, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from '@/components/ui/accordion';
import { router } from '@inertiajs/react';
import workspaces from '@/routes/workspaces';
import { Input } from '@/components/ui/input';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Calendar } from '@/components/ui/calendar';

type BreakDownAnalytics = {
    id: number;
    name: string;
    total_orders: number;
    rts_rate_percentage: number;
    returned_count: number;
    delivered_count: number;
}

type Props = {
    workspace: Workspace;
    data: {
        rts_rate_percentage: number;
        returned_count: number;
        delivered_count: number;
        returned_amount: number;
        tracked_orders: number;
        sent_parcel_journey_notifications: number;
        grouped_rts_stats_by_page?: BreakDownAnalytics[];
        grouped_rts_stats_by_shops?: BreakDownAnalytics[];
        grouped_rts_stats_by_users?: BreakDownAnalytics[];
        grouped_rts_stats_by_cities?: BreakDownAnalytics[];
    }
}

function formatDate(date: Date | undefined) {
    if (!date) {
        return ""
    }

    return date.toLocaleDateString("en-US", {
        day: "2-digit",
        month: "long",
        year: "numeric",
    })
}

function isValidDate(date: Date | undefined) {
    if (!date) {
        return false
    }
    return !isNaN(date.getTime())
}


const Analytics = ({ workspace, data }: Props) => {
    const [selectedPagesFilter, setSelectedPagesFilter] = useState<number[]>([]);
    const [selectedUsersFilter, setSelectedUsersFilter] = useState<number[]>([]);
    const [selectedShopFilter, setSelectedShopFilter] = useState<number[]>([]);

    const [groupedByPage, setGroupedByPage] = useState<BreakDownAnalytics[]>(data.grouped_rts_stats_by_page ?? []);
    const [groupedByShops, setGroupedByShops] = useState<BreakDownAnalytics[]>(data.grouped_rts_stats_by_shops ?? []);
    const [groupedByUsers, setGroupedByUsers] = useState<BreakDownAnalytics[]>(data.grouped_rts_stats_by_users ?? []);
    const [groupedByCities, setGroupedByCities] = useState<BreakDownAnalytics[]>(data.grouped_rts_stats_by_cities ?? []);
    const [loadingGrouped, setLoadingGrouped] = useState<boolean>(true);

    const [open, setOpen] = useState(false)
    const [date, setDate] = useState<Date | undefined>(
        new Date()
    )
    const [month, setMonth] = useState<Date | undefined>(date)
    const [value, setValue] = useState(formatDate(date))

    // useEffect(() => {
    //     if (selectedPagesFilter.length === 0) return;

    //     router.get(
    //         workspaces.rts.analytics.url(workspace),
    //         {
    //             page_ids: selectedPagesFilter,
    //             user_ids: selectedUsersFilter,
    //             city_ids: selectedCitiesFilter,
    //         },
    //         {
    //             preserveState: true,
    //             preserveScroll: true,
    //             replace: true
    //         }
    //     );
    // }, [selectedPagesFilter, workspace, data.grouped_rts_stats_by_page, selectedUsersFilter, selectedCitiesFilter]);


    // fetch grouped datasets from API endpoints (use workspace id)
    useEffect(() => {
        const base = `/workspaces/${workspace.slug}/rts/analytics/group-by`;

        const fetchJson = async (path: string) => {
            const res = await fetch(path, { credentials: 'same-origin' });
            if (!res.ok) return [];
            return res.json();
        };

        (async () => {
            setLoadingGrouped(true);
            try {
                const [pages, shops, users, cities] = await Promise.all([
                    fetchJson(`${base}/page`),
                    fetchJson(`${base}/shops`),
                    fetchJson(`${base}/users`),
                    fetchJson(`${base}/cities`),
                ]);

                setGroupedByPage(pages ?? []);
                setGroupedByShops(shops ?? []);
                setGroupedByUsers(users ?? []);
                setGroupedByCities(cities ?? []);
            } catch (e) {
                console.error('Failed to load grouped analytics', e);
            } finally {
                setLoadingGrouped(false);
            }
        })();
    }, [workspace.id, workspace.slug]);

    const heatmapPoints: HeatPoint[] = useMemo(() => {
        return groupedByCities
            .map((city) => {
                const latLng = getLatLng(city.name);
                if (!latLng) return null;
                const coordinates = {
                    lat: latLng.lat,
                    lng: latLng.lng,
                };
                return {
                    coordinates,
                    value: city.rts_rate_percentage,
                };
            })
            .filter((p): p is HeatPoint => p !== null);
    }, [groupedByCities]);


    const analytics = useMemo(() => {
        return [
            { title: 'RTS Rate', value: `${data.rts_rate_percentage}%` },
            { title: 'RTS Amount', value: data.returned_amount },
            { title: 'Tracked Orders', value: data.tracked_orders },
            { title: 'Parcel Updates Sent', value: data.sent_parcel_journey_notifications },
        ]
    }, [data])

    const chartConfig = {
        rts_rate_percentage: {
            label: "Rts Rate %",
            color: "#1f2937",
        },
    } satisfies ChartConfig;

    const perPageColumns: ColumnDef<BreakDownAnalytics>[] = [
        {
            accessorKey: "name",
            header: "Page",
        },
        {
            accessorKey: "total_orders",
            header: "Total Orders",
        },
        {
            accessorKey: "returned_count",
            header: "Returned",
        },
        {
            accessorKey: "delivered_count",
            header: "Delivered",
        },
        {
            accessorKey: "rts_rate_percentage",
            header: "RTS Rate",
            cell: ({ row }) => {
                return `${row.original.rts_rate_percentage}%`
            }
        },
    ];

    const perUserColumns: ColumnDef<BreakDownAnalytics>[] = [
        {
            accessorKey: "name",
            header: "User",
        },
        {
            accessorKey: "total_orders",
            header: "Total Orders",
        },
        {
            accessorKey: "returned_count",
            header: "Returned",
        },
        {
            accessorKey: "delivered_count",
            header: "Delivered",
        },
        {
            accessorKey: "rts_rate_percentage",
            header: "RTS Rate",
            cell: ({ row }) => {
                return `${row.original.rts_rate_percentage}%`
            }
        },
    ];

    const perCityColumns: ColumnDef<BreakDownAnalytics>[] = [
        {
            accessorKey: "name",
            header: "City",
        },
        {
            accessorKey: "total_orders",
            header: "Total Orders",
        },
        {
            accessorKey: "returned_count",
            header: "Returned",
        },
        {
            accessorKey: "delivered_count",
            header: "Delivered",
        },
        {
            accessorKey: "rts_rate_percentage",
            header: "RTS Rate",
            cell: ({ row }) => {
                return `${row.original.rts_rate_percentage}%`
            }
        },
    ];

    const perShopColumns: ColumnDef<BreakDownAnalytics>[] = [
        {
            accessorKey: "name",
            header: "Shop",
        },
        {
            accessorKey: "total_orders",
            header: "Total Orders",
        },
        {
            accessorKey: "returned_count",
            header: "Returned",
        },
        {
            accessorKey: "delivered_count",
            header: "Delivered",
        },
        {
            accessorKey: "rts_rate_percentage",
            header: "RTS Rate",
            cell: ({ row }) => {
                return `${row.original.rts_rate_percentage}%`
            }
        },
    ];

    return (
        <AppLayout>
            <div className='px-4 py-6'>
                <RtsNavigation workspace={workspace} />

                <div className='border p-5 rounded-md'>
                    <div className='flex items-center justify-between mb-8'>
                        <h1 className="scroll-m-20 text-center text-3xl font-extrabold tracking-tight text-balance">
                            Analytics
                        </h1>
                        <div className='flex gap-2'>
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <Button variant="outline">
                                        <FilterIcon className='mr-2 h-4 w-4' />
                                        Filter</Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end" className="w-54 p-3">
                                    <Accordion
                                        type="multiple"
                                        className="w-full"
                                    >
                                        <AccordionItem value="item-1">
                                            <AccordionTrigger className='py-2'>Page</AccordionTrigger>
                                            <AccordionContent className="flex flex-col gap-4 text-balance">
                                                {loadingGrouped ? (
                                                    <div>Loading...</div>
                                                ) : (
                                                    <SearchSelect
                                                        items={groupedByPage.map((page) => ({
                                                            id: page.id,
                                                            name: page.name,
                                                        }))}
                                                        selected={selectedPagesFilter}
                                                        setSelected={setSelectedPagesFilter}
                                                    />
                                                )}
                                            </AccordionContent>
                                        </AccordionItem>
                                        <AccordionItem value="item-2">
                                            <AccordionTrigger className='py-2'>User</AccordionTrigger>
                                            <AccordionContent className="flex flex-col gap-4 text-balance">
                                                {loadingGrouped ? (
                                                    <div>Loading...</div>
                                                ) : (
                                                    <SearchSelect
                                                        items={groupedByUsers.map((user) => ({
                                                            id: user.id,
                                                            name: user.name,
                                                        }))}
                                                        selected={selectedUsersFilter}
                                                        setSelected={setSelectedUsersFilter}
                                                    />
                                                )}
                                            </AccordionContent>
                                        </AccordionItem>
                                        <AccordionItem value="item-3">
                                            <AccordionTrigger className='py-2'>Shop</AccordionTrigger>
                                            <AccordionContent className="flex flex-col gap-4 text-balance">
                                                {loadingGrouped ? (
                                                    <div>Loading...</div>
                                                ) : (
                                                    <SearchSelect
                                                        items={groupedByShops.map((shop) => ({
                                                            id: shop.id,
                                                            name: shop.name,
                                                        }))}
                                                        selected={selectedShopFilter}
                                                        setSelected={setSelectedShopFilter}
                                                    />
                                                )}
                                            </AccordionContent>
                                        </AccordionItem>
                                    </Accordion>
                                </DropdownMenuContent>
                            </DropdownMenu>

                            <div className="flex flex-col gap-3">
                                <div className="relative flex gap-2">
                                    <Input
                                        id="date"
                                        value={value}
                                        placeholder="June 01, 2025"
                                        className="bg-background pr-10"
                                        onChange={(e) => {
                                            const date = new Date(e.target.value)
                                            setValue(e.target.value)
                                            if (isValidDate(date)) {
                                                setDate(date)
                                                setMonth(date)
                                            }
                                        }}
                                        onKeyDown={(e) => {
                                            if (e.key === "ArrowDown") {
                                                e.preventDefault()
                                                setOpen(true)
                                            }
                                        }}
                                    />
                                    <Popover open={open} onOpenChange={setOpen}>
                                        <PopoverTrigger asChild>
                                            <Button
                                                id="date-picker"
                                                variant="ghost"
                                                className="absolute top-1/2 right-2 size-6 -translate-y-1/2"
                                            >
                                                <CalendarIcon className="size-3.5" />
                                                <span className="sr-only">Select date</span>
                                            </Button>
                                        </PopoverTrigger>
                                        <PopoverContent
                                            className="w-auto overflow-hidden p-0"
                                            align="end"
                                            alignOffset={-8}
                                            sideOffset={10}
                                        >
                                            <Calendar
                                                mode="single"
                                                selected={date}
                                                captionLayout="dropdown"
                                                month={month}
                                                onMonthChange={setMonth}
                                                onSelect={(date) => {
                                                    setDate(date)
                                                    setValue(formatDate(date))
                                                    setOpen(false)
                                                }}
                                            />
                                        </PopoverContent>
                                    </Popover>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
                        {analytics.map((data, key) => (
                            <Card key={key} className="p-4 gap-5 flex flex-col col-span-1">
                                <CardHeader className="p-0">
                                    <div>
                                        <span className="text-xl sm:text-2xl md:text-3xl font-extrabold">
                                            {typeof data.value === 'number'
                                                ? new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(data.value)
                                                : data.value}
                                        </span>
                                    </div>
                                </CardHeader>
                                <CardContent className="p-0">
                                    <p className="text-sm sm:text-md text-muted-foreground">{data.title}</p>
                                </CardContent>
                            </Card>
                        ))}

                        <div className="col-span-1 sm:col-span-2 md:col-span-4 mt-4 space-y-6">
                            <BreakdownAnalyticsView<BreakDownAnalytics>
                                columns={perPageColumns}
                                bars={[
                                    { dataKey: 'rts_rate_percentage', fill: chartConfig.rts_rate_percentage.color, name: chartConfig.rts_rate_percentage.label },
                                ]}
                                xKey="name"
                                className="w-full max-h-[400px]"
                                data={groupedByPage}
                                chartConfig={chartConfig}
                                title="Breakdown per Pages"
                                loading={loadingGrouped}
                            />

                            <BreakdownAnalyticsView<BreakDownAnalytics>
                                columns={perShopColumns}
                                bars={[
                                    { dataKey: 'rts_rate_percentage', fill: chartConfig.rts_rate_percentage.color, name: chartConfig.rts_rate_percentage.label },
                                ]}
                                xKey="name"
                                className="w-full max-h-[400px]"
                                data={groupedByShops}
                                chartConfig={chartConfig}
                                title="Breakdown per Shops"
                                loading={loadingGrouped}
                            />

                            <BreakdownAnalyticsView<BreakDownAnalytics>
                                columns={perUserColumns}
                                bars={[
                                    { dataKey: 'rts_rate_percentage', fill: chartConfig.rts_rate_percentage.color, name: chartConfig.rts_rate_percentage.label },
                                ]}
                                xKey="name"
                                className="w-full max-h-[400px]"
                                data={groupedByUsers}
                                chartConfig={chartConfig}
                                title="Breakdown per Users"
                                loading={loadingGrouped}
                            />

                            <BreakdownAnalyticsView<BreakDownAnalytics>
                                columns={perCityColumns}
                                availableViews={['heatmap', 'table']}
                                className="w-full max-h-[400px]"
                                data={groupedByCities}
                                title="Breakdown per Cities"
                                heatmapPoints={heatmapPoints}
                                loading={loadingGrouped}
                            />
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout >
    );
}

export default Analytics;
