import { format, isValid, parse } from "date-fns"
import { CalendarIcon } from "lucide-react"
import * as React from "react"
import { DateRange } from "react-day-picker"

import { Button } from "@/components/ui/button"
import { Calendar } from "@/components/ui/calendar"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover"
import { cn } from "@/lib/utils"

interface SimpleDateRangePickerProps {
  value?: DateRange
  onChange?: (value: DateRange | undefined) => void
  placeholder?: string
  className?: string
  /**
   * If true, uses global state management for date range persistence across the application.
   * When enabled, the date range will be stored in localStorage and shared across all pages.
   * If false or undefined, uses local state (controlled by value/onChange props).
   */
  useGlobalState?: boolean
}

export function SimpleDateRangePicker({
  value,
  onChange,
  placeholder = "Select date range",
  className,
  useGlobalState = false,
}: SimpleDateRangePickerProps) {
  // Use global state if enabled, otherwise use local props
  const globalState = useDateRange()
  
  const actualValue = useGlobalState ? globalState.dateRange : value
  const actualOnChange = useGlobalState ? globalState.setDateRange : onChange

  const [open, setOpen] = React.useState(false)
  const [tempValue, setTempValue] = React.useState<DateRange | undefined>(actualValue)
  const [fromInput, setFromInput] = React.useState("")
  const [toInput, setToInput] = React.useState("")
  const [isMobile, setIsMobile] = React.useState(false)

  // Check for mobile viewport
  React.useEffect(() => {
    const checkMobile = () => {
      setIsMobile(window.innerWidth < 768)
    }

    checkMobile()
    window.addEventListener('resize', checkMobile)
    return () => window.removeEventListener('resize', checkMobile)
  }, [])

  // Update temp value when popover opens
  React.useEffect(() => {
    if (open) {
      setTempValue(actualValue)
      setFromInput(actualValue?.from ? format(actualValue.from, "MM/dd/yyyy") : "")
      setToInput(actualValue?.to ? format(actualValue.to, "MM/dd/yyyy") : "")
    }
  }, [open, actualValue])

  const formatDateRange = (dateRange?: DateRange): string => {
    if (!dateRange?.from) {
      return placeholder
    }

    if (dateRange.to) {
      return `${format(dateRange.from, "MMM dd, yyyy")} - ${format(dateRange.to, "MMM dd, yyyy")}`
    }

    return format(dateRange.from, "MMM dd, yyyy")
  }

  const handleFromInputChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const input = e.target.value
    setFromInput(input)

    const parsedDate = parse(input, "MM/dd/yyyy", new Date())
    if (isValid(parsedDate)) {
      setTempValue(prev => ({ from: parsedDate, to: prev?.to }))
    }
  }

  const handleToInputChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const input = e.target.value
    setToInput(input)

    const parsedDate = parse(input, "MM/dd/yyyy", new Date())
    if (isValid(parsedDate)) {
      setTempValue(prev => ({ from: prev?.from, to: parsedDate }))
    }
  }

  const handleCalendarSelect = (range: DateRange | undefined) => {
    setTempValue(range)
    if (range?.from) {
      setFromInput(format(range.from, "MM/dd/yyyy"))
    }
    if (range?.to) {
      setToInput(format(range.to, "MM/dd/yyyy"))
    }
  }

  const handleApply = () => {
    actualOnChange?.(tempValue)
    setOpen(false)
  }

  const handleCancel = () => {
    setTempValue(actualValue)
    setOpen(false)
  }

  return (
    <Popover open={open} onOpenChange={setOpen}>
      <PopoverTrigger asChild>
        <Button
          variant="outline"
          className={cn(
            "justify-start text-left font-normal bg-white",
            !actualValue?.from && "text-muted-foreground",
            className
          )}
        >
          <CalendarIcon className="h-4 w-4" />
          <span className='text-theme-sm text-gray-500 dark:text-gray-400'>{formatDateRange(actualValue)}</span>
        </Button>
      </PopoverTrigger>
      <PopoverContent className={cn("w-auto p-0", isMobile && "w-[calc(100vw-2rem)]")} align={isMobile ? "center" : "start"}>
        <div className="flex flex-col">
          <div className="flex gap-2 p-3 border-b mt-7">
            <div className="flex flex-col gap-1.5 flex-1">
              <Label htmlFor="from-date" className="text-xs">From</Label>
              <Input
                id="from-date"
                type="text"
                value={fromInput}
                onChange={handleFromInputChange}
                placeholder="MM/DD/YYYY"
                className="h-8 text-xs w-full min-w-[100px]"
              />
            </div>
            <div className="flex flex-col gap-1.5 flex-1">
              <Label htmlFor="to-date" className="text-xs">To</Label>
              <Input
                id="to-date"
                type="text"
                value={toInput}
                onChange={handleToInputChange}
                placeholder="MM/DD/YYYY"
                className="h-8 text-xs w-full min-w-[100px]"
              />
            </div>
          </div>
          <div className="flex justify-center">
            <Calendar
              mode="range"
              selected={tempValue}
              onSelect={handleCalendarSelect}
              numberOfMonths={isMobile ? 1 : 2}
              defaultMonth={tempValue?.from || value?.from}
              className="border-b text-xs p-2 [&_button[data-range-middle=true]]:bg-gray-100 [&_button[data-range-middle=true]]:text-gray-900 dark:[&_button[data-range-middle=true]]:bg-gray-800 dark:[&_button[data-range-middle=true]]:text-gray-100 [&_button[data-range-middle=true]]:hover:bg-gray-200 dark:[&_button[data-range-middle=true]]:hover:bg-gray-700 [&_button[data-range-start=true]]:relative [&_button[data-range-start=true]]:z-10 [&_button[data-range-end=true]]:relative [&_button[data-range-end=true]]:z-10"
              classNames={{
                months: cn("flex", isMobile ? "flex-col gap-2" : "flex-row gap-2"),
                month: "gap-2",
                caption: "flex justify-center pt-1 pb-2 relative items-center text-xs",
                caption_label: "text-xs font-medium",
                nav: "flex items-center justify-between w-full absolute top-1 left-0 right-0 px-1",
                nav_button: "h-6 w-6 bg-transparent p-0 opacity-50 hover:opacity-100",
                table: "w-full border-collapse space-y-1",
                head_row: "flex",
                head_cell: "text-muted-foreground rounded-md w-7 font-normal text-[0.65rem]",
                row: "flex w-full mt-1",
                cell: "relative p-0 text-center text-xs focus-within:relative focus-within:z-20 [&:has([aria-selected].day-range-middle)]:bg-accent [&:has([aria-selected].day-range-end)]:rounded-r-md [&:has([aria-selected].day-range-start)]:rounded-l-md first:[&:has([aria-selected])]:rounded-l-md last:[&:has([aria-selected])]:rounded-r-md",
                day: "h-7 w-7 p-0 font-normal text-xs aria-selected:opacity-100 hover:bg-accent hover:text-accent-foreground rounded-md",
                range_start: "range-start bg-primary text-primary-foreground rounded-l-md hover:bg-primary hover:text-primary-foreground focus:bg-primary focus:text-primary-foreground",
                range_end: "range-end bg-primary text-primary-foreground rounded-r-md hover:bg-primary hover:text-primary-foreground focus:bg-primary focus:text-primary-foreground",
                selected: "bg-primary text-primary-foreground hover:bg-primary hover:text-primary-foreground focus:bg-primary focus:text-primary-foreground",
                range_middle: "range-middle bg-gray-100 dark:bg-gray-800 text-gray-900 dark:text-gray-100 rounded-none",
                today: "bg-accent text-accent-foreground font-semibold",
                outside: "text-muted-foreground opacity-50 aria-selected:bg-accent/50 aria-selected:text-muted-foreground aria-selected:opacity-30",
                disabled: "text-muted-foreground opacity-50",
                hidden: "invisible",
              }}
            />
          </div>
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
