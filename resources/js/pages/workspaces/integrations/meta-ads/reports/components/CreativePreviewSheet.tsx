import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Loader2 } from 'lucide-react';
import { useEffect, useState } from 'react';
import { type AdDetail, adDetailUrl, type ReportRow } from '../types';

/** Right-side drawer with Meta's signed creative preview + dimensions. */
export function CreativePreviewSheet({
    slug,
    ad,
    onClose,
}: {
    slug: string;
    ad: ReportRow | null;
    onClose: () => void;
}) {
    const [detail, setDetail] = useState<AdDetail | null>(null);
    const [loading, setLoading] = useState(false);
    const [iframeLoaded, setIframeLoaded] = useState(false);

    useEffect(() => {
        if (!ad) return;
        let active = true;
        setLoading(true);
        setDetail(null);
        setIframeLoaded(false);

        fetch(adDetailUrl(slug, ad.id), {
            headers: { Accept: 'application/json' },
        })
            .then((r) => (r.ok ? r.json() : Promise.reject()))
            .then((d: AdDetail) => {
                if (active) {
                    setDetail(d);
                    setLoading(false);
                }
            })
            .catch(() => active && setLoading(false));

        return () => {
            active = false;
        };
    }, [ad?.id, slug]); // eslint-disable-line react-hooks/exhaustive-deps

    const src = detail?.preview?.src ?? null;
    const dim = detail?.dimensions;
    const showSpinner = loading || (!!src && !iframeLoaded);

    return (
        <Sheet open={!!ad} onOpenChange={(o) => !o && onClose()}>
            <SheetContent
                side="right"
                className="w-full gap-0 overflow-y-auto p-0 sm:max-w-md"
            >
                <SheetHeader className="border-b border-black/6 px-4 py-3 dark:border-white/6">
                    <SheetTitle className="truncate pr-6 text-sm tracking-tight text-gray-800 dark:text-gray-100">
                        {ad?.name ?? 'Creative'}
                    </SheetTitle>
                </SheetHeader>

                <div className="p-4">
                    <div className="flex justify-center rounded-lg bg-stone-100 py-6 dark:bg-zinc-950">
                        <div className="w-[336px] overflow-hidden rounded-[2rem] border-[8px] border-zinc-800 bg-black shadow-xl dark:border-zinc-700">
                            <div className="flex h-6 items-center justify-center bg-zinc-900">
                                <div className="h-1 w-10 rounded-full bg-zinc-600" />
                            </div>
                            <div className="relative h-[560px] bg-white dark:bg-zinc-900">
                                {src && (
                                    <iframe
                                        key={src}
                                        title="Creative preview"
                                        src={src}
                                        onLoad={() => setIframeLoaded(true)}
                                        className={`h-full w-full border-0 ${
                                            iframeLoaded ? '' : 'invisible'
                                        }`}
                                        allowFullScreen
                                    />
                                )}
                                {showSpinner ? (
                                    <div className="absolute inset-0 flex items-center justify-center">
                                        <Loader2 className="h-6 w-6 animate-spin text-gray-400" />
                                    </div>
                                ) : (
                                    !src && (
                                        <div className="absolute inset-0 flex items-center justify-center px-6">
                                            <p className="text-center font-mono text-xs text-gray-400 dark:text-gray-500">
                                                Preview unavailable for this ad.
                                            </p>
                                        </div>
                                    )
                                )}
                            </div>
                        </div>
                    </div>

                    <p className="mt-5 mb-1 text-[10px] font-medium tracking-wider text-gray-400 uppercase dark:text-gray-500">
                        Dimensions
                    </p>
                    <dl className="divide-y divide-black/4 dark:divide-white/4">
                        <PreviewRow label="Ad status" value={dim?.ad_status} />
                        <PreviewRow label="Adset" value={dim?.adset_name} />
                        <PreviewRow
                            label="Campaign"
                            value={dim?.campaign_name}
                        />
                        <PreviewRow label="Account" value={dim?.account_name} />
                        <PreviewRow label="Ad type" value={dim?.ad_type} />
                        <PreviewRow
                            label="Call to action"
                            value={dim?.call_to_action}
                        />
                    </dl>
                </div>
            </SheetContent>
        </Sheet>
    );
}

function PreviewRow({
    label,
    value,
}: {
    label: string;
    value: string | null | undefined;
}) {
    return (
        <div className="grid grid-cols-[120px_1fr] gap-2 py-1.5">
            <dt className="text-[11px] text-gray-400 dark:text-gray-500">
                {label}
            </dt>
            <dd className="text-xs text-gray-700 dark:text-gray-200">
                {value ?? <span className="text-gray-300">—</span>}
            </dd>
        </div>
    );
}
