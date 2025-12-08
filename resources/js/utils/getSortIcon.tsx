import React from 'react'
import { ArrowDown, ArrowUp, ArrowUpDown } from 'lucide-react'

export const getSortIcon = (
    field: string,
    sortField: string,
    sortDirection: 'asc' | 'desc' | ''
) => {
    if (sortField !== field) return <ArrowUpDown className="ml-2 h-4 w-4 text-muted-foreground" />
    if (sortDirection === 'asc') return <ArrowUp className="ml-2 h-4 w-4 text-muted-foreground" />
    if (sortDirection === 'desc') return <ArrowDown className="ml-2 h-4 w-4 text-muted-foreground" />
    return <ArrowUpDown className="ml-2 h-4 w-4 text-muted-foreground" />
}

export default getSortIcon
