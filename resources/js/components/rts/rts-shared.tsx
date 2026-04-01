import { BarChart2, Table } from 'lucide-react';

export type ViewMode = 'table' | 'chart';

export type RtsQueryParams = {
    startDate: string;
    endDate: string;
    pageIds: number[];
    shopIds: number[];
};

export type DeliveryAttemptRow = {
    delivery_attempts: string | null;
    total_orders: number;
    delivered_count: number;
    returned_count: number;
    rts_rate_percentage: number;
};

export type CxRtsRow = {
    cx_rts_bucket: string;
    total_orders: number;
    delivered_count: number;
    returned_count: number;
    rts_rate_percentage: number;
};

export type OrderItemRow = {
    item_name: string | null;
    total_orders: number;
    delivered_count: number;
    returned_count: number;
    rts_rate_percentage: number;
};

export type PriceRow = {
    price_bucket: string | null;
    total_orders: number;
    delivered_count: number;
    returned_count: number;
    rts_rate_percentage: number;
};

export type AdRow = {
    ad_id: string | null;
    ad_name: string | null;
    total_orders: number;
    delivered_count: number;
    returned_count: number;
    rts_rate_percentage: number;
};

export type ConfirmedByRow = {
    confirmed_by_name: string | null;
    total_orders: number;
    delivered_count: number;
    returned_count: number;
    rts_rate_percentage: number;
};

export type OrderFrequencyRow = {
    order_frequency: string;
    total_orders: number;
    delivered_count: number;
    returned_count: number;
    rts_rate_percentage: number;
};

export type RiderRow = {
    rider_name: string | null;
    total_orders: number;
    delivered_count: number;
    returned_count: number;
    rts_rate_percentage: number;
};

export type ProvinceRow = {
    province_name: string;
    total_orders: number;
    delivered_count: number;
    returned_count: number;
    rts_rate_percentage: number;
};

export type CityRow = {
    city_name: string;
    province_name: string;
    total_orders: number;
    delivered_count: number;
    returned_count: number;
    rts_rate_percentage: number;
};

export const PRICE_LABELS: Record<string, string> = {
    '0-250':     '₱0 – ₱250',
    '251-500':   '₱251 – ₱500',
    '501-750':   '₱501 – ₱750',
    '751-1000':  '₱751 – ₱1,000',
    '1001-1500': '₱1,001 – ₱1,500',
    '1501-2000': '₱1,501 – ₱2,000',
    '2001-3000': '₱2,001 – ₱3,000',
    '3001-5000': '₱3,001 – ₱5,000',
    '5000+':     '₱5,000+',
};

export const CX_RTS_LABELS: Record<string, string> = {
    no_report: 'No Report',
    '0-10':   '0 – 10%',
    '11-20':  '11 – 20%',
    '21-30':  '21 – 30%',
    '31-40':  '31 – 40%',
    '41-50':  '41 – 50%',
    '51-60':  '51 – 60%',
    '61-70':  '61 – 70%',
    '71-80':  '71 – 80%',
    '81-90':  '81 – 90%',
    '91-100': '91 – 100%',
};

export function rtsColor(value: number): string {
    if (value <= 15) return 'text-green-600 dark:text-green-400';
    if (value <= 20) return 'text-yellow-500 dark:text-yellow-400';
    if (value <= 25) return 'text-orange-500 dark:text-orange-400';
    return 'font-semibold text-red-500';
}

export const RtsCell = ({ value }: { value: number }) => (
    <span className={rtsColor(value)}>{value}%</span>
);

export const ViewToggle = ({ value, onChange }: { value: ViewMode; onChange: (v: ViewMode) => void }) => (
    <div className="flex overflow-hidden rounded-lg border border-black/8 dark:border-white/8">
        <button
            onClick={() => onChange('table')}
            className={`flex items-center gap-1.5 px-2.5 py-1.5 transition-colors ${value === 'table' ? 'bg-gray-100 text-gray-900 dark:bg-white/10 dark:text-gray-100' : 'text-gray-400 hover:text-gray-700 dark:text-gray-500 dark:hover:text-gray-300'}`}
        >
            <Table className="h-3.5 w-3.5" />
        </button>
        <button
            onClick={() => onChange('chart')}
            className={`flex items-center gap-1.5 border-l border-black/8 px-2.5 py-1.5 transition-colors dark:border-white/8 ${value === 'chart' ? 'bg-gray-100 text-gray-900 dark:bg-white/10 dark:text-gray-100' : 'text-gray-400 hover:text-gray-700 dark:text-gray-500 dark:hover:text-gray-300'}`}
        >
            <BarChart2 className="h-3.5 w-3.5" />
        </button>
    </div>
);

export function buildBaseParams(params: RtsQueryParams): URLSearchParams {
    const p = new URLSearchParams();
    p.append('start_date', params.startDate);
    p.append('end_date', params.endDate);
    params.pageIds.forEach((id) => p.append('page_ids[]', String(id)));
    params.shopIds.forEach((id) => p.append('shop_ids[]', String(id)));
    return p;
}
