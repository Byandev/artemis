import { router, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

export default function SyncingDataModal() {
    const { syncingData } = usePage().props as {
        syncingData?: { workspaceSlug: string } | null;
    };
    const [dismissed, setDismissed] = useState(false);
    const [dots, setDots] = useState('');
    const pollRef = useRef<ReturnType<typeof setInterval> | null>(null);

    useEffect(() => {
        if (!syncingData) setDismissed(false);
    }, [syncingData]);

    useEffect(() => {
        if (!syncingData || dismissed) return;
        const interval = setInterval(() => {
            setDots((d) => (d.length >= 3 ? '' : d + '.'));
        }, 500);
        return () => clearInterval(interval);
    }, [syncingData, dismissed]);

    useEffect(() => {
        if (!syncingData || dismissed) return;

        const poll = () => {
            fetch(
                `/workspaces/${syncingData.workspaceSlug}/onboarding/status`,
                {
                    headers: { Accept: 'application/json' },
                },
            )
                .then((res) => res.json())
                .then((data) => {
                    if (data.complete) {
                        setDismissed(true);
                        if (pollRef.current) {
                            clearInterval(pollRef.current);
                            pollRef.current = null;
                        }
                        router.reload();
                    }
                })
                .catch(() => {});
        };

        pollRef.current = setInterval(poll, 5000);
        return () => {
            if (pollRef.current) {
                clearInterval(pollRef.current);
                pollRef.current = null;
            }
        };
    }, [syncingData, dismissed]);

    if (!syncingData || dismissed) return null;

    return (
        <div className="fixed inset-0 z-[100] flex items-center justify-center bg-black/60 backdrop-blur-sm">
            <div className="mx-4 w-full max-w-xl rounded-3xl bg-white shadow-2xl dark:bg-zinc-900">
                {/* Spinner area */}
                <div className="flex justify-center pt-14 pb-8">
                    <div className="relative h-24 w-24">
                        <div
                            className="absolute inset-0 animate-spin rounded-full border-4 border-transparent border-t-emerald-500 border-r-emerald-500/30"
                            style={{ animationDuration: '1.2s' }}
                        />
                        <div
                            className="absolute inset-2.5 animate-spin rounded-full border-4 border-transparent border-b-emerald-400 border-l-emerald-400/20"
                            style={{
                                animationDirection: 'reverse',
                                animationDuration: '1.8s',
                            }}
                        />
                        <div className="absolute inset-6 animate-pulse rounded-full bg-emerald-500/15 dark:bg-emerald-500/10" />
                    </div>
                </div>

                {/* Text */}
                <div className="px-12 text-center">
                    <h2 className="text-2xl font-bold text-gray-900 dark:text-gray-100">
                        Preparing your data{dots}
                    </h2>
                    <p className="mx-auto mt-3 max-w-sm text-[15px] leading-relaxed text-gray-500 dark:text-gray-400">
                        We're syncing your orders and customer data. This
                        usually takes a few minutes.
                    </p>
                </div>

                {/* Progress */}
                <div className="mx-12 mt-8">
                    <div className="overflow-hidden rounded-full bg-gray-100 dark:bg-zinc-800">
                        <div
                            className="h-2.5 rounded-full bg-gradient-to-r from-emerald-500 to-teal-400"
                            style={{
                                animation:
                                    'syncProgress 2s ease-in-out infinite',
                            }}
                        />
                    </div>
                </div>

                {/* Footer hint */}
                <div className="px-12 pt-5 pb-12 text-center">
                    <p className="text-sm text-gray-400 dark:text-gray-500">
                        Please be patient.
                        <br />
                        This will disappear automatically once everything is
                        ready.
                    </p>
                </div>
            </div>

            <style>{`
                @keyframes syncProgress {
                    0% { width: 0%; margin-left: 0%; }
                    50% { width: 60%; margin-left: 20%; }
                    100% { width: 0%; margin-left: 100%; }
                }
            `}</style>
        </div>
    );
}
