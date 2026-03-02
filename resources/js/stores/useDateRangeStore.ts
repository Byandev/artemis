import { create } from 'zustand'
import { persist, createJSONStorage } from 'zustand/middleware'
import { DateRange } from 'react-day-picker'

interface DateRangeState {
  dateRange: DateRange | undefined
  setDateRange: (dateRange: DateRange | undefined) => void
  clearDateRange: () => void
}

export const useDateRangeStore = create<DateRangeState>()(
  persist(
    (set) => ({
      dateRange: undefined,
      setDateRange: (dateRange) => set({ dateRange }),
      clearDateRange: () => set({ dateRange: undefined }),
    }),
    {
      name: 'date-range-storage',
      storage: createJSONStorage(() => localStorage),
      // Custom serialization to handle Date objects
      partialize: (state) => ({
        dateRange: state.dateRange
          ? {
              from: state.dateRange.from?.toISOString(),
              to: state.dateRange.to?.toISOString(),
            }
          : undefined,
      }),
      // Custom deserialization to restore Date objects
      merge: (persistedState: any, currentState) => {
        const restored = persistedState as any
        return {
          ...currentState,
          dateRange: restored.dateRange
            ? {
                from: restored.dateRange.from ? new Date(restored.dateRange.from) : undefined,
                to: restored.dateRange.to ? new Date(restored.dateRange.to) : undefined,
              }
            : undefined,
        }
      },
    }
  )
)
