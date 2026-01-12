import { useDateRangeStore } from '@/stores/useDateRangeStore'
import moment from 'moment'
import { useEffect } from 'react'

interface UseDateRangeOptions {
  startDate?: string
  endDate?: string
}

/**
 * Hook for managing global date range state with localStorage persistence.
 * 
 * Initializes from provided startDate/endDate or defaults to current month.
 * 
 * @param {UseDateRangeOptions} [options] - Optional startDate and endDate (YYYY-MM-DD format)
 * @returns {Object} dateRange, setDateRange, clearDateRange
 * 
 */
export function useDateRange(options?: UseDateRangeOptions) {
  const dateRange = useDateRangeStore((state) => state.dateRange)
  const setDateRange = useDateRangeStore((state) => state.setDateRange)
  const clearDateRange = useDateRangeStore((state) => state.clearDateRange)

  // Initialize date range from dates or default to current month
  useEffect(() => {
    if (!dateRange && (options?.startDate || options?.endDate)) {
      setDateRange({
        from: options.startDate ? new Date(options.startDate) : moment().startOf('month').toDate(),
        to: options.endDate ? new Date(options.endDate) : moment().toDate()
      })
    } else if (!dateRange) {
      // Default to current month if no dates and no global state
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
