
import axios from 'axios'
import { useEffect, useState } from 'react';
import { Workspace } from '@/types/models/Workspace';
import { Product } from '@/types/models/Product';
import {
    currencyFormatter,
    numberFormatter,
    percentageFormatter,
} from '@/lib/utils';
import { Select, SelectTrigger, SelectValue, SelectContent, SelectGroup, SelectItem } from '@/components/ui/select';

type Props  = {
    workspace: Workspace,
    startDate: string,
    endDate: string,
}

type Metric = {
    name: string,
    key: keyof Product,
    display: string,
    formatter: (value: number, options?: Intl.NumberFormatOptions) => string
}

const METRICS: Metric[] = [
    { display: 'Sales', name: 'sales', key: 'sales', formatter: currencyFormatter },
    { display: 'Advertising Sales', name: 'advertising-sales', key: 'advertising_sales', formatter: currencyFormatter },
    { display: 'Ad Spent',  name: 'ad-spent', key: 'ad_spent', formatter: currencyFormatter },
    { display: 'ROAS', name: 'roas', key: 'roas', formatter: numberFormatter },
    { display: 'RTS', name: 'rts', key: 'rts', formatter: percentageFormatter },
]

const TopProducts = ({ workspace, startDate, endDate }: Props) => {
    const [products, setProducts] = useState<Product[]>([])
    const [metric, setMetric] = useState<Metric>(METRICS[0])
    const [loading, setLoading] = useState(true)

    useEffect(() => {
        setLoading(true)
        axios.get(`/workspaces/${workspace.slug}/products/analytics/metrics`, {
            params: {
                metric: metric.key,
                sort: metric.key === 'rts'? metric.key : `-${metric.key}`,
                start_date: startDate,
                end_date: endDate
            }
        })
            .then((response) => {
                setProducts(response.data.data)
                setLoading(false)
            })
            .finally(() => setLoading(false))
    }, [workspace.slug, metric.key, startDate, endDate]);

    return <div
        className={`rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]`}
    >
         <div className="px-6 py-5 flex justify-between items-center">
            <h3 className="text-base font-medium text-gray-800 dark:text-white/90">
                Top Products
            </h3>

             <Select value={metric.name} onValueChange={(value) => setMetric(METRICS.find(m => m.name === value) as Metric)}>
                 <SelectTrigger className="w-[180px]">
                     <SelectValue placeholder="Select metric" />
                 </SelectTrigger>
                 <SelectContent>
                     <SelectGroup>
                         {
                             METRICS.map(metric => <SelectItem key={metric.key} value={metric.name}>{metric.display}</SelectItem>)
                         }
                     </SelectGroup>
                 </SelectContent>
             </Select>
        </div>

        {/* Card Body */}
        <div className="p-4 border-t border-gray-100 dark:border-gray-800 sm:p-6">
            <table className='min-w-full'>
                <thead className='border-t border-gray-100 dark:border-white/[0.05]'>
                    <tr>
                        <th className='px-4 py-3 border border-gray-100 dark:border-white/[0.05] font-medium text-gray-700 text-theme-xs dark:text-gray-400'>
                            Product
                        </th>
                        <th className='px-4 py-3 border border-gray-100 dark:border-white/[0.05] font-medium text-gray-700 text-theme-xs dark:text-gray-400'>
                            {metric.display}
                        </th>
                    </tr>
                </thead>

                <tbody className='[&_tr:last-child]:border-0'>
                {loading ? (
                    // Loading skeleton rows
                    Array.from({ length: 5 }).map((_, index) => (
                        <tr key={`skeleton-${index}`}>
                            <td className='w-2/3 align-middle whitespace-nowrap [&:has([role=checkbox])]:pr-0 [&>[role=checkbox]]:translate-y-[2px] px-4 py-3 border border-gray-100 dark:border-white/[0.05]text-gray-700 text-theme-xs dark:text-gray-400'>
                                <div className='h-4 bg-gray-200 dark:bg-gray-700 rounded animate-pulse' />
                            </td>
                            <td className='w-1/3 align-middle whitespace-nowrap [&:has([role=checkbox])]:pr-0 [&>[role=checkbox]]:translate-y-[2px] px-4 py-3 border border-gray-100 dark:border-white/[0.05]text-gray-700 text-theme-xs dark:text-gray-400'>
                                <div className='h-4 bg-gray-200 dark:bg-gray-700 rounded animate-pulse' />
                            </td>
                        </tr>
                    ))
                ) : (
                    products.map(product =>
                        <tr key={product.code}>
                            <td className='w-2/3 align-middle whitespace-nowrap [&:has([role=checkbox])]:pr-0 [&>[role=checkbox]]:translate-y-[2px] px-4 py-3 border border-gray-100 dark:border-white/[0.05]text-gray-700 text-theme-xs dark:text-gray-400'>
                                {product.name}
                            </td>
                            <td className='w-1/3 align-middle whitespace-nowrap [&:has([role=checkbox])]:pr-0 [&>[role=checkbox]]:translate-y-[2px] px-4 py-3 border border-gray-100 dark:border-white/[0.05]text-gray-700 text-theme-xs dark:text-gray-400'>
                                {metric.formatter(product[metric.key] as number)}
                            </td>
                        </tr>
                    )
                )}
                </tbody>
            </table>
        </div>
    </div>
}

export default TopProducts;
