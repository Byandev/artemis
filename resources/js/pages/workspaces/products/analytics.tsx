
import { useMemo } from 'react';
import ProductLayout from '@/pages/workspaces/products/partials/layout';
import { Workspace } from '@/types/models/Workspace';
import TopProducts from '@/pages/workspaces/products/partials/analytics/TopProducts';
import SalesBreakdown from '@/pages/workspaces/products/partials/analytics/SalesBreakdown';
import AdSpentBreakdown from '@/pages/workspaces/products/partials/analytics/AdSpentBreakdown';
import RoasBreakdown from '@/pages/workspaces/products/partials/analytics/RoasBreakdown';
import RtsBreakdown from '@/pages/workspaces/products/partials/analytics/RtsBreakdown';
import StatusBreakdown from '@/pages/workspaces/products/partials/analytics/StatusBreakdown';
import { sum } from 'lodash';

interface Props {
    workspace: Workspace;
    summary: {
        scaling_product_count: number;
        testing_product_count: number;
        inactive_product_count: number;
        total_product_count: number
    }
}
const Analytics = ({ workspace, summary }: Props) => {
    const items = useMemo(() => {
        return [
            { title: 'Total Products', value: summary.total_product_count },
            { title: 'Scaling Products', value: summary.scaling_product_count },
            { title: 'Testing Products', value: summary.testing_product_count },
            {
                title: 'Inactive Products',
                value: summary.inactive_product_count,
            },
        ];
    }, [summary]);

    return (
        <ProductLayout workspace={workspace}>
            <div className="space-y-5 sm:space-y-6">
                <div className='grid grid-cols-1 gap-4 sm:grid-cols-2 md:gap-6 xl:grid-cols-4'>
                    {items.map((item) => (
                        <div key={item.title} className="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
                            <p className="text-theme-sm text-gray-500 dark:text-gray-400">
                                {item.title}
                            </p>
                            <div className="mt-3 flex items-end justify-between">
                                <div>
                                    <h4 className="text-2xl font-bold text-gray-800 dark:text-white/90">
                                        {item.value}
                                    </h4>
                                </div>
                            </div>
                        </div>
                    ))}
                </div>


                <div className="grid lg:grid-cols-2 gap-4">
                    <TopProducts workspace={workspace}/>

                    <StatusBreakdown
                        scaling_product_count={summary.scaling_product_count}
                        inactive_product_count={summary.inactive_product_count}
                        testing_product_count={summary.testing_product_count}
                    />

                    <SalesBreakdown workspace={workspace}/>

                    <AdSpentBreakdown workspace={workspace} />

                    <RoasBreakdown workspace={workspace} />

                    <RtsBreakdown workspace={workspace} />
                </div>
            </div>
        </ProductLayout>
    );
};

export default Analytics;
