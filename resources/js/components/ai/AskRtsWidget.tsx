import { Workspace } from '@/types/models/Workspace';
import { PhilippinePeso, Package, Truck, ShieldAlert, MapPin, Repeat } from 'lucide-react';
import AskWidget, { AskWidgetSection } from './AskWidget';

export interface RtsData {
    price: object[];
    products: object[];
    riders: object[];
    customerRisk: object[];
    provinces: object[];
    orderFrequency: object[];
}

interface Props {
    workspace: Workspace;
    dateRange: string[];
    data: RtsData;
}

const SECTIONS: AskWidgetSection[] = [
    {
        key: 'price',
        label: 'Price Ranges',
        icon: PhilippinePeso,
        color: 'bg-green-500/10 text-green-500',
        defaultQuestion: 'Which price ranges have the worst RTS rate? What should I do?',
    },
    {
        key: 'products',
        label: 'Products',
        icon: Package,
        color: 'bg-blue-500/10 text-blue-500',
        defaultQuestion: 'Which products are being returned the most? Should I stop selling any?',
    },
    {
        key: 'riders',
        label: 'Riders',
        icon: Truck,
        color: 'bg-amber-500/10 text-amber-500',
        defaultQuestion: 'Are any riders performing significantly worse than others?',
    },
    {
        key: 'customerRisk',
        label: 'Customer Risk',
        icon: ShieldAlert,
        color: 'bg-red-500/10 text-red-500',
        defaultQuestion: 'How many orders came from high-risk customers? What is the impact?',
    },
    {
        key: 'location',
        label: 'Location',
        icon: MapPin,
        color: 'bg-purple-500/10 text-purple-500',
        defaultQuestion: 'Which provinces have the highest return rates? Should I restrict delivery there?',
    },
    {
        key: 'orderFrequency',
        label: 'Order Frequency',
        icon: Repeat,
        color: 'bg-brand-500/10 text-brand-500',
        defaultQuestion: 'Do repeat buyers return less? How does order frequency affect my RTS rate?',
    },
];

export default function AskRtsWidget({ workspace, dateRange, data }: Props) {
    const getSectionData = (key: string): object => {
        const period = `${dateRange[0]} to ${dateRange[1]}`;
        switch (key) {
            case 'price':          return { period, price_by_range: data.price };
            case 'products':       return { period, products: data.products };
            case 'riders':         return { period, riders: data.riders };
            case 'customerRisk':   return { period, customer_rts_buckets: data.customerRisk };
            case 'location':       return { period, provinces: data.provinces };
            case 'orderFrequency': return { period, order_frequency: data.orderFrequency };
            default:               return { period };
        }
    };

    return (
        <AskWidget
            workspace={workspace}
            dateRange={dateRange}
            sections={SECTIONS}
            getSectionData={getSectionData}
            title="Ask RTS Data"
            secondSuggestedQuestion="What is the single biggest driver of my RTS rate right now?"
            fabClass="bg-brand-500 shadow-brand-500/30 hover:bg-brand-600 hover:shadow-brand-500/40"
            headerIconClass="bg-brand-500/10"
            headerIconTextClass="text-brand-500"
        />
    );
}
