import AppLayout from '@/layouts/app-layout';
import RtsNavigation from '@/pages/workspaces/rts/partials/RtsNavigation';
import { Workspace } from '@/types/models/Workspace';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { useMemo } from 'react';
import React from 'react';
import { Calendar } from '@/components/ui/calendar';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { CalendarIcon, FilterIcon } from 'lucide-react';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';

type Props = {
    workspace: Workspace;
    data: {
        rts_rate_percentage: number;
        returned_count: number;
        delivered_count: number;
        returned_amount: number;
        tracked_orders: number;
        sent_parcel_journey_notifications: number
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
    const analytics = useMemo(() => {
        return [
            { title: 'RTS Rate', value: `${data.rts_rate_percentage}%` },
            { title: 'RTS Amount', value: data.returned_amount },
            { title: 'Tracked Orders', value: data.tracked_orders },
            { title: 'Parcel Updates Sent', value: data.sent_parcel_journey_notifications },
        ]
    }, [data])

    const [open, setOpen] = React.useState(false)
    const [date, setDate] = React.useState<Date | undefined>(
        new Date("2025-06-01")
    )
    const [month, setMonth] = React.useState<Date | undefined>(date)
    const [value, setValue] = React.useState(formatDate(date))
    return (
        <AppLayout>
            <div className='px-4 py-6'>
                <div className='flex justify-between mb-5'>
                    <h1 className="text-center text-4xl font-extrabold text-balance">
                        Analytics
                    </h1>

                    <div>
                        <div className='flex items-center gap-3'>
                            <Button variant="secondary" size="sm">
                                <FilterIcon className="mr-2 h-4 w-4" />
                                Filters
                            </Button>

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

                <RtsNavigation workspace={workspace} />

                <div className="grid grid-cols-4 gap-4">
                    {analytics.map((data, key) => {
                        return <Card key={key}>
                            <CardHeader>
                                <CardTitle>{data.value}</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <p>{data.title}</p>
                            </CardContent>
                        </Card>
                    })}
                </div>
            </div>
        </AppLayout>
    );
}

export default Analytics;
