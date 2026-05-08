import { CheckCircle2, Loader2, XCircle } from 'lucide-react';
import { useState } from 'react';

type Status = 'idle' | 'loading' | 'valid' | 'invalid';

type Props = {
    url: string;
    payload: Record<string, string>;
    disabledReason?: string;
};

function csrfFromCookie(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

export default function ValidateTokenButton({
    url,
    payload,
    disabledReason,
}: Props) {
    const [status, setStatus] = useState<Status>('idle');
    const [message, setMessage] = useState<string>('');

    async function run() {
        setStatus('loading');
        setMessage('');
        try {
            const res = await fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-XSRF-TOKEN': csrfFromCookie(),
                },
                body: JSON.stringify(payload),
            });
            const data = (await res.json()) as {
                valid?: boolean;
                message?: string;
            };
            setStatus(data.valid ? 'valid' : 'invalid');
            setMessage(data.message ?? (data.valid ? 'Valid' : 'Invalid'));
        } catch {
            setStatus('invalid');
            setMessage('Network error. Try again.');
        }
    }

    const disabled = Boolean(disabledReason) || status === 'loading';

    return (
        <div className="flex items-center gap-2">
            <button
                type="button"
                onClick={run}
                disabled={disabled}
                title={disabledReason}
                className="flex h-8 shrink-0 items-center gap-1.5 rounded-md border border-black/8 bg-stone-100 px-3 font-mono! text-[11px]! font-medium text-gray-600 transition-all hover:bg-stone-200 disabled:cursor-not-allowed disabled:opacity-50 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-300 dark:hover:bg-zinc-700"
            >
                {status === 'loading' && (
                    <Loader2 className="h-3.5 w-3.5 animate-spin" />
                )}
                {status === 'valid' && (
                    <CheckCircle2 className="h-3.5 w-3.5 text-emerald-500" />
                )}
                {status === 'invalid' && (
                    <XCircle className="h-3.5 w-3.5 text-red-500" />
                )}
                {status === 'loading' ? 'Validating…' : 'Validate'}
            </button>
            {message && (
                <span
                    className={`font-mono text-[11px] ${
                        status === 'valid'
                            ? 'text-emerald-500'
                            : status === 'invalid'
                              ? 'text-red-500'
                              : 'text-gray-400'
                    }`}
                >
                    {message}
                </span>
            )}
        </div>
    );
}
