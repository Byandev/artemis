import ComponentCard from '@/components/common/ComponentCard';
import Chart from "react-apexcharts";
import { useEffect, useState } from 'react';
import { Product } from '@/types/models/Product';
import axios from 'axios';
import { Workspace } from '@/types/models/Workspace';
import {
    percentageFormatter,
} from '@/lib/utils';
import { chartOptions } from '@/pages/workspaces/products/partials/analytics/chart';

type Props  = {
    workspace: Workspace
}

const RtsBreakdown = ({ workspace }: Props) => {
    const [products, setProducts] = useState<Product[]>([])
    const [loading, setLoading] = useState(true)

    useEffect(() => {
        setLoading(true)
        axios.get(`/workspaces/${workspace.slug}/products/analytics/metrics`, {
            params: {
                metric: 'rts',
                per_page: 1000
            }
        })
            .then((response) => {
                setProducts(response.data.data)
                setLoading(false)
            })
            .finally(() => setLoading(false))
    }, [workspace.slug]);

    return <ComponentCard title='RTS Breakdown'>
        <div className="max-w-full overflow-x-auto custom-scrollbar">
            <div className="-ml-5 w-full pl-2">
                <Chart options={chartOptions({ yAxisLabelFormatter: percentageFormatter, tooltipFormatter: percentageFormatter })} series={[
                    {
                        name: "RTS",
                        data: products.map(product => ({
                            x: product.name,
                            y: product.rts
                        })),
                    },
                ]} type="bar" height={250} />
            </div>
        </div>
    </ComponentCard>
}


export default RtsBreakdown;
