import { Workspace } from '@/types/models/Workspace';
import { metricConfigs } from '@/types/metrics';
import { BarChart2, LayoutGrid, Store, Users } from 'lucide-react';
import AskWidget, { AskWidgetSection } from './AskWidget';

export interface BreakdownSection {
    data: object[];
    metric: string;
}

export interface DashboardData {
    metrics: Record<string, number>;
    pages: BreakdownSection;
    shops: BreakdownSection;
    users: BreakdownSection;
}

interface Props {
    workspace: Workspace;
    dateRange: string[];
    data: DashboardData;
}

const SECTIONS: AskWidgetSection[] = [
    {
        key: 'metrics',
        label: 'Sales & Metrics',
        icon: BarChart2,
        color: 'bg-blue-500/10 text-blue-500',
        defaultQuestion: 'Analyze my metrics. What is working and what needs attention?',
    },
    {
        key: 'pages',
        label: 'Page Performance',
        icon: LayoutGrid,
        color: 'bg-emerald-500/10 text-emerald-500',
        defaultQuestion: 'Which pages are performing well and which are struggling?',
    },
    {
        key: 'shops',
        label: 'Shop Performance',
        icon: Store,
        color: 'bg-amber-500/10 text-amber-500',
        defaultQuestion: 'Which shops are driving the most value?',
    },
    {
        key: 'users',
        label: 'Team Performance',
        icon: Users,
        color: 'bg-brand-500/10 text-brand-500',
        defaultQuestion: 'How is my team performing? Any standouts?',
    },
];

const formatBreakdown = (section: BreakdownSection) => {
    const config = metricConfigs.find(m => m.key === section.metric);
    return (section.data as Array<Record<string, unknown>>).map(item => ({
        ...item,
        value:   config ? config.formatter(Number(item.value)) : item.value,
        _metric: config?.name ?? section.metric,
    }));
};

export default function AskDataWidget({ workspace, dateRange, data }: Props) {
    const getSectionData = (key: string): object => {
        const period = `${dateRange[0]} to ${dateRange[1]}`;
        switch (key) {
            case 'metrics': {
                const formatted: Record<string, string> = {};
                Object.entries(data.metrics).forEach(([k, v]) => {
                    const config = metricConfigs.find(m => m.key === k);
                    formatted[config?.name ?? k] = config ? config.formatter(v) : String(v);
                });
                return { period, ...formatted };
            }
            case 'pages': return { period, metric: metricConfigs.find(m => m.key === data.pages.metric)?.name ?? data.pages.metric, pages: formatBreakdown(data.pages) };
            case 'shops': return { period, metric: metricConfigs.find(m => m.key === data.shops.metric)?.name ?? data.shops.metric, shops: formatBreakdown(data.shops) };
            case 'users': return { period, metric: metricConfigs.find(m => m.key === data.users.metric)?.name ?? data.users.metric, users: formatBreakdown(data.users) };
            default:       return { period };
        }
    };

    return (
        <AskWidget
            workspace={workspace}
            dateRange={dateRange}
            sections={SECTIONS}
            getSectionData={getSectionData}
            title="Ask Your Data"
            secondSuggestedQuestion="What is the single most important thing I should act on right now?"
            fabClass="bg-brand-500 shadow-brand-500/30 hover:bg-brand-600 hover:shadow-brand-500/40"
            headerIconClass="bg-brand-500/10"
            headerIconTextClass="text-brand-500"
        />
    );
}
