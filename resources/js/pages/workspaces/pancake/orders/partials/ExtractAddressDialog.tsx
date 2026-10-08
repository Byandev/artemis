import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import axios from 'axios';
import { Check, Copy, Loader2, RefreshCw } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';

type MatchStatus = 'complete' | 'partial' | 'not_found';

/** One location level: what the customer typed, and Pancake's id for it. */
interface Level {
    typed: string;
    id: string | null;
    name: string | null;
}

export interface AddressExtraction {
    found: boolean;
    status: MatchStatus;
    confidence: number;
    address_text: string;
    province: Level;
    district: Level;
    commune: Level;
    /** Street / purok / house number, plus the landmark when given. */
    address: string;
    formatted_address: string;
    messages_read: number;
}

const STATUS_STYLES: Record<MatchStatus, string> = {
    complete:
        'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400',
    partial:
        'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400',
    not_found: 'bg-gray-100 text-gray-600 dark:bg-white/5 dark:text-gray-400',
};

const STATUS_LABELS: Record<MatchStatus, string> = {
    complete: 'Fully matched',
    partial: 'Partly matched',
    not_found: 'No match',
};

/**
 * "Get address" on a Pancake order: reads the order's Messenger conversation,
 * pulls out the address the customer typed and matches it to Pancake's
 * province / district / commune ids. Read-only — nothing is saved yet.
 */
export default function ExtractAddressDialog({
    url,
    orderLabel,
    open,
    onOpenChange,
}: {
    url: string;
    orderLabel: string;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [result, setResult] = useState<AddressExtraction | null>(null);
    const [copied, setCopied] = useState(false);

    const run = useCallback(async () => {
        setLoading(true);
        setError(null);
        setResult(null);

        try {
            const response = await axios.post<AddressExtraction>(url);
            setResult(response.data);
        } catch (e) {
            const message = axios.isAxiosError(e)
                ? (e.response?.data?.message ??
                  (e.response?.status === 429
                      ? 'Too many tries in a row — wait a minute.'
                      : null))
                : null;
            setError(message ?? 'Could not read the address. Try again.');
        } finally {
            setLoading(false);
        }
    }, [url]);

    useEffect(() => {
        if (open) run();
    }, [open, run]);

    const copy = async () => {
        if (!result?.formatted_address) return;
        await navigator.clipboard.writeText(result.formatted_address);
        setCopied(true);
        setTimeout(() => setCopied(false), 1500);
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Address from conversation</DialogTitle>
                    <DialogDescription>
                        Order {orderLabel} — read from the customer's Messenger
                        chat. Nothing is changed in Pancake.
                    </DialogDescription>
                </DialogHeader>

                {loading && (
                    <div className="flex items-center gap-2 py-8 text-sm text-gray-500">
                        <Loader2 className="size-4 animate-spin" />
                        Reading the conversation…
                    </div>
                )}

                {error && !loading && (
                    <p className="rounded-md bg-red-50 px-3 py-2 text-sm text-red-700 dark:bg-red-500/10 dark:text-red-400">
                        {error}
                    </p>
                )}

                {result && !loading && (
                    <div className="space-y-4 text-sm">
                        {!result.found ? (
                            <p className="text-gray-600 dark:text-gray-400">
                                No delivery address found in the last{' '}
                                {result.messages_read} messages.
                            </p>
                        ) : (
                            <>
                                <Section label="What the customer typed">
                                    <p className="whitespace-pre-wrap text-gray-800 dark:text-gray-200">
                                        {result.address_text || '—'}
                                    </p>
                                </Section>

                                <div className="flex items-center gap-2">
                                    <span
                                        className={`rounded px-2 py-0.5 text-[11px] font-medium ${STATUS_STYLES[result.status]}`}
                                    >
                                        {STATUS_LABELS[result.status]}
                                    </span>
                                    <span className="text-[11px] text-gray-400">
                                        AI confidence{' '}
                                        {Math.round(result.confidence * 100)}%
                                    </span>
                                </div>

                                <div className="overflow-hidden rounded-md border border-black/6 dark:border-white/10">
                                    <LevelRow
                                        label="Province"
                                        level={result.province}
                                    />
                                    <LevelRow
                                        label="District"
                                        level={result.district}
                                    />
                                    <LevelRow
                                        label="Commune"
                                        level={result.commune}
                                    />
                                    <div className="grid grid-cols-[110px_1fr] gap-2 px-3 py-2">
                                        <span className="text-[12px] text-gray-500">
                                            Address
                                        </span>
                                        <span className="text-[12px] text-gray-800 dark:text-gray-200">
                                            {result.address || '—'}
                                        </span>
                                    </div>
                                </div>

                                {result.formatted_address && (
                                    <Section label="Formatted address">
                                        <div className="flex items-start justify-between gap-2">
                                            <p className="font-medium text-gray-900 dark:text-gray-100">
                                                {result.formatted_address}
                                            </p>
                                            <button
                                                type="button"
                                                onClick={copy}
                                                className="shrink-0 text-gray-400 hover:text-gray-700 dark:hover:text-gray-200"
                                                title="Copy"
                                            >
                                                {copied ? (
                                                    <Check className="size-4" />
                                                ) : (
                                                    <Copy className="size-4" />
                                                )}
                                            </button>
                                        </div>
                                    </Section>
                                )}
                            </>
                        )}
                    </div>
                )}

                <DialogFooter>
                    <Button variant="outline" onClick={run} disabled={loading}>
                        <RefreshCw className="size-4" />
                        Read again
                    </Button>
                    <Button onClick={() => onOpenChange(false)}>Close</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function Section({
    label,
    children,
}: {
    label: string;
    children: React.ReactNode;
}) {
    return (
        <div>
            <div className="mb-1 font-mono text-[10px] tracking-wider text-gray-400 uppercase">
                {label}
            </div>
            {children}
        </div>
    );
}

/** Pancake's matched name and id, with what the customer typed underneath. */
function LevelRow({ label, level }: { label: string; level: Level }) {
    return (
        <div className="grid grid-cols-[110px_1fr] gap-2 border-b border-black/6 px-3 py-2 dark:border-white/10">
            <span className="text-[12px] text-gray-500">{label}</span>
            <div className="min-w-0 text-[12px]">
                {level.id ? (
                    <div className="text-gray-900 dark:text-gray-100">
                        {level.name}{' '}
                        <span className="font-mono text-[11px] text-emerald-700 dark:text-emerald-400">
                            {level.id}
                        </span>
                    </div>
                ) : (
                    <div className="text-amber-600 dark:text-amber-400">
                        Not matched
                    </div>
                )}
                {level.typed && (
                    <div className="text-[11px] text-gray-400">
                        typed: {level.typed}
                    </div>
                )}
            </div>
        </div>
    );
}
