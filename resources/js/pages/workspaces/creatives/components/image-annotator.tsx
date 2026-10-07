import React, { useCallback, useEffect, useRef, useState } from 'react';
import { Region, ReviewStatus } from '../types';

export interface Mark {
    id: number;
    /** The number shown on the image and on the matching review card. */
    n: number;
    region: Region;
    status: ReviewStatus;
}

/** Below this (as a fraction of the image) a drag counts as a click — a point. */
const POINT_THRESHOLD = 0.01;

const MARK_COLOR: Partial<Record<ReviewStatus, string>> = {
    revision: 'border-amber-400 bg-amber-400/10',
    approved: 'border-emerald-400 bg-emerald-400/10',
};
const BADGE_COLOR: Partial<Record<ReviewStatus, string>> = {
    revision: 'bg-amber-400 text-amber-950',
    approved: 'bg-emerald-400 text-emerald-950',
};

const clamp = (v: number) => Math.min(1, Math.max(0, v));

/** Box from two corners, in either drag direction. */
function boxFrom(a: { x: number; y: number }, b: { x: number; y: number }) {
    return {
        x: Math.min(a.x, b.x),
        y: Math.min(a.y, b.y),
        w: Math.abs(a.x - b.x),
        h: Math.abs(a.y - b.y),
    };
}

/**
 * An image filling its parent (object-contain), with review marks laid over
 * the exact pixels of the picture — not the letterbox around it. When
 * `onDraw` is given, dragging draws a box and clicking drops a point.
 */
export function ImageAnnotator({
    src,
    alt,
    marks,
    draft,
    onDraw,
    highlightId,
    onHover,
    onSelect,
    showMarks,
    onError,
}: {
    src: string;
    /** The image failed to load — e.g. its signed URL expired. */
    onError?: () => void;
    alt: string;
    marks: Mark[];
    /** The area being marked for the review not yet submitted. */
    draft: Region | null;
    onDraw?: (region: Region) => void;
    highlightId: number | null;
    onHover: (id: number | null) => void;
    onSelect: (id: number) => void;
    showMarks: boolean;
}) {
    const containerRef = useRef<HTMLDivElement>(null);
    const imgRef = useRef<HTMLImageElement>(null);
    const [frame, setFrame] = useState<{
        left: number;
        top: number;
        width: number;
        height: number;
    } | null>(null);
    const [dragStart, setDragStart] = useState<{ x: number; y: number } | null>(
        null,
    );
    const [dragBox, setDragBox] = useState<Region | null>(null);

    // Where the picture actually sits inside the container under
    // object-contain, so fractions map onto the image itself.
    const measure = useCallback(() => {
        const container = containerRef.current;
        const img = imgRef.current;
        if (!container || !img?.naturalWidth) return;
        const W = container.clientWidth;
        const H = container.clientHeight;
        const scale = Math.min(W / img.naturalWidth, H / img.naturalHeight);
        const width = img.naturalWidth * scale;
        const height = img.naturalHeight * scale;
        setFrame({
            left: (W - width) / 2,
            top: (H - height) / 2,
            width,
            height,
        });
    }, []);

    useEffect(() => {
        const container = containerRef.current;
        if (!container) return;
        const observer = new ResizeObserver(measure);
        observer.observe(container);
        return () => observer.disconnect();
    }, [measure]);

    const toFraction = (e: React.PointerEvent) => {
        const rect = e.currentTarget.getBoundingClientRect();
        return {
            x: clamp((e.clientX - rect.left) / rect.width),
            y: clamp((e.clientY - rect.top) / rect.height),
        };
    };

    const onPointerDown = (e: React.PointerEvent<HTMLDivElement>) => {
        if (!onDraw || e.button !== 0) return;
        e.currentTarget.setPointerCapture(e.pointerId);
        const p = toFraction(e);
        setDragStart(p);
        setDragBox({ ...p, w: 0, h: 0 });
    };

    const onPointerMove = (e: React.PointerEvent<HTMLDivElement>) => {
        if (!dragStart) return;
        setDragBox(boxFrom(dragStart, toFraction(e)));
    };

    const onPointerUp = (e: React.PointerEvent<HTMLDivElement>) => {
        if (!dragStart || !onDraw) return;
        const box = boxFrom(dragStart, toFraction(e));
        setDragStart(null);
        setDragBox(null);
        onDraw(
            box.w < POINT_THRESHOLD && box.h < POINT_THRESHOLD
                ? { x: dragStart.x, y: dragStart.y, w: 0, h: 0 }
                : box,
        );
    };

    const shownDraft = dragBox ?? draft;

    return (
        <div ref={containerRef} className="absolute inset-0">
            <img
                ref={imgRef}
                src={src}
                alt={alt}
                onLoad={measure}
                onError={onError}
                draggable={false}
                className="absolute inset-0 h-full w-full object-contain select-none"
            />

            {frame && (
                <div
                    onPointerDown={onPointerDown}
                    onPointerMove={onPointerMove}
                    onPointerUp={onPointerUp}
                    onPointerCancel={() => {
                        setDragStart(null);
                        setDragBox(null);
                    }}
                    style={frame}
                    className={`absolute touch-none ${onDraw ? 'cursor-crosshair' : ''}`}
                >
                    {showMarks &&
                        marks.map((m) => (
                            <MarkShape
                                key={m.id}
                                mark={m}
                                highlighted={highlightId === m.id}
                                onHover={onHover}
                                onSelect={onSelect}
                            />
                        ))}

                    {shownDraft && (
                        <RegionShape
                            region={shownDraft}
                            className="border-violet-400 bg-violet-400/15"
                            badgeClass="bg-violet-500 text-white"
                            label="+"
                            dashed
                        />
                    )}
                </div>
            )}
        </div>
    );
}

function MarkShape({
    mark,
    highlighted,
    onHover,
    onSelect,
}: {
    mark: Mark;
    highlighted: boolean;
    onHover: (id: number | null) => void;
    onSelect: (id: number) => void;
}) {
    return (
        <RegionShape
            region={mark.region}
            label={String(mark.n)}
            className={`${MARK_COLOR[mark.status] ?? 'border-violet-400 bg-violet-400/10'} ${
                highlighted ? 'z-10 ring-2 ring-white' : ''
            }`}
            badgeClass={`${BADGE_COLOR[mark.status] ?? 'bg-violet-400 text-violet-950'} ${
                highlighted ? 'scale-125' : ''
            }`}
            // A mark is clickable even while drawing is on — it must not
            // start a new box underneath.
            onPointerDown={(e) => e.stopPropagation()}
            onClick={() => onSelect(mark.id)}
            onMouseEnter={() => onHover(mark.id)}
            onMouseLeave={() => onHover(null)}
        />
    );
}

/** A box, or — when w and h are 0 — a numbered pin, at a fractional region. */
function RegionShape({
    region,
    label,
    className,
    badgeClass,
    dashed,
    ...handlers
}: {
    region: Region;
    label: string;
    className: string;
    badgeClass: string;
    dashed?: boolean;
} & Pick<
    React.HTMLAttributes<HTMLDivElement>,
    'onPointerDown' | 'onClick' | 'onMouseEnter' | 'onMouseLeave'
>) {
    const isPoint = region.w === 0 && region.h === 0;
    const interactive = !!handlers.onClick;

    if (isPoint) {
        return (
            <div
                {...handlers}
                style={{
                    left: `${region.x * 100}%`,
                    top: `${region.y * 100}%`,
                }}
                className={`absolute flex h-6 w-6 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full font-mono text-[11px] font-bold shadow-lg ring-2 ring-black/40 transition-transform ${badgeClass} ${
                    interactive ? 'cursor-pointer' : 'pointer-events-none'
                }`}
            >
                {label}
            </div>
        );
    }

    return (
        <div
            {...handlers}
            style={{
                left: `${region.x * 100}%`,
                top: `${region.y * 100}%`,
                width: `${region.w * 100}%`,
                height: `${region.h * 100}%`,
            }}
            className={`absolute rounded-[3px] border-2 transition-shadow ${dashed ? 'border-dashed' : ''} ${className} ${
                interactive ? 'cursor-pointer' : 'pointer-events-none'
            }`}
        >
            <span
                className={`absolute -top-3 -left-3 flex h-6 w-6 items-center justify-center rounded-full font-mono text-[11px] font-bold shadow-lg transition-transform ${badgeClass}`}
            >
                {label}
            </span>
        </div>
    );
}
