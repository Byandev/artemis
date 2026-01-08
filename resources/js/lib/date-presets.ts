import { endOfMonth, endOfToday, endOfWeek, startOfMonth, startOfToday, startOfWeek, subDays, subMonths, subWeeks } from "date-fns"
import { DateRange } from "react-day-picker"

export type PresetIconType = "clock" | "calendar-days" | "calendar-range" | "sparkles"

export interface DatePreset {
  label: string
  getValue: () => DateRange
  icon: PresetIconType
}

export function isPresetSelected(preset: DatePreset, currentRange?: DateRange): boolean {
  if (!currentRange?.from) return false
  const presetRange = preset.getValue()
  return (
    currentRange.from.toDateString() === presetRange.from?.toDateString() &&
    currentRange.to?.toDateString() === presetRange.to?.toDateString()
  )
}

export const dateRangePresets: DatePreset[] = [
  {
    label: "Today",
    icon: "clock",
    getValue: () => ({
      from: startOfToday(),
      to: endOfToday(),
    }),
  },
  {
    label: "Yesterday",
    icon: "clock",
    getValue: () => {
      const yesterday = subDays(new Date(), 1)
      return {
        from: yesterday,
        to: yesterday,
      }
    },
  },
  {
    label: "Last 7 days",
    icon: "sparkles",
    getValue: () => ({
      from: subDays(new Date(), 6),
      to: new Date(),
    }),
  },
  {
    label: "Last 14 days",
    icon: "sparkles",
    getValue: () => ({
      from: subDays(new Date(), 13),
      to: new Date(),
    }),
  },
  {
    label: "Last 30 days",
    icon: "sparkles",
    getValue: () => ({
      from: subDays(new Date(), 29),
      to: new Date(),
    }),
  },
  {
    label: "This Week",
    icon: "calendar-days",
    getValue: () => ({
      from: startOfWeek(new Date()),
      to: endOfWeek(new Date()),
    }),
  },
  {
    label: "Last Week",
    icon: "calendar-days",
    getValue: () => {
      const lastWeek = subWeeks(new Date(), 1)
      return {
        from: startOfWeek(lastWeek),
        to: endOfWeek(lastWeek),
      }
    },
  },
  {
    label: "This Month",
    icon: "calendar-range",
    getValue: () => ({
      from: startOfMonth(new Date()),
      to: endOfMonth(new Date()),
    }),
  },
  {
    label: "Last Month",
    icon: "calendar-range",
    getValue: () => {
      const lastMonth = subMonths(new Date(), 1)
      return {
        from: startOfMonth(lastMonth),
        to: endOfMonth(lastMonth),
      }
    },
  },
]
