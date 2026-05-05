import AuthLayout from '@/layouts/auth-layout';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { CheckCircle2, Loader2, RefreshCw } from 'lucide-react';
import { FormEventHandler, useCallback, useEffect, useRef, useState } from 'react';

interface Props {
    workspace: { id: number; name: string; slug: string };
}

const inputCls =
    'h-10 w-full rounded-[10px] border border-black/8 bg-stone-50 px-3 font-mono! text-[13px]! text-gray-800 placeholder:text-gray-300 outline-none transition-all focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-100 dark:placeholder:text-gray-600 dark:focus:border-emerald-400';

export default function Onboarding({ workspace }: Props) {
    const { flash } = usePage().props as { flash?: { success?: string } };
    const [syncing, setSyncing] = useState(false);
    const [complete, setComplete] = useState(false);
    const pollRef = useRef<ReturnType<typeof setInterval> | null>(null);

    const { data, setData, post, processing, errors } = useForm({
        page_id: '',
        shop_id: '',
        page_name: '',
        pos_token: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(`/workspaces/${workspace.slug}/onboarding`, {
            onSuccess: () => {
                setSyncing(true);
            },
        });
    };

    const pollStatus = useCallback(() => {
        fetch(`/workspaces/${workspace.slug}/onboarding/status`, {
            headers: { Accept: 'application/json' },
        })
            .then((res) => res.json())
            .then((data) => {
                if (data.complete) {
                    setComplete(true);
                    setSyncing(false);
                    if (pollRef.current) {
                        clearInterval(pollRef.current);
                        pollRef.current = null;
                    }
                }
            })
            .catch(() => {});
    }, [workspace.slug]);

    useEffect(() => {
        if (syncing && !pollRef.current) {
            pollRef.current = setInterval(pollStatus, 3000);
        }
        return () => {
            if (pollRef.current) {
                clearInterval(pollRef.current);
                pollRef.current = null;
            }
        };
    }, [syncing, pollStatus]);

    useEffect(() => {
        if (complete) {
            const timer = setTimeout(() => {
                router.visit(`/workspaces/${workspace.slug}/dashboard`);
            }, 2000);
            return () => clearTimeout(timer);
        }
    }, [complete, workspace.slug]);

    // Show syncing overlay
    if (syncing || complete) {
        return (
            <AuthLayout title="Syncing your data" description={`Setting up ${workspace.name}`}>
                <Head title="Syncing Data..." />
                <div className="flex flex-col items-center gap-6 py-8">
                    {complete ? (
                        <>
                            <div className="flex h-16 w-16 items-center justify-center rounded-full bg-emerald-100 dark:bg-emerald-500/20">
                                <CheckCircle2 className="h-8 w-8 text-emerald-600 dark:text-emerald-400" />
                            </div>
                            <div className="text-center">
                                <p className="text-[15px] font-semibold text-gray-900 dark:text-gray-100">
                                    Sync complete!
                                </p>
                                <p className="mt-1 font-mono text-[12px] text-gray-500">
                                    Redirecting to your dashboard...
                                </p>
                            </div>
                        </>
                    ) : (
                        <>
                            <div className="flex h-16 w-16 items-center justify-center rounded-full bg-emerald-100 dark:bg-emerald-500/20">
                                <RefreshCw className="h-8 w-8 animate-spin text-emerald-600 dark:text-emerald-400" />
                            </div>
                            <div className="text-center">
                                <p className="text-[15px] font-semibold text-gray-900 dark:text-gray-100">
                                    Fetching your orders...
                                </p>
                                <p className="mt-1 font-mono text-[12px] text-gray-500">
                                    This may take a few minutes. Please don't close this page.
                                </p>
                            </div>
                            <div className="w-full overflow-hidden rounded-full bg-gray-200 dark:bg-zinc-700">
                                <div className="h-1.5 animate-pulse rounded-full bg-emerald-500" style={{ width: '60%' }} />
                            </div>
                        </>
                    )}
                </div>
            </AuthLayout>
        );
    }

    return (
        <AuthLayout title="Connect your page" description="Link your Pancake page to start syncing orders">
            <Head title="Onboarding" />

            <form onSubmit={submit} className="flex flex-col gap-5">
                <div className="space-y-4">
                    {/* Page ID */}
                    <div className="space-y-1.5">
                        <label htmlFor="page_id" className="block font-mono text-[10px] font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500">
                            Page ID <span className="text-red-400">*</span>
                        </label>
                        <input
                            id="page_id"
                            type="number"
                            required
                            autoFocus
                            value={data.page_id}
                            onChange={(e) => setData('page_id', e.target.value)}
                            placeholder="e.g. 123456"
                            className={inputCls}
                        />
                        {errors.page_id && <p className="font-mono text-[11px] text-red-500">{errors.page_id}</p>}
                    </div>

                    {/* Shop ID */}
                    <div className="space-y-1.5">
                        <label htmlFor="shop_id" className="block font-mono text-[10px] font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500">
                            Shop ID <span className="text-red-400">*</span>
                        </label>
                        <input
                            id="shop_id"
                            type="number"
                            required
                            value={data.shop_id}
                            onChange={(e) => setData('shop_id', e.target.value)}
                            placeholder="e.g. 789"
                            className={inputCls}
                        />
                        {errors.shop_id && <p className="font-mono text-[11px] text-red-500">{errors.shop_id}</p>}
                    </div>

                    {/* Page Name */}
                    <div className="space-y-1.5">
                        <label htmlFor="page_name" className="block font-mono text-[10px] font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500">
                            Page Name <span className="text-red-400">*</span>
                        </label>
                        <input
                            id="page_name"
                            type="text"
                            required
                            value={data.page_name}
                            onChange={(e) => setData('page_name', e.target.value)}
                            placeholder="My Store Page"
                            className={inputCls}
                        />
                        {errors.page_name && <p className="font-mono text-[11px] text-red-500">{errors.page_name}</p>}
                    </div>

                    {/* POS Token */}
                    <div className="space-y-1.5">
                        <label htmlFor="pos_token" className="block font-mono text-[10px] font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500">
                            POS Token <span className="text-red-400">*</span>
                        </label>
                        <input
                            id="pos_token"
                            type="text"
                            required
                            value={data.pos_token}
                            onChange={(e) => setData('pos_token', e.target.value)}
                            placeholder="Your Pancake API key"
                            className={inputCls}
                        />
                        {errors.pos_token
                            ? <p className="font-mono text-[11px] text-red-500">{errors.pos_token}</p>
                            : <p className="font-mono text-[10px] text-gray-400 dark:text-gray-500">Find this in your Pancake POS settings.</p>
                        }
                    </div>

                    {/* Submit */}
                    <button
                        type="submit"
                        disabled={processing}
                        className="mt-1 flex h-10 w-full items-center justify-center gap-2 rounded-[10px] bg-emerald-600 font-mono! text-[13px]! font-semibold text-white transition-all hover:bg-emerald-700 disabled:opacity-50"
                    >
                        {processing && <Loader2 className="h-3.5 w-3.5 animate-spin" />}
                        {processing ? 'Connecting...' : 'Connect & Sync Orders'}
                    </button>

                    {/* Skip */}
                    <button
                        type="button"
                        onClick={() => router.post(`/workspaces/${workspace.slug}/onboarding/skip`)}
                        className="flex h-10 w-full items-center justify-center rounded-[10px] border border-black/8 font-mono! text-[12px]! text-gray-500 transition-all hover:bg-stone-50 dark:border-white/8 dark:text-gray-400 dark:hover:bg-zinc-800"
                    >
                        Skip for now
                    </button>
                </div>
            </form>
        </AuthLayout>
    );
}
