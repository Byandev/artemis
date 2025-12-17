import ComponentCard from '@/components/common/ComponentCard';
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
    workspace: Workspace
}

const METRICS = [
    { name: 'sales', key: 'advertising_sales', formatter: currencyFormatter },
    { name: 'advertising-sales', key: 'advertising_sales', formatter: currencyFormatter },
    { name: 'ad-spent', key: 'ad_spent', formatter: currencyFormatter },
    { name: 'roas', key: 'roas', formatter: numberFormatter },
    { name: 'rts', key: 'rts', formatter: percentageFormatter },
]

const TopProducts = ({ workspace }: Props) => {
    const [products, setProducts] = useState<Product[]>([])
    const [metric, setMetric] = useState<string>('advertising-sales')

    useEffect(() => {
        axios.get(`/workspaces/${workspace.slug}/products/analytics/top/${metric}`)
            .then((response) => setProducts(response.data))
    }, [workspace.slug, metric]);

    return <div
        className={`rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]`}
    >
         <div className="px-6 py-5 flex justify-between items-center">
            <h3 className="text-base font-medium text-gray-800 dark:text-white/90">
                Top Products
            </h3>

             <Select value={metric} onValueChange={(value) => setMetric(METRICS.find(m => m.name === value)?.name as string)}>
                 <SelectTrigger className="w-[180px]">
                     <SelectValue placeholder="Select metric" />
                 </SelectTrigger>
                 <SelectContent>
                     <SelectGroup>
                         {
                             METRICS.map(metric => <SelectItem value={metric.name}>{metric.name}</SelectItem>)
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
                            Sales
                        </th>
                    </tr>
                </thead>

                <tbody className='[&_tr:last-child]:border-0'>
                {
                    products.map(product =>
                        <tr key={product.code}>
                            <td className='align-middle whitespace-nowrap [&:has([role=checkbox])]:pr-0 [&>[role=checkbox]]:translate-y-[2px] px-4 py-3 border border-gray-100 dark:border-white/[0.05]text-gray-700 text-theme-xs dark:text-gray-400'>
                                {product.name}
                            </td>
                            <td className='align-middle whitespace-nowrap [&:has([role=checkbox])]:pr-0 [&>[role=checkbox]]:translate-y-[2px] px-4 py-3 border border-gray-100 dark:border-white/[0.05]text-gray-700 text-theme-xs dark:text-gray-400'>
                                {METRICS.find(m => m.name === me)}
                            </td>
                        </tr>
                    )
                }
                </tbody>
            </table>
        </div>
    </div>
}

export default TopProducts;
