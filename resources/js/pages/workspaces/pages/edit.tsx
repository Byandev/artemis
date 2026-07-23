import PageHeader from '@/components/common/PageHeader';
import ValidateTokenButton from '@/components/pages/ValidateTokenButton';
import AppLayout from '@/layouts/app-layout';
import workspaces from '@/routes/workspaces';
import { User } from '@/types';
import { Page } from '@/types/models/Page';
import { Workspace } from '@/types/models/Workspace';
import { Head, router, useForm } from '@inertiajs/react';
import { ArrowLeft, BookOpen, ExternalLink } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';

function csrfFromCookie(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

async function checkValid(
    url: string,
    payload: Record<string, string>,
): Promise<{ valid: boolean; message: string }> {
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
        return { valid: Boolean(data.valid), message: data.message ?? '' };
    } catch {
        return { valid: false, message: 'Network error. Try again.' };
    }
}

type WorkspaceSim = {
    id: number;
    phone_number: string;
    label: string | null;
    carrier: string | null;
};

interface Props {
    workspace: Workspace;
    page: Page;
    users: User[];
    sims: WorkspaceSim[];
}

const inputClass =
    'h-10 w-full rounded-[10px] border border-black/8 bg-stone-50 px-3 font-mono! text-[13px]! text-gray-800 placeholder:text-gray-300 outline-none transition-all focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-100 dark:placeholder:text-gray-600 dark:focus:border-emerald-400';
const labelClass =
    'block font-mono text-[10px] font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500';
const fieldClass = 'space-y-1.5';
const errorClass = 'font-mono text-[11px] text-red-500';

export default function Edit({ workspace, page, users, sims }: Props) {
    const { data, setData, put, processing, errors } = useForm({
        name: page.name ?? '',
        botcake_token: page.botcake_token ?? '',
        sms_provider: page.sms_provider ?? 'infotxt',
        infotxt_token: page.infotxt_token ?? '',
        infotxt_user_id: page.infotxt_user_id ?? '',
        sendgate_api_key: page.sendgate_api_key ?? '',
        sendgate_sim_id: page.sendgate_sim_id ?? '',
        sim_gateway_sim_id: page.sim_gateway_sim_id?.toString() ?? '',
        pancake_token: page.pancake_token ?? '',
        parcel_journey_custom_field_id:
            page.parcel_journey_custom_field_id?.toString() ?? '',
        parcel_journey_flow_id: page.parcel_journey_flow_id?.toString() ?? '',
        parcel_journey_enabled: Boolean(page.parcel_journey_enabled),
        owner_id: page.owner_id?.toString() ?? '',
        status: page.status ?? 'active',
    });

    const [validating, setValidating] = useState(false);

    const save = () => {
        put(workspaces.pages.update.url({ workspace, page }), {
            onError: () => {
                toast.error('Failed to update page. Please check the form.');
            },
        });
    };

    // Validate the parcel-journey flow ID before saving so a bad reference is
    // caught here rather than only on the server round-trip. The token being
    // typed is sent along so a token and flow ID entered together validate as a
    // pair — otherwise the check would run against the stored token and reject
    // a valid flow. Custom field IDs have no local source to check against.
    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();

        if (data.parcel_journey_enabled && data.parcel_journey_flow_id) {
            setValidating(true);
            try {
                const result = await checkValid(
                    `/workspaces/${workspace.slug}/pages/validate-flow-id`,
                    {
                        page_id: String(page.id),
                        flow_id: data.parcel_journey_flow_id,
                        token: data.botcake_token,
                    },
                );
                if (!result.valid) {
                    toast.error(
                        `Flow ID: ${result.message || 'Invalid flow ID.'}`,
                    );
                    return;
                }
            } finally {
                setValidating(false);
            }
        }

        save();
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
                            onClick={() =>
                                router.get(
                                    workspaces.pages.index.url({ workspace }),
                                )
                            }
                            className="flex items-center gap-1.5 font-mono! text-[12px]! text-gray-400 transition-colors hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300"
                        >
                            <ArrowLeft className="h-3.5 w-3.5" />
                            Back to Pages
                        </button>
                    </PageHeader>

                    <a
                        href="https://drive.google.com/file/d/1C4s1GJbiUndoi8I5UVBkP_K2M5dVrHdY/view?usp=sharing"
                        target="_blank"
                        rel="noopener noreferrer"
                        className="mb-5 inline-flex items-center gap-2 rounded-[10px] border border-emerald-500/20 bg-emerald-500/5 px-3 py-2 font-mono! text-[12px]! font-medium text-emerald-700 transition-colors hover:bg-emerald-500/10 dark:border-emerald-500/15 dark:bg-emerald-500/10 dark:text-emerald-300"
                    >
                        <BookOpen className="h-3.5 w-3.5" />
                        Watch the setup tutorial
                        <ExternalLink className="h-3 w-3" />
                    </a>

                    <form onSubmit={handleSubmit} className="space-y-5">
                        {/* Basic Info */}
                        <div className="rounded-2xl border border-black/6 bg-white p-6 dark:border-white/6 dark:bg-zinc-900">
                            <p className="mb-5 font-mono text-[10px] font-semibold tracking-widest text-gray-300 uppercase dark:text-gray-600">
                                Basic Info
                            </p>
                            <div className="grid gap-5 sm:grid-cols-2">
                                <div className={fieldClass}>
                                    <label className={labelClass}>
                                        Page Name{' '}
                                        <span className="text-red-400">*</span>
                                    </label>
                                    <input
                                        type="text"
                                        className={inputClass}
                                        placeholder="e.g. My Store Page"
                                        value={data.name}
                                        onChange={(e) =>
                                            setData('name', e.target.value)
                                        }
                                    />
                                    {errors.name && (
                                        <p className={errorClass}>
                                            {errors.name}
                                        </p>
                                    )}
                                </div>
                                <div className={`${fieldClass}`}>
                                    <label className={labelClass}>Owner</label>
                                    <select
                                        value={data.owner_id}
                                        onChange={(e) =>
                                            setData('owner_id', e.target.value)
                                        }
                                        className={inputClass}
                                    >
                                        <option value="">Select owner…</option>
                                        {users.map((u) => (
                                            <option key={u.id} value={u.id}>
                                                {u.name}
                                            </option>
                                        ))}
                                    </select>
                                    {errors.owner_id && (
                                        <p className={errorClass}>
                                            {errors.owner_id}
                                        </p>
                                    )}
                                </div>
                                <div className={`${fieldClass} sm:col-span-2`}>
                                    <div className="flex items-center justify-between rounded-[10px] border border-black/8 bg-stone-50 px-4 py-3 dark:border-white/8 dark:bg-zinc-800">
                                        <div>
                                            <p className={labelClass}>Status</p>
                                            <p className="mt-0.5 font-mono text-[11px] text-gray-400 dark:text-gray-500">
                                                {data.status === 'active'
                                                    ? 'Page is active and visible'
                                                    : 'Page is inactive and hidden'}
                                            </p>
                                        </div>
                                        <button
                                            type="button"
                                            onClick={() =>
                                                setData(
                                                    'status',
                                                    data.status === 'active'
                                                        ? 'inactive'
                                                        : 'active',
                                                )
                                            }
                                            className={`relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ${data.status === 'active' ? 'bg-emerald-500' : 'bg-gray-200 dark:bg-zinc-600'}`}
                                        >
                                            <span
                                                className={`pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow-sm ring-0 transition duration-200 ${data.status === 'active' ? 'translate-x-5' : 'translate-x-0'}`}
                                            />
                                        </button>
                                    </div>
                                    {errors.status && (
                                        <p className={errorClass}>
                                            {errors.status}
                                        </p>
                                    )}
                                </div>
                            </div>
                        </div>

                        {/* Tokens */}
                        <div className="rounded-2xl border border-black/6 bg-white p-6 dark:border-white/6 dark:bg-zinc-900">
                            <p className="mb-5 font-mono text-[10px] font-semibold tracking-widest text-gray-300 uppercase dark:text-gray-600">
                                Integration Tokens
                            </p>
                            <div className="grid gap-5 sm:grid-cols-2">
                                <div className={`${fieldClass} sm:col-span-2`}>
                                    <label className={labelClass}>
                                        Botcake Token
                                    </label>
                                    <input
                                        type="text"
                                        className={inputClass}
                                        placeholder="Enter Botcake token"
                                        value={data.botcake_token}
                                        onChange={(e) =>
                                            setData(
                                                'botcake_token',
                                                e.target.value,
                                            )
                                        }
                                    />
                                    <ValidateTokenButton
                                        url={`/workspaces/${workspace.slug}/pages/validate-botcake-token`}
                                        payload={{
                                            page_id: String(page.id),
                                            token: data.botcake_token,
                                        }}
                                        disabledReason={
                                            !data.botcake_token
                                                ? 'Enter a token first'
                                                : undefined
                                        }
                                    />
                                    {errors.botcake_token && (
                                        <p className={errorClass}>
                                            {errors.botcake_token}
                                        </p>
                                    )}
                                </div>
                            </div>
                        </div>

                        {/* Parcel Journey */}
                        <div className="rounded-2xl border border-black/6 bg-white p-6 dark:border-white/6 dark:bg-zinc-900">
                            <p className="mb-5 font-mono text-[10px] font-semibold tracking-widest text-gray-300 uppercase dark:text-gray-600">
                                Parcel Journey
                            </p>
                            <div className="space-y-5">
                                <div className="flex items-center justify-between rounded-[10px] border border-black/8 bg-stone-50 px-4 py-3 dark:border-white/8 dark:bg-zinc-800">
                                    <div>
                                        <p className={labelClass}>
                                            Enable Parcel Journey
                                        </p>
                                        <p className="mt-0.5 font-mono text-[11px] text-gray-400 dark:text-gray-500">
                                            Send parcel status updates via SMS
                                            and chat
                                        </p>
                                    </div>
                                    <button
                                        type="button"
                                        onClick={() =>
                                            setData(
                                                'parcel_journey_enabled',
                                                !data.parcel_journey_enabled,
                                            )
                                        }
                                        className={`relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ${data.parcel_journey_enabled ? 'bg-emerald-500' : 'bg-gray-200 dark:bg-zinc-600'}`}
                                    >
                                        <span
                                            className={`pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow-sm ring-0 transition duration-200 ${data.parcel_journey_enabled ? 'translate-x-5' : 'translate-x-0'}`}
                                        />
                                    </button>
                                </div>

                                {data.parcel_journey_enabled && (
                                    <>
                                        <div className="rounded-[10px] border border-black/8 bg-stone-50 p-4 dark:border-white/8 dark:bg-zinc-800">
                                            <p className="mb-4 font-mono text-[10px] font-semibold tracking-widest text-gray-400 uppercase dark:text-gray-500">
                                                Chat
                                            </p>
                                            <div className="grid gap-5 sm:grid-cols-2">
                                                <div className={fieldClass}>
                                                    <label
                                                        className={labelClass}
                                                    >
                                                        Custom Field ID
                                                    </label>
                                                    <input
                                                        type="text"
                                                        className={inputClass}
                                                        placeholder="Enter custom field ID"
                                                        value={
                                                            data.parcel_journey_custom_field_id
                                                        }
                                                        onChange={(e) =>
                                                            setData(
                                                                'parcel_journey_custom_field_id',
                                                                e.target.value,
                                                            )
                                                        }
                                                    />
                                                    <ValidateTokenButton
                                                        url={`/workspaces/${workspace.slug}/pages/validate-custom-field-id`}
                                                        payload={{
                                                            page_id: String(
                                                                page.id,
                                                            ),
                                                            custom_field_id:
                                                                data.parcel_journey_custom_field_id,
                                                            token: data.botcake_token,
                                                        }}
                                                        disabledReason={
                                                            !data.parcel_journey_custom_field_id
                                                                ? 'Enter a custom field ID first'
                                                                : !data.botcake_token
                                                                  ? 'Enter a Botcake token first'
                                                                  : undefined
                                                        }
                                                    />
                                                    {errors.parcel_journey_custom_field_id && (
                                                        <p
                                                            className={
                                                                errorClass
                                                            }
                                                        >
                                                            {
                                                                errors.parcel_journey_custom_field_id
                                                            }
                                                        </p>
                                                    )}
                                                </div>
                                                <div className={fieldClass}>
                                                    <label
                                                        className={labelClass}
                                                    >
                                                        Flow ID
                                                    </label>
                                                    <input
                                                        type="text"
                                                        className={inputClass}
                                                        placeholder="Enter flow ID"
                                                        value={
                                                            data.parcel_journey_flow_id
                                                        }
                                                        onChange={(e) =>
                                                            setData(
                                                                'parcel_journey_flow_id',
                                                                e.target.value,
                                                            )
                                                        }
                                                    />
                                                    <ValidateTokenButton
                                                        url={`/workspaces/${workspace.slug}/pages/validate-flow-id`}
                                                        payload={{
                                                            page_id: String(
                                                                page.id,
                                                            ),
                                                            flow_id:
                                                                data.parcel_journey_flow_id,
                                                            token: data.botcake_token,
                                                        }}
                                                        disabledReason={
                                                            !data.parcel_journey_flow_id
                                                                ? 'Enter a flow ID first'
                                                                : !data.botcake_token
                                                                  ? 'Enter a Botcake token first'
                                                                  : undefined
                                                        }
                                                    />
                                                    {errors.parcel_journey_flow_id && (
                                                        <p
                                                            className={
                                                                errorClass
                                                            }
                                                        >
                                                            {
                                                                errors.parcel_journey_flow_id
                                                            }
                                                        </p>
                                                    )}
                                                </div>
                                            </div>
                                        </div>

                                        <div className="rounded-[10px] border border-black/8 bg-stone-50 p-4 dark:border-white/8 dark:bg-zinc-800">
                                            <p className="mb-4 font-mono text-[10px] font-semibold tracking-widest text-gray-400 uppercase dark:text-gray-500">
                                                SMS
                                            </p>
                                            <div className="space-y-5">
                                                <div className={fieldClass}>
                                                    <label
                                                        className={labelClass}
                                                    >
                                                        Provider
                                                    </label>
                                                    <select
                                                        value={
                                                            data.sms_provider
                                                        }
                                                        onChange={(e) =>
                                                            setData(
                                                                'sms_provider',
                                                                e.target
                                                                    .value as
                                                                    | 'infotxt'
                                                                    | 'sendgate'
                                                                    | 'sim_gateway',
                                                            )
                                                        }
                                                        className={inputClass}
                                                    >
                                                        <option value="sim_gateway">
                                                            Artemis - Sim
                                                            Gateway
                                                        </option>
                                                        <option value="infotxt">
                                                            InfoTxt
                                                        </option>
                                                    </select>
                                                    {errors.sms_provider && (
                                                        <p
                                                            className={
                                                                errorClass
                                                            }
                                                        >
                                                            {
                                                                errors.sms_provider
                                                            }
                                                        </p>
                                                    )}
                                                </div>

                                                {data.sms_provider ===
                                                    'sim_gateway' && (
                                                    <div className={fieldClass}>
                                                        <label
                                                            className={
                                                                labelClass
                                                            }
                                                        >
                                                            SIM Card
                                                        </label>
                                                        {sims.length === 0 ? (
                                                            <p className="font-mono text-[11px] text-gray-400">
                                                                No active SIMs
                                                                in this
                                                                workspace. Ask
                                                                an admin to
                                                                provision one.
                                                            </p>
                                                        ) : (
                                                            <select
                                                                value={
                                                                    data.sim_gateway_sim_id
                                                                }
                                                                onChange={(e) =>
                                                                    setData(
                                                                        'sim_gateway_sim_id',
                                                                        e.target
                                                                            .value,
                                                                    )
                                                                }
                                                                className={
                                                                    inputClass
                                                                }
                                                            >
                                                                <option value="">
                                                                    Select a
                                                                    SIM…
                                                                </option>
                                                                {sims.map(
                                                                    (s) => (
                                                                        <option
                                                                            key={
                                                                                s.id
                                                                            }
                                                                            value={s.id.toString()}
                                                                        >
                                                                            {
                                                                                s.phone_number
                                                                            }
                                                                            {s.carrier
                                                                                ? ` · ${s.carrier.toUpperCase()}`
                                                                                : ''}
                                                                            {s.label
                                                                                ? ` · ${s.label}`
                                                                                : ''}
                                                                        </option>
                                                                    ),
                                                                )}
                                                            </select>
                                                        )}
                                                        {errors.sim_gateway_sim_id && (
                                                            <p
                                                                className={
                                                                    errorClass
                                                                }
                                                            >
                                                                {
                                                                    errors.sim_gateway_sim_id
                                                                }
                                                            </p>
                                                        )}
                                                    </div>
                                                )}

                                                {data.sms_provider ===
                                                    'infotxt' && (
                                                    <div className="grid gap-5 sm:grid-cols-2">
                                                        <div
                                                            className={
                                                                fieldClass
                                                            }
                                                        >
                                                            <label
                                                                className={
                                                                    labelClass
                                                                }
                                                            >
                                                                Infotxt Token
                                                            </label>
                                                            <input
                                                                type="text"
                                                                className={
                                                                    inputClass
                                                                }
                                                                placeholder="Enter Infotxt token"
                                                                value={
                                                                    data.infotxt_token
                                                                }
                                                                onChange={(e) =>
                                                                    setData(
                                                                        'infotxt_token',
                                                                        e.target
                                                                            .value,
                                                                    )
                                                                }
                                                            />
                                                            {errors.infotxt_token && (
                                                                <p
                                                                    className={
                                                                        errorClass
                                                                    }
                                                                >
                                                                    {
                                                                        errors.infotxt_token
                                                                    }
                                                                </p>
                                                            )}
                                                        </div>
                                                        <div
                                                            className={
                                                                fieldClass
                                                            }
                                                        >
                                                            <label
                                                                className={
                                                                    labelClass
                                                                }
                                                            >
                                                                Infotxt User ID
                                                            </label>
                                                            <input
                                                                type="text"
                                                                className={
                                                                    inputClass
                                                                }
                                                                placeholder="Enter Infotxt user ID"
                                                                value={
                                                                    data.infotxt_user_id
                                                                }
                                                                onChange={(e) =>
                                                                    setData(
                                                                        'infotxt_user_id',
                                                                        e.target
                                                                            .value,
                                                                    )
                                                                }
                                                            />
                                                            {errors.infotxt_user_id && (
                                                                <p
                                                                    className={
                                                                        errorClass
                                                                    }
                                                                >
                                                                    {
                                                                        errors.infotxt_user_id
                                                                    }
                                                                </p>
                                                            )}
                                                        </div>
                                                    </div>
                                                )}

                                                {data.sms_provider ===
                                                    'sendgate' && (
                                                    <div className="grid gap-5 sm:grid-cols-2">
                                                        <div
                                                            className={
                                                                fieldClass
                                                            }
                                                        >
                                                            <label
                                                                className={
                                                                    labelClass
                                                                }
                                                            >
                                                                SendGate API Key
                                                            </label>
                                                            <input
                                                                type="text"
                                                                className={
                                                                    inputClass
                                                                }
                                                                placeholder="Enter SendGate API key"
                                                                value={
                                                                    data.sendgate_api_key
                                                                }
                                                                onChange={(e) =>
                                                                    setData(
                                                                        'sendgate_api_key',
                                                                        e.target
                                                                            .value,
                                                                    )
                                                                }
                                                            />
                                                            {errors.sendgate_api_key && (
                                                                <p
                                                                    className={
                                                                        errorClass
                                                                    }
                                                                >
                                                                    {
                                                                        errors.sendgate_api_key
                                                                    }
                                                                </p>
                                                            )}
                                                        </div>
                                                        <div
                                                            className={
                                                                fieldClass
                                                            }
                                                        >
                                                            <label
                                                                className={
                                                                    labelClass
                                                                }
                                                            >
                                                                SendGate SIM ID
                                                            </label>
                                                            <input
                                                                type="text"
                                                                className={
                                                                    inputClass
                                                                }
                                                                placeholder="Enter SendGate SIM ID"
                                                                value={
                                                                    data.sendgate_sim_id
                                                                }
                                                                onChange={(e) =>
                                                                    setData(
                                                                        'sendgate_sim_id',
                                                                        e.target
                                                                            .value,
                                                                    )
                                                                }
                                                            />
                                                            {errors.sendgate_sim_id && (
                                                                <p
                                                                    className={
                                                                        errorClass
                                                                    }
                                                                >
                                                                    {
                                                                        errors.sendgate_sim_id
                                                                    }
                                                                </p>
                                                            )}
                                                        </div>
                                                    </div>
                                                )}
                                            </div>
                                        </div>
                                    </>
                                )}
                            </div>
                        </div>

                        {/* Footer */}
                        <div className="flex items-center justify-end gap-2">
                            <button
                                type="button"
                                onClick={() =>
                                    router.get(
                                        workspaces.pages.index.url({
                                            workspace,
                                        }),
                                    )
                                }
                                className="flex h-9 items-center rounded-lg border border-black/8 bg-stone-100 px-4 font-mono! text-[12px]! font-medium text-gray-600 transition-all hover:bg-stone-200 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-300 dark:hover:bg-zinc-700"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                disabled={processing || validating}
                                className="flex h-9 items-center rounded-lg bg-emerald-600 px-4 font-mono! text-[12px]! font-medium text-white transition-all hover:bg-emerald-700 disabled:opacity-50"
                            >
                                {validating
                                    ? 'Validating…'
                                    : processing
                                      ? 'Saving…'
                                      : 'Save Changes'}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </AppLayout>
    );
}
