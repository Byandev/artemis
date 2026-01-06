import * as React from "react"
import { CalendarIcon } from "lucide-react"
import { format } from "date-fns"
import { DateRange } from "react-day-picker"

import { cn } from "@/lib/utils"
import { Button } from "@/components/ui/button"
import { Calendar } from "@/components/ui/calendar"
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover"

interface SimpleDateRangePickerProps {
  value?: DateRange
  onChange?: (value: DateRange | undefined) => void
  placeholder?: string
  className?: string
}

export function SimpleDateRangePicker({
  value,
  onChange,
  placeholder = "Select date range",
  className,
}: SimpleDateRangePickerProps) {
  const [open, setOpen] = React.useState(false)
  const [tempValue, setTempValue] = React.useState<DateRange | undefined>(value)

  // Update temp value when popover opens
  React.useEffect(() => {
    if (open) {
      setTempValue(value)
    }
  }, [open, value])

  const formatDateRange = (dateRange?: DateRange): string => {
    if (!dateRange?.from) {
      return placeholder
    }

    if (dateRange.to) {
      return `${format(dateRange.from, "MMM dd, yyyy")} - ${format(dateRange.to, "MMM dd, yyyy")}`
    }

    return format(dateRange.from, "MMM dd, yyyy")
  }

  const handleApply = () => {
    onChange?.(tempValue)
    setOpen(false)
  }

  const handleCancel = () => {
    setTempValue(value)
    setOpen(false)
  }

  return (
    <Popover open={open} onOpenChange={setOpen}>
      <PopoverTrigger asChild>
        <Button
          variant="outline"
          className={cn(
            "justify-start text-left font-normal bg-white",
            !value?.from && "text-muted-foreground",
            className
          )}
        >
          <CalendarIcon className="h-4 w-4" />
          <span className='text-theme-sm text-gray-500 dark:text-gray-400'>{formatDateRange(value)}</span>
        </Button>
      </PopoverTrigger>
      <PopoverContent className="w-auto p-0" align="start">
        <div className="flex flex-col">
          <Calendar
            mode="range"
            selected={tempValue}
            onSelect={setTempValue}
            numberOfMonths={2}
            defaultMonth={tempValue?.from || value?.from}
            className="border-b text-xs p-2"
            classNames={{
              months: "flex gap-2",
              month: "gap-2",
              caption: "flex justify-center pt-1 pb-2 relative items-center text-xs",
              caption_label: "text-xs font-medium",
              nav: "flex items-center justify-between w-full absolute top-1 left-0 right-0 px-1",
              nav_button: "h-6 w-6 bg-transparent p-0 opacity-50 hover:opacity-100",
              table: "w-full border-collapse space-y-1",
              head_row: "flex",
              head_cell: "text-muted-foreground rounded-md w-7 font-normal text-[0.65rem]",
              row: "flex w-full mt-1",
              cell: "relative p-0 text-center text-xs focus-within:relative focus-within:z-20 [&:has([aria-selected])]:bg-accent [&:has([aria-selected].day-range-end)]:rounded-r-md [&:has([aria-selected].day-range-start)]:rounded-l-md first:[&:has([aria-selected])]:rounded-l-md last:[&:has([aria-selected])]:rounded-r-md",
              day: "h-7 w-7 p-0 font-normal text-xs aria-selected:opacity-100 hover:bg-accent hover:text-accent-foreground rounded-md",
              day_range_start: "day-range-start bg-primary text-primary-foreground rounded-l-md hover:bg-primary hover:text-primary-foreground focus:bg-primary focus:text-primary-foreground",
              day_range_end: "day-range-end bg-primary text-primary-foreground rounded-r-md hover:bg-primary hover:text-primary-foreground focus:bg-primary focus:text-primary-foreground",
              day_selected: "bg-primary text-primary-foreground hover:bg-primary hover:text-primary-foreground focus:bg-primary focus:text-primary-foreground",
              day_range_middle: "aria-selected:bg-accent aria-selected:text-accent-foreground rounded-none",
              day_today: "bg-accent text-accent-foreground font-semibold",
              day_outside: "text-muted-foreground opacity-50 aria-selected:bg-accent/50 aria-selected:text-muted-foreground aria-selected:opacity-30",
              day_disabled: "text-muted-foreground opacity-50",
              day_hidden: "invisible",
            }}
          />
          <div className="flex items-center justify-end gap-2 p-2">
            <Button
              variant="ghost"
              size="sm"
              onClick={handleCancel}
              className="h-7 text-xs px-2"
            >
              Cancel
            </Button>
            <Button
              size="sm"
              onClick={handleApply}
              className="h-7 text-xs px-3"
            >
              Apply
            </Button>
          </div>
        </div>
      </PopoverContent>
    </Popover>
  )
}
