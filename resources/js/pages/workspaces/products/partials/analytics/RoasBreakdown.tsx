import ComponentCard from '@/components/common/ComponentCard';
import Chart from "react-apexcharts";
import { useEffect, useState } from 'react';
import { Product } from '@/types/models/Product';
import axios from 'axios';
import { Workspace } from '@/types/models/Workspace';
import {
    numberFormatter,
} from '@/lib/utils';
import { chartOptions } from '@/pages/workspaces/products/partials/analytics/chart';

type Props  = {
    workspace: Workspace,
    startDate: string,
    endDate: string,
}

const SalesBreakdown = ({ workspace, startDate, endDate }: Props) => {
    const [products, setProducts] = useState<Product[]>([])
    const [loading, setLoading] = useState(true)

    useEffect(() => {
        setLoading(true)
        axios.get(`/workspaces/${workspace.slug}/products/analytics/metrics`, {
            params: {
                metric: 'roas',
                per_page: 1000,
                start_date: startDate,
                end_date: endDate
            }
        })
            .then((response) => {
                setProducts(response.data.data)
                setLoading(false)
            })
            .finally(() => setLoading(false))
    }, [workspace.slug, startDate, endDate]);

    return <ComponentCard title='ROAS Breakdown'>
        <div className="max-w-full overflow-x-auto custom-scrollbar">
            <div className="-ml-5 w-full pl-2">
                <Chart options={chartOptions({ yAxisLabelFormatter: numberFormatter, tooltipFormatter: numberFormatter })} series={[
                    {
                        name: "ROAS",
                        data: products.map(product => ({
                            x: product.name,
                            y: product.roas
                        })),
                    },
                ]} type="bar" height={250} />
            </div>
        </div>
    </ComponentCard>
}


export default SalesBreakdown;
