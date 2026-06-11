import { Clapperboard, FileImage } from 'lucide-react';
import {
    ADS_BADGE,
    ADS_DOT,
    ADS_STATUS_LABELS,
    AdsStatus,
    FINAL_DOT,
    FINAL_STATUS_BADGE,
    FINAL_STATUS_LABELS,
    FinalStatus,
    REVIEW_BADGE,
    REVIEW_DOT,
    REVIEW_STATUS_LABELS,
    ReviewStatus,
} from '../types';

const badgeBase =
    'inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 font-mono text-[11px] font-medium';

function StatusDot({ className }: { className: string }) {
    return (
        <span className={`h-1.5 w-1.5 shrink-0 rounded-full ${className}`} />
    );
}

export function AdsBadge({ status }: { status: AdsStatus }) {
    return (
        <span className={`${badgeBase} ${ADS_BADGE[status]}`}>
            <StatusDot className={ADS_DOT[status]} />
            {ADS_STATUS_LABELS[status]}
        </span>
    );
}

export function FinalBadge({ status }: { status: FinalStatus }) {
    return (
        <span className={`${badgeBase} ${FINAL_STATUS_BADGE[status]}`}>
            <StatusDot className={FINAL_DOT[status]} />
            {FINAL_STATUS_LABELS[status]}
        </span>
    );
}

export function ReviewBadge({ status }: { status: ReviewStatus }) {
    return (
        <span className={`${badgeBase} ${REVIEW_BADGE[status]}`}>
            <StatusDot className={REVIEW_DOT[status]} />
            {REVIEW_STATUS_LABELS[status]}
        </span>
    );
}

export function FormatBadge({ format }: { format: 'video' | 'image' }) {
    return (
        <span className="inline-flex items-center gap-1 font-mono text-[11px] text-gray-500 dark:text-gray-400">
            {format === 'video' ? (
                <Clapperboard className="h-3 w-3" />
            ) : (
                <FileImage className="h-3 w-3" />
            )}
            <span className="capitalize">{format}</span>
        </span>
    );
}

export function InitialAvatar({
    name,
    className = '',
}: {
    name: string;
    className?: string;
}) {
    return (
        <span
            className={`flex h-7 w-7 shrink-0 items-center justify-center rounded-full font-mono text-[11px] font-bold ${className}`}
        >
            {name.charAt(0).toUpperCase()}
        </span>
    );
}
