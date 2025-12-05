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
    date: Date | undefined;
    setDate: (d: Date | undefined) => void;
    month: Date | undefined;
    setMonth: (d: Date | undefined) => void;
    value: string;
    setValue: (v: string) => void;
}

const DateFilter = ({ open, setOpen, date, setDate, month, setMonth, value, setValue }: Props) => {
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
                            setDate(undefined);
                            setMonth(undefined);
                            return;
                        }
                        const parsed = new Date(val);
                        if (isValidDate(parsed)) {
                            setDate(parsed);
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
                            mode="single"
                            selected={date}
                            captionLayout="dropdown"
                            month={month}
                            onMonthChange={setMonth}
                            onSelect={(d) => {
                                setDate(d);
                                setValue(formatDate(d));
                                setOpen(false);
                            }}
                        />
                    </PopoverContent>
                </Popover>
            </div>
        </div>
    );
};

export default DateFilter;
