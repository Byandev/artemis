import type { SortingState } from "@tanstack/react-table"
import { isFunction, isString } from 'lodash';

// Frontend -> Backend
export function toBackendSort(sorting: SortingState): string {
    return sorting.map(s => (s.desc ? `-${s.id}` : s.id)).join(",")
}

// Backend -> Frontend
export function toFrontendSort(sort?: string | null): SortingState {
    if (isString(sort) && sort) {
        return sort
            .split(",")
            .map(part => part.trim())
            .filter(Boolean)
            .map(part => {
                const desc = part.startsWith("-")
                const id = desc ? part.slice(1) : part
                return { id, desc }
            })
    }
    return  [];
}
