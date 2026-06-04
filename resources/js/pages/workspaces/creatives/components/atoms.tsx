import { Clapperboard, FileImage } from 'lucide-react';
import { ADS_BADGE, ADS_STATUS_LABELS, AdsStatus, FINAL_STATUS_BADGE, FINAL_STATUS_LABELS, FinalStatus, REVIEW_BADGE, REVIEW_STATUS_LABELS, ReviewStatus } from '../types';

export function AdsBadge({ status }: { status: AdsStatus }) {
    return (
        <span className={`inline-flex items-center rounded-full px-2 py-0.5 font-mono text-[11px] font-medium ${ADS_BADGE[status]}`}>
            {ADS_STATUS_LABELS[status]}
        </span>
    );
}

export function FinalBadge({ status }: { status: FinalStatus }) {
    return (
        <span className={`inline-flex items-center rounded-full px-2 py-0.5 font-mono text-[11px] font-medium ${FINAL_STATUS_BADGE[status]}`}>
            {FINAL_STATUS_LABELS[status]}
        </span>
    );
}

export function ReviewBadge({ status }: { status: ReviewStatus }) {
    return (
        <span className={`inline-flex items-center rounded-full px-2 py-0.5 font-mono text-[11px] font-medium ${REVIEW_BADGE[status]}`}>
            {REVIEW_STATUS_LABELS[status]}
        </span>
    );
}

export function FormatBadge({ format }: { format: 'video' | 'image' }) {
    return (
        <span className="inline-flex items-center gap-1 font-mono text-[11px] text-gray-500 dark:text-gray-400">
            {format === 'video' ? <Clapperboard className="h-3 w-3" /> : <FileImage className="h-3 w-3" />}
            <span className="capitalize">{format}</span>
        </span>
    );
}

export function InitialAvatar({ name, className = '' }: { name: string; className?: string }) {
    return (
        <span className={`flex h-7 w-7 shrink-0 items-center justify-center rounded-full font-mono text-[11px] font-bold ${className}`}>
            {name.charAt(0).toUpperCase()}
        </span>
    );
}
