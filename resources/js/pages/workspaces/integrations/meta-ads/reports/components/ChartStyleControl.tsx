import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import {
    AreaChart as AreaChartIcon,
    BarChart3,
    BarChartHorizontal,
    ChevronDown,
    LayoutGrid,
    LineChart as LineChartIcon,
} from 'lucide-react';
import { useState } from 'react';
import { CHART_LABELS, type ChartStyle } from '../types';

const CHART_ICONS: Record<ChartStyle, typeof LayoutGrid> = {
    gallery: LayoutGrid,
    bar: BarChart3,
    stacked_bar: BarChartHorizontal,
    line: LineChartIcon,
    area: AreaChartIcon,
};

/** SuperAds-style chart-type switcher (Gallery / Bar / Stacked / Line / Area). */
export function ChartStyleControl({
    value,
    onChange,
}: {
    value: ChartStyle;
    onChange: (chart: ChartStyle) => void;
}) {
    const [open, setOpen] = useState(false);
    const ActiveIcon = CHART_ICONS[value];

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <button
                    type="button"
                    className="inline-flex h-8 items-center gap-1.5 rounded-md border border-black/10 bg-white px-2.5 text-xs font-medium text-gray-700 hover:border-emerald-400 dark:border-white/10 dark:bg-zinc-900 dark:text-gray-200"
                >
                    <ActiveIcon className="h-3.5 w-3.5" />
                    {CHART_LABELS[value]}
                    <ChevronDown className="h-3 w-3 opacity-50" />
                </button>
            </PopoverTrigger>
            <PopoverContent align="end" className="w-56 p-2">
                <p className="px-1 pb-1.5 text-[10px] font-semibold tracking-wider text-gray-400 uppercase">
                    Chart style
                </p>
                <div className="grid grid-cols-3 gap-1.5">
                    {(Object.keys(CHART_LABELS) as ChartStyle[]).map((key) => {
                        const Icon = CHART_ICONS[key];
                        return (
                            <button
                                key={key}
                                type="button"
                                onClick={() => {
                                    onChange(key);
                                    setOpen(false);
                                }}
                                className={`flex flex-col items-center gap-1 rounded-lg border px-2 py-2.5 text-[10px] transition-colors ${
                                    key === value
                                        ? 'border-emerald-500 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300'
                                        : 'border-black/8 text-gray-600 hover:border-emerald-400 dark:border-white/8 dark:text-gray-300'
                                }`}
                            >
                                <Icon className="h-4 w-4" />
                                {CHART_LABELS[key]}
                            </button>
                        );
                    })}
                </div>
            </PopoverContent>
        </Popover>
    );
}
