import { format } from "date-fns"
import { CalendarDays, CalendarIcon, CalendarRange, Clock, Sparkles } from "lucide-react"
import * as React from "react"
import { DateRange } from "react-day-picker"

import { Button } from "@/components/ui/button"
import { Calendar } from "@/components/ui/calendar"
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover"
import { useDateRange } from "@/hooks/use-date-range"
import { useIsMobile } from "@/hooks/use-mobile"
import { isPresetSelected as checkPresetSelected, DatePreset, dateRangePresets, PresetIconType } from "@/lib/date-presets"
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
  const isMobile = useIsMobile()

  // Update temp value when popover opens or when actualValue changes
  React.useEffect(() => {
    if (open) {
      setTempValue(actualValue)
    }
  }, [open, actualValue])

  // Sync tempValue with actualValue when component mounts or actualValue changes externally
  React.useEffect(() => {
    setTempValue(actualValue)
  }, [actualValue])

  const formatDateRange = (dateRange?: DateRange): string => {
    if (!dateRange?.from) {
      return placeholder
    }

    if (dateRange.to) {
      return `${format(dateRange.from, "MMM dd, yyyy")} - ${format(dateRange.to, "MMM dd, yyyy")}`
    }

    return format(dateRange.from, "MMM dd, yyyy")
  }

  const handleCalendarSelect = (range: DateRange | undefined) => {
    setTempValue(range)
  }

  const handleApply = () => {
    actualOnChange?.(tempValue)
    setOpen(false)
  }

  const handleCancel = () => {
    setTempValue(actualValue)
    setOpen(false)
  }

  const handlePresetClick = (preset: DatePreset) => {
    const range = preset.getValue()
    setTempValue(range)
  }

  const getPresetIcon = (iconType: PresetIconType) => {
    switch (iconType) {
      case "clock":
        return <Clock className="h-3.5 w-3.5" />
      case "calendar-days":
        return <CalendarDays className="h-3.5 w-3.5" />
      case "calendar-range":
        return <CalendarRange className="h-3.5 w-3.5" />
      case "sparkles":
        return <Sparkles className="h-3.5 w-3.5" />
    }
  }

  return (
    <Popover open={open} onOpenChange={setOpen}>
      <PopoverTrigger asChild>
        <Button
          variant="outline"
          className={cn(
            "justify-start text-left font-normal bg-white w-full md:w-auto",
            !actualValue?.from && "text-muted-foreground",
            className
          )}
        >
          <CalendarIcon className="h-4 w-4 shrink-0" />
          <span className='text-xs md:text-sm text-gray-500 dark:text-gray-400 truncate'>{formatDateRange(actualValue)}</span>
        </Button>
      </PopoverTrigger>
      <PopoverContent
        className={cn(
          "w-auto p-0",
          isMobile ? "w-[calc(100vw-2rem)] max-h-[85vh] overflow-y-auto" : "max-w-[900px]"
        )}
        align={isMobile ? "center" : "start"}
      >
        <div className="flex flex-col lg:flex-row">
          {/* Presets Section */}
          <div className="hidden md:block border-r border-gray-200 dark:border-gray-700 bg-linear-to-b from-gray-50/50 to-white dark:from-gray-900/50 dark:to-gray-950 w-48 lg:w-56 p-2">
            <div className="space-y-0.5">
              <div className="flex items-center gap-2 px-3 py-1.5 mb-2">
                <div className="h-1 w-1 rounded-full bg-primary animate-pulse" />
                <h4 className="text-xs font-semibold tracking-wide text-gray-700 dark:text-gray-300 uppercase">
                  Quick Select
                </h4>
              </div>
              <div className="space-y-0.5">
                {dateRangePresets.map((preset) => {
                  const isSelected = checkPresetSelected(preset, open ? tempValue : actualValue)
                  return (
                    <button
                      key={preset.label}
                      onClick={() => handlePresetClick(preset)}
                      className={cn(
                        "group w-full flex items-center gap-2.5 px-3 py-1.5 text-xs rounded-lg transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:ring-offset-1 relative overflow-hidden",
                        isSelected
                          ? "bg-primary text-primary-foreground shadow-sm font-medium"
                          : "hover:bg-gray-100 dark:hover:bg-gray-800 text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-gray-100"
                      )}
                    >
                      <span
                        className={cn(
                          "transition-colors",
                          isSelected
                            ? "text-primary-foreground"
                            : "text-gray-400 dark:text-gray-500 group-hover:text-gray-600 dark:group-hover:text-gray-400"
                        )}
                      >
                        {getPresetIcon(preset.icon)}
                      </span>
                      <span className="flex-1 text-left text-[11px]">{preset.label}</span>
                      {isSelected && (
                        <div className="h-1.5 w-1.5 rounded-full bg-primary-foreground animate-pulse" />
                      )}
                    </button>
                  )
                })}
              </div>
            </div>
          </div>

          {/* Mobile Presets - Show as horizontal scroll on mobile */}
          <div className="md:hidden border-b border-gray-200 dark:border-gray-700 p-2 overflow-x-auto">
            <div className="flex gap-1.5 min-w-max">
              {dateRangePresets.map((preset) => {
                const isSelected = checkPresetSelected(preset, tempValue)
                return (
                  <button
                    key={preset.label}
                    onClick={() => handlePresetClick(preset)}
                    className={cn(
                      "flex items-center gap-1.5 px-3 py-1.5 text-xs rounded-lg whitespace-nowrap transition-all duration-200",
                      isSelected
                        ? "bg-primary text-primary-foreground shadow-sm font-medium"
                        : "bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700"
                    )}
                  >
                    <span className={cn(isSelected ? "text-primary-foreground" : "text-gray-400 dark:text-gray-500")}>
                      {getPresetIcon(preset.icon)}
                    </span>
                    <span>{preset.label}</span>
                  </button>
                )
              })}
            </div>
          </div>

          {/* Calendar Section */}
          <div className="flex flex-col flex-1">
            <div className="flex justify-center md:justify-end">
              <Calendar
                mode="range"
                selected={tempValue}
                onSelect={handleCalendarSelect}
                numberOfMonths={isMobile ? 1 : 2}
                defaultMonth={tempValue?.from || value?.from}
                className="text-[0.7rem] md:text-[0.7rem] p-2 md:p-1.5 [&_button[data-range-middle=true]]:bg-gray-100 [&_button[data-range-middle=true]]:text-gray-900 dark:[&_button[data-range-middle=true]]:bg-gray-800 dark:[&_button[data-range-middle=true]]:text-gray-100 [&_button[data-range-middle=true]]:hover:bg-gray-200 dark:[&_button[data-range-middle=true]]:hover:bg-gray-700 [&_button[data-range-start=true]]:relative [&_button[data-range-start=true]]:z-10 [&_button[data-range-end=true]]:relative [&_button[data-range-end=true]]:z-10 [&_.text-\[0\.8rem\]]:text-[0.65rem] [&_.text-muted-foreground]:text-[0.65rem] [&_.font-medium]:text-[0.7rem]"
              />
            </div>
            <div className="flex items-center justify-end gap-2 p-2 md:p-2 border-t">
              <Button
                variant="ghost"
                size="sm"
                onClick={handleCancel}
                className="h-8 md:h-7 text-xs px-3 md:px-2"
              >
                Cancel
              </Button>
              <Button
                size="sm"
                onClick={handleApply}
                className="h-8 md:h-7 text-xs px-4 md:px-3"
              >
                Apply
              </Button>
            </div>
          </div>
        </div>
      </PopoverContent>
    </Popover>
  )
}
