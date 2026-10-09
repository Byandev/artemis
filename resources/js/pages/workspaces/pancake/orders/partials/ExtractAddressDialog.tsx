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
import { Check, Copy, Loader2, RefreshCw, Upload } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import { toast } from 'sonner';

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
    /** Levels the names alone could not match, picked from the real list by the AI. */
    picked_by_ai: Array<'province' | 'district' | 'commune'>;
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
 * province / district / commune ids. With `pushUrl` (the user may update
 * order addresses) the result can then be written to the order in Pancake.
 */
export default function ExtractAddressDialog({
    url,
    pushUrl,
    orderLabel,
    open,
    onOpenChange,
    onPushed,
}: {
    url: string;
    /** Omitted when the user may not update order addresses. */
    pushUrl?: string;
    orderLabel: string;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onPushed?: () => void;
}) {
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [result, setResult] = useState<AddressExtraction | null>(null);
    const [copied, setCopied] = useState(false);
    // The address line as it will be sent — prefilled from the chat, editable.
    const [addressLine, setAddressLine] = useState('');
    const [confirming, setConfirming] = useState(false);
    const [pushing, setPushing] = useState(false);
    const [pushed, setPushed] = useState(false);

    const run = useCallback(async () => {
        setLoading(true);
        setError(null);
        setResult(null);
        setConfirming(false);
        setPushed(false);

        try {
            const response = await axios.post<AddressExtraction>(url);
            setResult(response.data);
            setAddressLine(response.data.address);
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

    // The commune fixes its district and province, so it is all that has to match.
    const canPush =
        !!pushUrl &&
        !!result?.found &&
        !!result.commune.id &&
        addressLine.trim() !== '' &&
        !pushed;

    const push = async () => {
        if (!canPush || !result?.commune.id) return;
        setPushing(true);
        setError(null);

        try {
            await axios.post(pushUrl, {
                commune_id: result.commune.id,
                address: addressLine.trim(),
            });
            setPushed(true);
            setConfirming(false);
            toast.success(`Order ${orderLabel} updated in Pancake.`);
            onPushed?.();
        } catch (e) {
            const message = axios.isAxiosError(e)
                ? (e.response?.data?.message ??
                  (e.response?.status === 429
                      ? 'Too many tries in a row — wait a minute.'
                      : null))
                : null;
            setError(message ?? 'Could not update the order in Pancake.');
        } finally {
            setPushing(false);
        }
    };

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
                        chat.{' '}
                        {pushUrl
                            ? 'Nothing changes in Pancake until you press Update.'
                            : 'Nothing is changed in Pancake.'}
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
                                        picked={result.picked_by_ai?.includes(
                                            'province',
                                        )}
                                    />
                                    <LevelRow
                                        label="District"
                                        level={result.district}
                                        picked={result.picked_by_ai?.includes(
                                            'district',
                                        )}
                                    />
                                    <LevelRow
                                        label="Commune"
                                        level={result.commune}
                                        picked={result.picked_by_ai?.includes(
                                            'commune',
                                        )}
                                    />
                                    <div className="grid grid-cols-[110px_1fr] gap-2 px-3 py-2">
                                        <span className="text-[12px] text-gray-500">
                                            Address
                                        </span>
                                        {pushUrl && !pushed ? (
                                            <input
                                                type="text"
                                                value={addressLine}
                                                onChange={(e) =>
                                                    setAddressLine(
                                                        e.target.value,
                                                    )
                                                }
                                                placeholder="Street, purok or landmark"
                                                className="h-8 w-full rounded-md border border-black/10 bg-white px-2 text-[12px] text-gray-800 outline-none focus:border-emerald-500 dark:border-white/10 dark:bg-zinc-900 dark:text-gray-200"
                                            />
                                        ) : (
                                            <span className="text-[12px] text-gray-800 dark:text-gray-200">
                                                {addressLine || '—'}
                                            </span>
                                        )}
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

                {pushed && (
                    <p className="rounded-md bg-emerald-50 px-3 py-2 text-sm text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400">
                        Updated in Pancake.
                    </p>
                )}

                {confirming && canPush && result && (
                    <p className="rounded-md bg-amber-50 px-3 py-2 text-[12px] text-amber-800 dark:bg-amber-500/10 dark:text-amber-300">
                        This replaces the order's address in Pancake with{' '}
                        <strong>
                            {[
                                addressLine.trim(),
                                result.commune.name,
                                result.district.name,
                                result.province.name,
                            ]
                                .filter(Boolean)
                                .join(', ')}
                        </strong>
                        . Name and phone are not sent.
                    </p>
                )}

                <DialogFooter>
                    <Button
                        variant="outline"
                        onClick={run}
                        disabled={loading || pushing}
                    >
                        <RefreshCw className="size-4" />
                        Read again
                    </Button>
                    {canPush && !loading && (
                        <Button
                            onClick={() =>
                                confirming ? push() : setConfirming(true)
                            }
                            disabled={pushing}
                            className="bg-emerald-600 text-white hover:bg-emerald-700"
                        >
                            {pushing ? (
                                <Loader2 className="size-4 animate-spin" />
                            ) : (
                                <Upload className="size-4" />
                            )}
                            {confirming
                                ? 'Confirm update'
                                : 'Update in Pancake'}
                        </Button>
                    )}
                    <Button
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                    >
                        Close
                    </Button>
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
function LevelRow({
    label,
    level,
    picked,
}: {
    label: string;
    level: Level;
    picked?: boolean;
}) {
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
                        {picked && (
                            <span
                                className="ml-1.5 rounded bg-violet-50 px-1.5 py-px text-[10px] text-violet-700 dark:bg-violet-500/10 dark:text-violet-300"
                                title="The typed name matched nothing exactly, so the AI picked this from the real list. Worth a look."
                            >
                                picked by AI
                            </span>
                        )}
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
