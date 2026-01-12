import { useDateRangeStore } from '@/stores/useDateRangeStore'
import moment from 'moment'
import { useEffect } from 'react'

interface UseeDateRangeOptions {
  filters?: {
    start_date?: string
    end_date?: string
  }
}

/**
 * Custom hook for managing global date range state
 * Provides easy access to the date range store with localStorage persistence
 * 
 * Automatically initializes the date range from URL filters on mount if global state is empty.
 * Defaults to current month if no filters are provided.
 * 
 * @param options - Optional configuration with filters object
 * @example
 * ```tsx
 * // Basic usage
 * const { dateRange, setDateRange, clearDateRange } = useDateRange()
 * 
 * // With automatic initialization from URL filters
 * const { dateRange, setDateRange } = useDateRange({
 *   filters: { start_date: '2024-01-01', end_date: '2024-01-31' }
 * })
 * 
 * // Use in component
 * <SimpleDateRangePicker value={dateRange} onChange={setDateRange} />
 * ```
 */
export function useDateRange(options?: UseeDateRangeOptions) {
  const dateRange = useDateRangeStore((state) => state.dateRange)
  const setDateRange = useDateRangeStore((state) => state.setDateRange)
  const clearDateRange = useDateRangeStore((state) => state.clearDateRange)

  // Initialize date range from URL filters or default to current month
  useEffect(() => {
    if (!dateRange && (options?.filters?.start_date || options?.filters?.end_date)) {
      setDateRange({
        from: options.filters.start_date ? new Date(options.filters.start_date) : moment().startOf('month').toDate(),
        to: options.filters.end_date ? new Date(options.filters.end_date) : moment().toDate()
      })
    } else if (!dateRange) {
      // Default to current month if no filters and no global state
      setDateRange({
        from: moment().startOf('month').toDate(),
        to: moment().toDate()
      })
    }
  }, []) // Only run on mount

  return {
    dateRange,
    setDateRange,
    clearDateRange,
  }
}
