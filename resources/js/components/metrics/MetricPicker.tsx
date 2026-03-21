import { useCallback, useMemo, useState } from 'react';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Button } from '@/components/ui/button';
import { ChartNoAxesColumn, SlidersHorizontal, X } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import PageFilter from '@/components/filters/PageFilter';
import ShopFilter from '@/components/filters/ShopFilter';
import { groupedMetrics, metricConfigs, MetricKey } from '@/types/metrics';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { FilterValue } from '@/components/filters/Filters';

interface Props {
    initialValue: MetricKey[];
    onChange: (value: MetricKey[]) => void
}

const MetricPicker = ({ initialValue = [], onChange }: Props) => {
    const [isOpen, setIsOpen] = useState(false);
    const [localValue, setLocalValue] = useState<MetricKey[]>(initialValue);

    const handleOpenChange = useCallback(
        (open: boolean) => {
            setIsOpen(open);
        },
        [],
    );

    // Handle apply button click
    const handleApply = useCallback(() => {
        onChange(localValue);
        setIsOpen(false);
    }, [localValue, onChange]);

    const activeCount = useMemo(() => localValue.length, [localValue]);

    return (
        <Popover open={isOpen} onOpenChange={handleOpenChange}>
            <PopoverTrigger asChild>
                <Button
                    variant="outline"
                    size="default"
                    className="relative gap-2 rounded-full border border-gray-300 px-4"
                >
                    <ChartNoAxesColumn className="h-4 w-4" />
                    Metrics
                    {activeCount > 0 && (
                        <Badge
                            variant="secondary"
                            className="ml-1 h-5 w-5 rounded-full p-0 text-xs"
                        >
                            {activeCount}
                        </Badge>
                    )}
                </Button>
            </PopoverTrigger>

            <PopoverContent
                className="w-[calc(100vw-2rem)] rounded-2xl p-4 sm:w-80"
                align="start"
            >
                <div className="mb-3 flex items-center justify-between">
                    <h3 className="text-sm font-medium">Metrics</h3>
                </div>

                <div className="max-h-72 space-y-3 overflow-y-auto">
                    {groupedMetrics.map((g) => (
                            <div className="space-y-2" key={g.key}>
                                <p className="text-xs font-semibold">
                                    {g.label}
                                </p>

                                <div className="space-y-1">
                                    {g.metrics.map((m) => (
                                        <div
                                            key={m.key}
                                            className="flex items-center gap-x-2 text-xs"
                                        >
                                            <Checkbox
                                                id={m.key}
                                                name={m.key}
                                                checked={localValue.includes(
                                                    m.key,
                                                )}
                                                onCheckedChange={() =>
                                                    setLocalValue((prev) =>
                                                        prev.includes(m.key)
                                                            ? prev.filter(
                                                                  (item) =>
                                                                      item !==
                                                                      m.key,
                                                              )
                                                            : [...prev, m.key],
                                                    )
                                                }
                                            />
                                            <Label
                                                htmlFor={m.key}
                                                className="text-xs text-gray-800"
                                            >
                                                {m.name}
                                            </Label>
                                        </div>
                                    ))}
                                </div>
                            </div>
                    ))}
                </div>

                <div className="mt-4 flex gap-2">
                    <Button
                        size="sm"
                        onClick={handleApply}
                        className="flex-1"
                    >
                        Apply
                    </Button>
                    <Button
                        size="sm"
                        variant="outline"
                        onClick={() => {
                            setLocalValue(initialValue);
                            setIsOpen(false);
                        }}
                    >
                        Cancel
                    </Button>
                </div>
            </PopoverContent>
        </Popover>
    );
};

export default MetricPicker;
