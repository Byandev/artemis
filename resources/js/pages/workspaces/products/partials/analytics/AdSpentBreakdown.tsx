import ComponentCard from '@/components/common/ComponentCard';
import Chart from "react-apexcharts";
import { ApexOptions } from "apexcharts";
import { useEffect, useState } from 'react';
import { Product } from '@/types/models/Product';
import axios from 'axios';
import { Workspace } from '@/types/models/Workspace';
import { currencyFormatter, formatCompactCurrency } from '@/lib/utils';
import { chartOptions } from '@/pages/workspaces/products/partials/analytics/chart';

type Props  = {
    workspace: Workspace
}

const AdSpentBreakdown = ({ workspace }: Props) => {
    const [products, setProducts] = useState<Product[]>([])
    const [loading, setLoading] = useState(true)

    useEffect(() => {
        setLoading(true)
        axios.get(`/workspaces/${workspace.slug}/products/analytics/metrics`, {
            params: {
                metric: 'ad_spent',
                per_page: 1000
            }
        })
            .then((response) => {
                setProducts(response.data.data)
                setLoading(false)
            })
            .finally(() => setLoading(false))
    }, [workspace.slug]);

    return <ComponentCard title='Ad Spent Breakdown'>
        <div className="max-w-full overflow-x-auto custom-scrollbar">
            <div className="-ml-5 w-full pl-2">
                <Chart options={chartOptions({ yAxisLabelFormatter: formatCompactCurrency, tooltipFormatter: currencyFormatter })} series={[
                    {
                        name: "Ad Spent",
                        data: products.map(product => ({
                            x: product.name,
                            y: product.ad_spent
                        })),
                    },
                ]} type="bar" height={250} />
            </div>
        </div>
    </ComponentCard>
}


export default AdSpentBreakdown;
