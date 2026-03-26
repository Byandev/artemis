import PageHeader from '@/components/common/PageHeader';
import AppLayout from '@/layouts/app-layout';
import { Head, router, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { User } from '@/types';
import { Page } from '@/types/models/Page';
import { Workspace } from '@/types/models/Workspace';
import workspaces from '@/routes/workspaces';

interface Props {
    workspace: Workspace;
    page: Page;
    users: User[];
}

const inputClass = 'h-10 w-full rounded-[10px] border border-black/8 bg-stone-50 px-3 font-mono! text-[13px]! text-gray-800 placeholder:text-gray-300 outline-none transition-all focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-100 dark:placeholder:text-gray-600 dark:focus:border-emerald-400';
const labelClass = 'block font-mono text-[10px] font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500';
const fieldClass = 'space-y-1.5';
const errorClass = 'font-mono text-[11px] text-red-500';

export default function Edit({ workspace, page, users }: Props) {
    const { data, setData, put, processing, errors } = useForm({
        shop_id: page.shop_id?.toString() ?? '',
        name: page.name ?? '',
        pos_token: page.pos_token ?? '',
        botcake_token: page.botcake_token ?? '',
        infotxt_token: page.infotxt_token ?? '',
        infotxt_user_id: page.infotxt_user_id ?? '',
        pancake_token: page.pancake_token ?? '',
        parcel_journey_flow_id: page.parcel_journey_flow_id?.toString() ?? '',
        parcel_journey_custom_field_id: page.parcel_journey_custom_field_id?.toString() ?? '',
        parcel_journey_enabled: Boolean(page.parcel_journey_enabled),
        owner_id: page.owner_id?.toString() ?? '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        put(workspaces.pages.update.url({ workspace, page }));
    };

    return (
        <AppLayout>
            <Head title={`Edit — ${page.name}`} />
            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
                <div>
                    <PageHeader
                        title="Edit Page"
                        description={`ID: ${page.id} · ${page.name}`}
                    >
                        <button
                            onClick={() => router.get(workspaces.pages.index.url({ workspace }))}
                            className="flex items-center gap-1.5 font-mono! text-[12px]! text-gray-400 transition-colors hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300"
                        >
                            <ArrowLeft className="h-3.5 w-3.5" />
                            Back to Pages
                        </button>
                    </PageHeader>

                    <form onSubmit={handleSubmit} className="space-y-5">
                        {/* Basic Info */}
                        <div className="rounded-2xl border border-black/6 bg-white p-6 dark:border-white/6 dark:bg-zinc-900">
                            <p className="mb-5 font-mono text-[10px] font-semibold uppercase tracking-widest text-gray-300 dark:text-gray-600">
                                Basic Info
                            </p>
                            <div className="grid gap-5 sm:grid-cols-2">
                                <div className={fieldClass}>
                                    <label className={labelClass}>Shop ID <span className="text-red-400">*</span></label>
                                    <input type="number" className={inputClass} placeholder="e.g. 789" value={data.shop_id} onChange={(e) => setData('shop_id', e.target.value)} />
                                    {errors.shop_id && <p className={errorClass}>{errors.shop_id}</p>}
                                </div>
                                <div className={fieldClass}>
                                    <label className={labelClass}>Page Name <span className="text-red-400">*</span></label>
                                    <input type="text" className={inputClass} placeholder="e.g. My Store Page" value={data.name} onChange={(e) => setData('name', e.target.value)} />
                                    {errors.name && <p className={errorClass}>{errors.name}</p>}
                                </div>
                                <div className={`${fieldClass} sm:col-span-2`}>
                                    <label className={labelClass}>Owner</label>
                                    <select
                                        value={data.owner_id}
                                        onChange={(e) => setData('owner_id', e.target.value)}
                                        className={inputClass}
                                    >
                                        <option value="">Select owner…</option>
                                        {users.map((u) => (
                                            <option key={u.id} value={u.id}>{u.name}</option>
                                        ))}
                                    </select>
                                    {errors.owner_id && <p className={errorClass}>{errors.owner_id}</p>}
                                </div>
                            </div>
                        </div>

                        {/* Tokens */}
                        <div className="rounded-2xl border border-black/6 bg-white p-6 dark:border-white/6 dark:bg-zinc-900">
                            <p className="mb-5 font-mono text-[10px] font-semibold uppercase tracking-widest text-gray-300 dark:text-gray-600">
                                Integration Tokens
                            </p>
                            <div className="grid gap-5 sm:grid-cols-2">
                                <div className={`${fieldClass} sm:col-span-2`}>
                                    <label className={labelClass}>POS Token</label>
                                    <input type="text" className={inputClass} placeholder="Enter POS token" value={data.pos_token} onChange={(e) => setData('pos_token', e.target.value)} />
                                    {errors.pos_token && <p className={errorClass}>{errors.pos_token}</p>}
                                </div>
                                <div className={`${fieldClass} sm:col-span-2`}>
                                    <label className={labelClass}>Pancake Token</label>
                                    <input type="text" className={inputClass} placeholder="Enter Pancake token" value={data.pancake_token} onChange={(e) => setData('pancake_token', e.target.value)} />
                                    {errors.pancake_token && <p className={errorClass}>{errors.pancake_token}</p>}
                                </div>
                                <div className={`${fieldClass} sm:col-span-2`}>
                                    <label className={labelClass}>Botcake Token</label>
                                    <input type="text" className={inputClass} placeholder="Enter Botcake token" value={data.botcake_token} onChange={(e) => setData('botcake_token', e.target.value)} />
                                    {errors.botcake_token && <p className={errorClass}>{errors.botcake_token}</p>}
                                </div>
                                <div className={fieldClass}>
                                    <label className={labelClass}>Infotxt Token</label>
                                    <input type="text" className={inputClass} placeholder="Enter Infotxt token" value={data.infotxt_token} onChange={(e) => setData('infotxt_token', e.target.value)} />
                                    {errors.infotxt_token && <p className={errorClass}>{errors.infotxt_token}</p>}
                                </div>
                                <div className={fieldClass}>
                                    <label className={labelClass}>Infotxt User ID</label>
                                    <input type="text" className={inputClass} placeholder="Enter Infotxt user ID" value={data.infotxt_user_id} onChange={(e) => setData('infotxt_user_id', e.target.value)} />
                                    {errors.infotxt_user_id && <p className={errorClass}>{errors.infotxt_user_id}</p>}
                                </div>
                            </div>
                        </div>

                        {/* Parcel Journey */}
                        <div className="rounded-2xl border border-black/6 bg-white p-6 dark:border-white/6 dark:bg-zinc-900">
                            <p className="mb-5 font-mono text-[10px] font-semibold uppercase tracking-widest text-gray-300 dark:text-gray-600">
                                Parcel Journey
                            </p>
                            <div className="grid gap-5 sm:grid-cols-2">
                                <div className={fieldClass}>
                                    <label className={labelClass}>Flow ID</label>
                                    <input type="text" className={inputClass} placeholder="Enter flow ID" value={data.parcel_journey_flow_id} onChange={(e) => setData('parcel_journey_flow_id', e.target.value)} />
                                    {errors.parcel_journey_flow_id && <p className={errorClass}>{errors.parcel_journey_flow_id}</p>}
                                </div>
                                <div className={fieldClass}>
                                    <label className={labelClass}>Custom Field ID</label>
                                    <input type="text" className={inputClass} placeholder="Enter custom field ID" value={data.parcel_journey_custom_field_id} onChange={(e) => setData('parcel_journey_custom_field_id', e.target.value)} />
                                    {errors.parcel_journey_custom_field_id && <p className={errorClass}>{errors.parcel_journey_custom_field_id}</p>}
                                </div>
                                <div className={`${fieldClass} sm:col-span-2`}>
                                    <div className="flex items-center justify-between rounded-[10px] border border-black/8 bg-stone-50 px-4 py-3 dark:border-white/8 dark:bg-zinc-800">
                                        <div>
                                            <p className={labelClass}>Enable Parcel Journey</p>
                                            <p className="mt-0.5 font-mono text-[11px] text-gray-400 dark:text-gray-500">Send parcel status updates via flow</p>
                                        </div>
                                        <button
                                            type="button"
                                            onClick={() => setData('parcel_journey_enabled', !data.parcel_journey_enabled)}
                                            className={`relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ${data.parcel_journey_enabled ? 'bg-emerald-500' : 'bg-gray-200 dark:bg-zinc-600'}`}
                                        >
                                            <span className={`pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow-sm ring-0 transition duration-200 ${data.parcel_journey_enabled ? 'translate-x-5' : 'translate-x-0'}`} />
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {/* Footer */}
                        <div className="flex items-center justify-end gap-2">
                            <button
                                type="button"
                                onClick={() => router.get(workspaces.pages.index.url({ workspace }))}
                                className="flex h-9 items-center rounded-lg border border-black/8 bg-stone-100 px-4 font-mono! text-[12px]! font-medium text-gray-600 transition-all hover:bg-stone-200 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-300 dark:hover:bg-zinc-700"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                disabled={processing}
                                className="flex h-9 items-center rounded-lg bg-emerald-600 px-4 font-mono! text-[12px]! font-medium text-white transition-all hover:bg-emerald-700 disabled:opacity-50"
                            >
                                {processing ? 'Saving…' : 'Save Changes'}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </AppLayout>
    );
}
