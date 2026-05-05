import AuthLayout from '@/layouts/auth-layout';
import { Head, router, useForm } from '@inertiajs/react';
import { Loader2 } from 'lucide-react';
import { FormEventHandler } from 'react';

interface Props {
    workspace: { id: number; name: string; slug: string };
}

const inputCls =
    'h-10 w-full rounded-[10px] border border-black/8 bg-stone-50 px-3 font-mono! text-[13px]! text-gray-800 placeholder:text-gray-300 outline-none transition-all focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-100 dark:placeholder:text-gray-600 dark:focus:border-emerald-400';

export default function Onboarding({ workspace }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        page_id: '',
        shop_id: '',
        page_name: '',
        pos_token: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(`/workspaces/${workspace.slug}/onboarding`);
    };

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
