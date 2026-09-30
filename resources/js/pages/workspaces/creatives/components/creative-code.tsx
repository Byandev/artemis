import { useClipboard } from '@/hooks/use-clipboard';
import { Check, Copy } from 'lucide-react';
import { useEffect, useState } from 'react';

/** The creative's short unique code as a click-to-copy chip. */
export function CreativeCode({ code }: { code: string }) {
    const [, copy] = useClipboard();
    const [copied, setCopied] = useState(false);

    useEffect(() => {
        if (!copied) return;
        const t = setTimeout(() => setCopied(false), 1500);
        return () => clearTimeout(t);
    }, [copied]);

    return (
        <button
            type="button"
            title={copied ? 'Copied' : 'Copy code'}
            onClick={async (e) => {
                // Rows open the detail sheet on click — keep copying separate.
                e.stopPropagation();
                if (await copy(code)) setCopied(true);
            }}
            className="group inline-flex items-center gap-1 rounded bg-stone-100 px-1.5 py-0.5 font-mono text-[11px] font-medium tracking-wide text-gray-600 transition-colors hover:bg-stone-200 dark:bg-zinc-800 dark:text-gray-400 dark:hover:bg-zinc-700"
        >
            {code}
            {copied ? (
                <Check className="h-3 w-3 text-emerald-500" />
            ) : (
                <Copy className="h-3 w-3 opacity-0 transition-opacity group-hover:opacity-100" />
            )}
        </button>
    );
}
