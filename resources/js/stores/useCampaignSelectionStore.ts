import { create } from 'zustand'
import { createJSONStorage, persist } from 'zustand/middleware'

interface AdsManagerSelectionState {
  selections: Record<string, Record<string, boolean>>
  setSelectedRows: (tableId: string, rowIds: Record<string, boolean>) => void
  toggleRow: (tableId: string, rowId: string) => void
  selectAllRows: (tableId: string, rowIds: string[]) => void
  deselectAllRows: (tableId: string) => void
  clearSelection: (tableId: string) => void
  getTableSelection: (tableId: string) => Record<string, boolean>
}

export const useAdsManagerSelectionStore = create<AdsManagerSelectionState>()(
  persist(
    (set, get) => ({
      selections: {},
      setSelectedRows: (tableId, rowIds) =>
        set((state) => ({
          selections: {
            ...state.selections,
            [tableId]: rowIds,
          },
        })),
      toggleRow: (tableId, rowId) =>
        set((state) => {
          const currentSelection = state.selections[tableId] || {}
          return {
            selections: {
              ...state.selections,
              [tableId]: {
                ...currentSelection,
                [rowId]: !currentSelection[rowId],
              },
            },
          }
        }),
      selectAllRows: (tableId, rowIds) =>
        set((state) => ({
          selections: {
            ...state.selections,
            [tableId]: rowIds.reduce(
              (acc, id) => ({ ...acc, [id]: true }),
              {}
            ),
          },
        })),
      deselectAllRows: (tableId) =>
        set((state) => {
          const currentSelection = state.selections[tableId] || {}
          const newSelection = { ...currentSelection }
          Object.keys(newSelection).forEach((key) => {
            newSelection[key] = false
          })
          return {
            selections: {
              ...state.selections,
              [tableId]: newSelection,
            },
          }
        }),
      clearSelection: (tableId) =>
        set((state) => ({
          selections: {
            ...state.selections,
            [tableId]: {},
          },
        })),
      getTableSelection: (tableId) => {
        const state = get()
        return state.selections[tableId] || {}
      },
    }),
    {
      name: 'ads-manager-selection-storage',
      storage: createJSONStorage(() => localStorage),
    }
  )
)

// Backward compatibility export
export const useCampaignSelectionStore = useAdsManagerSelectionStore
