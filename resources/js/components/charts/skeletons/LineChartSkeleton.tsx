import ComponentCard from '@/components/common/ComponentCard';

export default function LineChartSkeleton  () {
    return (
        <ComponentCard>
            <div className="relative h-80 w-full pb-12">
                {/* Y-axis labels skeleton */}
                <div className="absolute top-0 -left-2 flex h-full flex-col justify-between py-4">
                    {[...Array(6)].map((_, i) => (
                        <div
                            key={i}
                            className="h-4 w-12 animate-pulse rounded bg-gray-200"
                        />
                    ))}
                </div>

                {/* Chart area skeleton */}
                <div className="relative ml-16 h-full">
                    {/* Horizontal grid lines */}
                    <div className="absolute inset-0 flex flex-col justify-between">
                        {[...Array(6)].map((_, i) => (
                            <div
                                key={i}
                                className="h-px w-full border-t border-dashed border-gray-200"
                            />
                        ))}
                    </div>

                    {/* Vertical grid lines */}
                    <div className="absolute inset-0 flex justify-between">
                        {[...Array(11)].map((_, i) => (
                            <div
                                key={i}
                                className="h-full w-px border-l border-dashed border-gray-200"
                            />
                        ))}
                    </div>

                    {/* X-axis labels skeleton */}
                    <div className="absolute right-0 -bottom-8 left-0 flex justify-between px-2">
                        {[...Array(10)].map((_, i) => (
                            <div
                                key={i}
                                className="h-4 w-16 animate-pulse rounded bg-gray-200"
                            />
                        ))}
                    </div>
                </div>
            </div>
        </ComponentCard>
    );
}
