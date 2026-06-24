import { Skeleton } from '@/components/ui/skeleton';
import { ImageOff, Video } from 'lucide-react';
import { formatMetricValue, metricLabel } from '../../_shared';
import { type ReportRow } from '../types';

/** A single gallery tile: optional thumbnail + the selected metrics. */
export function GalleryCard({
    row,
    metrics,
    showThumbnail,
    onPreview,
}: {
    row: ReportRow;
    metrics: string[];
    showThumbnail: boolean;
    onPreview?: () => void;
}) {
    const thumb = row.thumbnail_url || row.image_url || null;

    return (
        <div className="flex flex-col overflow-hidden rounded-xl border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
            {showThumbnail && (
                <button
                    type="button"
                    onClick={onPreview}
                    title="View creative"
                    className="group relative aspect-square w-full cursor-pointer bg-gray-100 dark:bg-zinc-800"
                >
                    {thumb ? (
                        <img
                            src={thumb}
                            alt={row.name ?? ''}
                            className="h-full w-full object-cover"
                            loading="lazy"
                        />
                    ) : (
                        <div className="flex h-full w-full items-center justify-center text-gray-300 dark:text-gray-600">
                            <ImageOff className="h-8 w-8" />
                        </div>
                    )}
                    {row.media_type === 'video' && (
                        <span className="absolute bottom-2 left-2 inline-flex items-center gap-1 rounded bg-black/60 px-1.5 py-0.5 text-[10px] font-medium text-white">
                            <Video className="h-3 w-3" />
                            Video
                        </span>
                    )}
                    <span className="absolute inset-0 bg-black/0 transition-colors group-hover:bg-black/10" />
                </button>
            )}
            <div className="flex flex-1 flex-col p-3">
                <p
                    className="truncate text-xs font-semibold text-gray-900 dark:text-gray-100"
                    title={row.name ?? ''}
                >
                    {row.name || '—'}
                </p>
                <dl className="mt-2 space-y-1">
                    {metrics.map((id) => (
                        <div
                            key={id}
                            className="flex items-center justify-between gap-2"
                        >
                            <dt className="truncate text-[11px] text-gray-400 dark:text-gray-500">
                                {metricLabel(id)}
                            </dt>
                            <dd className="font-mono text-[11px] font-medium text-gray-700 tabular-nums dark:text-gray-200">
                                {formatMetricValue(row, id)}
                            </dd>
                        </div>
                    ))}
                </dl>
            </div>
        </div>
    );
}

export function GallerySkeleton() {
    return (
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
            {Array.from({ length: 8 }).map((_, i) => (
                <div
                    key={i}
                    className="overflow-hidden rounded-xl border border-black/6 dark:border-white/6"
                >
                    <Skeleton className="aspect-square w-full" />
                    <div className="space-y-2 p-3">
                        <Skeleton className="h-3 w-3/4" />
                        <Skeleton className="h-3 w-full" />
                        <Skeleton className="h-3 w-full" />
                    </div>
                </div>
            ))}
        </div>
    );
}
