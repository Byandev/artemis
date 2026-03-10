import LineChart from '@/components/charts/LineChart';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Workspace } from '@/types/models/Workspace';
import axios from 'axios';
import { useEffect, useState } from 'react';

interface Props {
    workspace: Workspace;
}

export function StatisticBreakdown({ workspace }: Props) {
    const [breakdown, setBreakdown] = useState<any[]>([]);
    const [option, setOption] = useState('sales');

    const optionLabels: Record<string, string> = {
        sales: 'Sales',
        orders: 'Orders',
        oav: 'AOV',
        rts: 'RTS',
        ror: 'ROR',
        first_order: 'Time to First Order',
        avg_lifetime: 'Average Lifetime Value',
        avg_delivery: 'Average Delivery Days',
        avg_shiped: 'Average Shipped Out Days',
    };

    useEffect(() => {
        axios
            .get(`/api/v1/workspace/analytics/breakdown`, {
                headers: {
                    'X-Workspace-Id': workspace.id,
                },
                params: {
                    metric: option,
                },
            })
            .then((response) => setBreakdown(response.data.data))
            .catch((error) => console.error(error));
    }, [workspace.id, option]);

    return (
        <div className="space-y-4">
            <div className="flex justify-between">
                <h2 className="text-lg font-semibold">
                    {optionLabels[option]} Breakdown
                </h2>
                <div className="w-80">
                    <Select value={option} onValueChange={setOption}>
                        <SelectTrigger>
                            <SelectValue placeholder="Select options" />
                        </SelectTrigger>

                        <SelectContent>
                            <SelectItem value="sales">Sales</SelectItem>
                            <SelectItem value="orders">Orders</SelectItem>
                            <SelectItem value="oav">AOV</SelectItem>
                            <SelectItem value="rts">RTS</SelectItem>
                            <SelectItem value="ror">ROR</SelectItem>
                            <SelectItem value="first_order">
                                Time to First Order
                            </SelectItem>
                            <SelectItem value="avg_lifetime">
                                AVG Lifetime Value
                            </SelectItem>
                            <SelectItem value="avg_delivery">
                                AVG Delivery Days
                            </SelectItem>
                            <SelectItem value="avg_shiped">
                                AVG Shipped Out Days
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
            </div>

            <LineChart
                categories={breakdown.map((a) => a.period)}
                series={[
                    {
                        name: optionLabels[option],
                        data: breakdown.map((a) => a.value),
                    },
                ]}
            />
        </div>
    );
}
