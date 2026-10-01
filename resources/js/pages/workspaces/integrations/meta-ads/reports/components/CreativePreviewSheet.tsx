import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { ExternalLink, ImageOff, Loader2 } from 'lucide-react';
import { useEffect, useState } from 'react';
import {
    type AdDetail,
    adDetailUrl,
    PREVIEW_FORMAT_LABELS,
    type ReportRow,
} from '../types';

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

    const preview = detail?.preview ?? null;
    const src = preview?.src ?? null;
    const dim = detail?.dimensions;
    const showSpinner = loading || (!!src && !iframeLoaded);

    // Meta will happily return a frame for a placement the creative can't
    // render in, so the server falls back to other formats and tells us which
    // one it settled on. Say so when it isn't the one we asked for.
    const substituted =
        !!preview?.format &&
        preview.format !== preview.requested_format &&
        PREVIEW_FORMAT_LABELS[preview.format];

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
                                        <PreviewFallback
                                            preview={preview}
                                            adName={ad?.name}
                                        />
                                    )
                                )}
                            </div>
                        </div>
                    </div>

                    {substituted && (
                        <p className="mt-2 text-center text-[11px] text-gray-400 dark:text-gray-500">
                            Showing the {substituted.toLowerCase()} placement —
                            this ad has no{' '}
                            {PREVIEW_FORMAT_LABELS[
                                preview!.requested_format
                            ].toLowerCase()}{' '}
                            preview.
                        </p>
                    )}

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

/**
 * Shown in the phone frame when Meta renders nothing. The synced creative
 * thumbnail and copy are usually enough to recognise the ad; the links are the
 * ways to go and look at the real thing when they aren't.
 */
function PreviewFallback({
    preview,
    adName,
}: {
    preview: AdDetail['preview'] | null;
    adName?: string | null;
}) {
    const fallback = preview?.fallback;
    const image = fallback?.image_url ?? null;

    const explanation =
        preview?.reason === 'story_unavailable'
            ? 'Meta can’t render this ad — the post behind it was most likely deleted.'
            : 'Meta returned no preview for this ad.';

    return (
        <div className="absolute inset-0 flex flex-col items-center justify-center gap-2.5 overflow-y-auto px-5 py-6 text-center">
            {image ? (
                <img
                    src={image}
                    alt={adName ?? 'Creative thumbnail'}
                    className="max-h-40 rounded-md object-contain"
                />
            ) : (
                <ImageOff className="h-7 w-7 text-gray-300 dark:text-gray-600" />
            )}

            {fallback?.title && (
                <p className="text-xs font-medium text-gray-700 dark:text-gray-200">
                    {fallback.title}
                </p>
            )}
            {fallback?.body && (
                <p className="line-clamp-2 text-[11px] text-gray-500 dark:text-gray-400">
                    {fallback.body}
                </p>
            )}

            <p className="text-[11px] text-gray-400 dark:text-gray-500">
                {explanation}
            </p>

            <div className="mt-1 flex w-full flex-col items-center gap-1.5">
                {fallback?.instagram_url && (
                    <FallbackLink href={fallback.instagram_url}>
                        View the Instagram post
                    </FallbackLink>
                )}
                {fallback?.ads_library_url && (
                    <FallbackLink
                        href={fallback.ads_library_url}
                        // Meta's Library IDs aren't the ad ids we hold, so this
                        // lands on the page's ads — pre-filtered by this ad's
                        // copy — rather than on this exact one.
                        note="This page’s ads, matched on the copy above"
                    >
                        Find it in the Ads Library
                    </FallbackLink>
                )}
                {fallback?.ads_manager_url && (
                    <FallbackLink href={fallback.ads_manager_url}>
                        Open in Ads Manager
                    </FallbackLink>
                )}
            </div>
        </div>
    );
}

function FallbackLink({
    href,
    note,
    children,
}: {
    href: string;
    note?: string;
    children: React.ReactNode;
}) {
    return (
        <span className="flex flex-col items-center gap-0.5">
            <a
                href={href}
                target="_blank"
                rel="noreferrer"
                className="inline-flex items-center gap-1 text-[11px] text-blue-600 hover:underline dark:text-blue-400"
            >
                {children}
                <ExternalLink className="h-3 w-3" />
            </a>
            {note && (
                <span className="text-[10px] text-gray-400 dark:text-gray-500">
                    {note}
                </span>
            )}
        </span>
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
