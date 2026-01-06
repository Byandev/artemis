import { useDateRangeStore } from '@/stores/useDateRangeStore'
import { DateRange } from 'react-day-picker'

/**
 * Custom hook for managing global date range state
 * Provides easy access to the date range store with localStorage persistence
 * 
 * @example
 * ```tsx
 * const { dateRange, setDateRange, clearDateRange } = useDateRange()
 * 
 * // Use in component
 * <SimpleDateRangePicker value={dateRange} onChange={setDateRange} />
 * ```
 */
export function useDateRange() {
  const dateRange = useDateRangeStore((state) => state.dateRange)
  const setDateRange = useDateRangeStore((state) => state.setDateRange)
  const clearDateRange = useDateRangeStore((state) => state.clearDateRange)

  return {
    dateRange,
    setDateRange,
    clearDateRange,
  }
}
