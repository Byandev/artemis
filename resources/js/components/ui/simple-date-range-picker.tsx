import { format } from "date-fns"
import { CalendarDays, CalendarIcon, CalendarRange, Clock, Sparkles } from "lucide-react"
import * as React from "react"
import { DateRange } from "react-day-picker"

import { Button } from "@/components/ui/button"
import { Calendar } from "@/components/ui/calendar"
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover"
import { useIsMobile } from "@/hooks/use-mobile"
import { isPresetSelected as checkPresetSelected, DatePreset, dateRangePresets, PresetIconType } from "@/lib/date-presets"
import { cn } from "@/lib/utils"

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
  const isMobile = useIsMobile()

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

  const handleCalendarSelect = (range: DateRange | undefined) => {
    setTempValue(range)
  }

  const handleApply = () => {
    onChange?.(tempValue)
    setOpen(false)
  }

  const handleCancel = () => {
    setTempValue(value)
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
            "justify-start text-left font-normal bg-white",
            !value?.from && "text-muted-foreground",
            className
          )}
        >
          <CalendarIcon className="h-4 w-4" />
          <span className='text-theme-sm text-gray-500 dark:text-gray-400'>{formatDateRange(value)}</span>
        </Button>
      </PopoverTrigger>
      <PopoverContent className={cn("w-auto p-0", isMobile && "w-[calc(100vw-2rem)]")} align={isMobile ? "center" : "start"}>
        <div className="flex flex-col md:flex-row">
          {/* Presets Section */}
          {!isMobile && (
            <div className="border-r border-gray-200 dark:border-gray-700 bg-gradient-to-b from-gray-50/50 to-white dark:from-gray-900/50 dark:to-gray-950 w-56 p-2">
              <div className="space-y-0.5">
                <div className="flex items-center gap-2 px-3 py-1.5 mb-2">
                  <div className="h-1 w-1 rounded-full bg-primary animate-pulse" />
                  <h4 className="text-xs font-semibold tracking-wide text-gray-700 dark:text-gray-300 uppercase">
                    Quick Select
                  </h4>
                </div>
                <div className="space-y-0.5">
                  {dateRangePresets.map((preset) => {
                    const isSelected = checkPresetSelected(preset, tempValue)
                    return (
                      <button
                        key={preset.label}
                        onClick={() => handlePresetClick(preset)}
                        className={cn(
                          "group w-full flex items-center gap-2.5 px-3 py-1.5 text-sm rounded-lg transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:ring-offset-1 relative overflow-hidden",
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
                        <span className="flex-1 text-left">{preset.label}</span>
                        {isSelected && (
                          <div className="h-1.5 w-1.5 rounded-full bg-primary-foreground animate-pulse" />
                        )}
                      </button>
                    )
                  })}
                </div>
              </div>
            </div>
          )}

          {/* Calendar Section */}
          <div className="flex flex-col flex-1">
            <div className="flex items-center justify-end gap-2 p-2 border-b">
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
            <div className="flex justify-end">
              <Calendar
                mode="range"
                selected={tempValue}
                onSelect={handleCalendarSelect}
                numberOfMonths={isMobile ? 1 : 2}
                defaultMonth={tempValue?.from || value?.from}
                className="text-[0.7rem] p-1.5 [&_button[data-range-middle=true]]:bg-gray-100 [&_button[data-range-middle=true]]:text-gray-900 dark:[&_button[data-range-middle=true]]:bg-gray-800 dark:[&_button[data-range-middle=true]]:text-gray-100 [&_button[data-range-middle=true]]:hover:bg-gray-200 dark:[&_button[data-range-middle=true]]:hover:bg-gray-700 [&_button[data-range-start=true]]:relative [&_button[data-range-start=true]]:z-10 [&_button[data-range-end=true]]:relative [&_button[data-range-end=true]]:z-10 [&_.text-\\[0\\.8rem\\]]:text-[0.65rem] [&_.text-muted-foreground]:text-[0.65rem] [&_.font-medium]:text-[0.7rem]"
              />
            </div>
          </div>
        </div>
      </PopoverContent>
    </Popover>
  )
}
