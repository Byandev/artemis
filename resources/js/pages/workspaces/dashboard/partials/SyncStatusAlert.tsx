import { CheckCircle2, Sparkles } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

interface Props {
    workspaceSlug: string;
}

export default function SyncStatusAlert({ workspaceSlug }: Props) {
    const [syncing, setSyncing] = useState<boolean | null>(null);
    const [justCompleted, setJustCompleted] = useState(false);
    const pollRef = useRef<ReturnType<typeof setInterval> | null>(null);

    useEffect(() => {
        let cancelled = false;

        const check = async () => {
            try {
                const res = await fetch(`/workspaces/${workspaceSlug}/onboarding/status`, {
                    headers: { Accept: 'application/json' },
                });
                const json = await res.json();
                if (cancelled) return;

                if (json.complete) {
                    setSyncing((prev) => {
                        if (prev) setJustCompleted(true);
                        return false;
                    });
                    if (pollRef.current) {
                        clearInterval(pollRef.current);
                        pollRef.current = null;
                    }
                } else if (json.syncing) {
                    setSyncing(true);
                }
            } catch {}
        };

        check();
        pollRef.current = setInterval(check, 5000);

        return () => {
            cancelled = true;
            if (pollRef.current) {
                clearInterval(pollRef.current);
                pollRef.current = null;
            }
        };
    }, [workspaceSlug]);

    useEffect(() => {
        if (!justCompleted) return;
        const timer = setTimeout(() => setJustCompleted(false), 6000);
        return () => clearTimeout(timer);
    }, [justCompleted]);

    if (justCompleted) {
        return (
            <div className="group relative mb-5 overflow-hidden rounded-2xl border border-emerald-200/70 bg-gradient-to-r from-emerald-50 via-white to-emerald-50/60 p-[1px] shadow-[0_1px_2px_rgba(16,185,129,0.08),0_8px_24px_-12px_rgba(16,185,129,0.25)] dark:border-emerald-400/20 dark:from-emerald-500/10 dark:via-zinc-900 dark:to-emerald-500/5 dark:shadow-[0_1px_2px_rgba(0,0,0,0.4),0_8px_24px_-12px_rgba(16,185,129,0.35)]">
                <div className="flex items-center gap-4 rounded-[15px] bg-white/60 px-5 py-4 backdrop-blur-sm dark:bg-zinc-950/60">
                    <div className="relative flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-emerald-400 to-emerald-600 shadow-[inset_0_1px_0_rgba(255,255,255,0.25),0_4px_12px_-2px_rgba(16,185,129,0.5)]">
                        <CheckCircle2 className="h-5 w-5 text-white" strokeWidth={2.5} />
                    </div>
                    <div className="flex-1">
                        <p className="font-mono text-[10px] font-semibold uppercase tracking-[0.12em] text-emerald-600/80 dark:text-emerald-400/80">
                            Status
                        </p>
                        <p className="mt-0.5 text-[14px] font-semibold text-gray-900 dark:text-gray-50">
                            Sync complete · your data is ready
                        </p>
                    </div>
                </div>
            </div>
        );
    }

    if (!syncing) return null;

    return (
        <div className="group relative mb-5 overflow-hidden rounded-2xl border border-emerald-200/70 bg-gradient-to-r from-emerald-50 via-white to-emerald-50/60 p-[1px] shadow-[0_1px_2px_rgba(16,185,129,0.08),0_8px_24px_-12px_rgba(16,185,129,0.25)] dark:border-emerald-400/20 dark:from-emerald-500/10 dark:via-zinc-900 dark:to-emerald-500/5 dark:shadow-[0_1px_2px_rgba(0,0,0,0.4),0_8px_24px_-12px_rgba(16,185,129,0.35)]">
            {/* Animated shimmer */}
            <div className="pointer-events-none absolute inset-0 -translate-x-full animate-[shimmer_2.5s_ease-in-out_infinite] bg-gradient-to-r from-transparent via-emerald-300/20 to-transparent dark:via-emerald-400/10" />

            <div className="relative flex items-center gap-4 rounded-[15px] bg-white/60 px-5 py-4 backdrop-blur-sm dark:bg-zinc-950/60">
                {/* Icon orb */}
                <div className="relative flex h-10 w-10 shrink-0 items-center justify-center">
                    <span className="absolute inset-0 animate-ping rounded-xl bg-emerald-400/30" />
                    <span className="absolute inset-0 rounded-xl bg-gradient-to-br from-emerald-400 to-emerald-600 shadow-[inset_0_1px_0_rgba(255,255,255,0.25),0_4px_12px_-2px_rgba(16,185,129,0.5)]" />
                    <Sparkles className="relative h-4.5 w-4.5 text-white" strokeWidth={2.25} />
                </div>

                {/* Content */}
                <div className="flex-1 min-w-0">
                    <div className="flex items-center gap-2">
                        <p className="font-mono text-[10px] font-semibold uppercase tracking-[0.12em] text-emerald-600/80 dark:text-emerald-400/80">
                            Syncing
                        </p>
                        <span className="flex items-center gap-0.5">
                            <span className="h-1 w-1 animate-bounce rounded-full bg-emerald-500 [animation-delay:-0.3s]" />
                            <span className="h-1 w-1 animate-bounce rounded-full bg-emerald-500 [animation-delay:-0.15s]" />
                            <span className="h-1 w-1 animate-bounce rounded-full bg-emerald-500" />
                        </span>
                    </div>
                    <p className="mt-0.5 text-[14px] font-semibold text-gray-900 dark:text-gray-50">
                        Fetching your orders and customers
                    </p>
                    <p className="mt-0.5 text-[12px] text-gray-500 dark:text-gray-400">
                        This runs in the background — your dashboard will update automatically as data arrives.
                    </p>
                </div>

                {/* Animated progress line at the bottom */}
                <div className="pointer-events-none absolute inset-x-0 bottom-0 h-[2px] overflow-hidden rounded-b-[15px]">
                    <div className="h-full w-1/3 animate-[slide_2s_ease-in-out_infinite] bg-gradient-to-r from-transparent via-emerald-500 to-transparent" />
                </div>
            </div>

            <style>{`
                @keyframes shimmer {
                    0%   { transform: translateX(-100%); }
                    100% { transform: translateX(100%); }
                }
                @keyframes slide {
                    0%   { transform: translateX(-100%); }
                    100% { transform: translateX(400%); }
                }
            `}</style>
        </div>
    );
}
