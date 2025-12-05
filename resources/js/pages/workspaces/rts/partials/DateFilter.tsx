import React from 'react';
import { CalendarIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Calendar } from '@/components/ui/calendar';
import { formatDate, isValidDate } from '@/lib/utils';

type Props = {
    open: boolean;
    setOpen: (v: boolean) => void;
    startDate: Date | undefined;
    setStartDate: (d: Date | undefined) => void;
    endDate: Date | undefined;
    setEndDate: (d: Date | undefined) => void;
    month: Date | undefined;
    setMonth: (d: Date | undefined) => void;
    value: string;
    setValue: (v: string) => void;
}

const DateFilter = ({ open, setOpen, startDate, setStartDate, endDate, setEndDate, month, setMonth, value, setValue }: Props) => {
    return (
        <div className="flex flex-col gap-3">
            <div className="relative flex gap-2">
                <Input
                    id="date"
                    value={value}
                    placeholder="June 01, 2025"
                    className="bg-background pr-10"
                    onChange={(e) => {
                        const val = e.target.value;
                        setValue(val);
                        if (!val) {
                            setStartDate(undefined);
                            setEndDate(undefined);
                            setMonth(undefined);
                            return;
                        }

                        // Try parsing a range like "Jun 01, 2025 - Jun 30, 2025"
                        const parts = val.split(/\s*(?:—|-|–)\s*/);
                        if (parts.length === 2) {
                            const p0 = new Date(parts[0]);
                            const p1 = new Date(parts[1]);
                            const valid0 = isValidDate(p0);
                            const valid1 = isValidDate(p1);
                            if (valid0) setStartDate(p0);
                            if (valid1) setEndDate(p1);
                            if (valid0) setMonth(p0);
                            return;
                        }

                        // Fallback: try parse single date
                        const parsed = new Date(val);
                        if (isValidDate(parsed)) {
                            setStartDate(parsed);
                            setEndDate(undefined);
                            setMonth(parsed);
                        }
                    }}
                    onKeyDown={(e) => {
                        if (e.key === 'ArrowDown') {
                            e.preventDefault();
                            setOpen(true);
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
                            mode="range"
                            selected={{ from: startDate, to: endDate }}
                            captionLayout="dropdown"
                            month={month}
                            onMonthChange={setMonth}
                            onSelect={(rangeOrDate: any) => {
                                // rangeOrDate may be undefined, a Date, or a Range { from?, to? }
                                if (!rangeOrDate) {
                                    setStartDate(undefined);
                                    setEndDate(undefined);
                                    setValue('');
                                    return;
                                }

                                // If it's a single Date (user clicked one date), set start only
                                if (rangeOrDate instanceof Date) {
                                    setStartDate(rangeOrDate);
                                    setEndDate(undefined);
                                    setValue(formatDate(rangeOrDate));
                                    return;
                                }

                                const from: Date | undefined = rangeOrDate?.from;
                                const to: Date | undefined = rangeOrDate?.to;
                                setStartDate(from);
                                setEndDate(to);
                                if (from && to) {
                                    setValue(`${formatDate(from)} — ${formatDate(to)}`);
                                    setOpen(false);
                                } else if (from) {
                                    setValue(formatDate(from));
                                }
                            }}
                        />
                    </PopoverContent>
                </Popover>
            </div>
        </div>
    );
};

export default DateFilter;
