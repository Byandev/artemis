import { Check, Copy } from 'lucide-react';
import { useState } from 'react';

/**
 * What to paste into Pancake POS → Settings → Advanced → Webhook/API for a
 * shop with auto-fill on: the URL, and the secret to send as a request header.
 * The secret only exists once auto-fill has been saved on.
 */
export default function WebhookDetails({
    shopId,
    secret,
}: {
    shopId: number;
    secret: string | null;
}) {
    const url = `${window.location.origin}/api/webhooks/pancake/shops/${shopId}/orders`;

    if (!secret) {
        return (
            <p className="text-[12px] text-amber-600 dark:text-amber-400">
                Save to create this shop's webhook secret.
            </p>
        );
    }

    return (
        <div className="space-y-2 rounded-md bg-stone-50 p-3 dark:bg-zinc-800">
            <p className="text-[12px] text-gray-500 dark:text-gray-400">
                In Pancake POS → Settings → Advanced → Webhook/API, set Data to
                Orders and add:
            </p>
            <CopyRow label="Webhook URL" value={url} />
            <CopyRow label="Header key" value="X-Artemis-Secret" />
            <CopyRow label="Header value" value={secret} />
        </div>
    );
}

function CopyRow({ label, value }: { label: string; value: string }) {
    const [copied, setCopied] = useState(false);

    const copy = async () => {
        await navigator.clipboard.writeText(value);
        setCopied(true);
        setTimeout(() => setCopied(false), 1500);
    };

    return (
        <div className="grid grid-cols-[90px_1fr_auto] items-center gap-2">
            <span className="font-mono text-[10px] tracking-wider text-gray-400 uppercase">
                {label}
            </span>
            <code className="truncate font-mono text-[11px] text-gray-800 dark:text-gray-200">
                {value}
            </code>
            <button
                type="button"
                onClick={copy}
                className="text-gray-400 hover:text-gray-700 dark:hover:text-gray-200"
                title={`Copy ${label.toLowerCase()}`}
            >
                {copied ? (
                    <Check className="size-3.5" />
                ) : (
                    <Copy className="size-3.5" />
                )}
            </button>
        </div>
    );
}
