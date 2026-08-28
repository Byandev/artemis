import HeadingSmall from '@/components/heading-small';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { type BreadcrumbItem } from '@/types';
import { Workspace } from '@/types/models/Workspace';
import { Transition } from '@headlessui/react';
import { Head, useForm } from '@inertiajs/react';
import { BellRing, CalendarClock, Clock, ListChecks, Tags } from 'lucide-react';
import { type FormEventHandler } from 'react';

// Whole-hour options (00:00 – 23:00); the scheduler checks hourly, so a send
// time on any other minute would never match.
const HOUR_OPTIONS = Array.from({ length: 24 }, (_, h) => {
    const value = `${String(h).padStart(2, '0')}:00`;
    return { value, label: value };
});

interface Props {
    workspace: Workspace;
    settings: {
        enable_edit_previous_day: boolean;
        enable_bulk_status_update: boolean;
        enable_auto_tag_status: boolean;
        discord_daily_stats_enabled: boolean;
        discord_webhook_url: string | null;
        discord_send_at: string;
    };
    /** Whether this user holds "Manage RMO Notifications" on top of the page's own permission. */
    canManageNotifications: boolean;
}

export default function RmoSettings({
    workspace,
    settings,
    canManageNotifications,
}: Props) {
    const baseUrl = `/workspaces/${workspace.slug}/settings/rmo`;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'RMO management', href: baseUrl },
    ];

    const { data, setData, put, processing, errors, recentlySuccessful } =
        useForm({
            enable_edit_previous_day: settings.enable_edit_previous_day,
            enable_bulk_status_update: settings.enable_bulk_status_update,
            enable_auto_tag_status: settings.enable_auto_tag_status,
            discord_daily_stats_enabled: settings.discord_daily_stats_enabled,
            discord_webhook_url: settings.discord_webhook_url ?? '',
            discord_send_at: settings.discord_send_at,
        });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(baseUrl, { preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="RMO management settings" />

            <SettingsLayout workspace={workspace}>
                <div className="space-y-6">
                    <HeadingSmall
                        title="RMO Management"
                        description="Control how far back the RMO management page stays editable, what can be changed in bulk, and which statuses tag themselves."
                    />

                    <form onSubmit={submit} className="space-y-8">
                        <div className="space-y-3">
                            <div className="rounded-[12px] border border-black/8 bg-white p-4 dark:border-white/8 dark:bg-zinc-900">
                                <div className="flex items-start justify-between gap-4">
                                    <div className="flex items-start gap-3">
                                        <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-[10px] bg-stone-100 dark:bg-zinc-800">
                                            <CalendarClock className="h-4 w-4 text-gray-500 dark:text-gray-400" />
                                        </div>
                                        <div>
                                            <p className="text-[13px] font-medium text-gray-900 dark:text-gray-100">
                                                Edit previous days
                                            </p>
                                            <p className="mt-0.5 text-[12px] text-gray-500 dark:text-gray-400">
                                                Allow assigning and updating
                                                status on <strong>every</strong>{' '}
                                                past delivery date, not just
                                                yesterday. When off, only
                                                today&apos;s orders and
                                                yesterday&apos;s
                                                delivered/returned parcels can
                                                be changed.
                                            </p>
                                        </div>
                                    </div>
                                    <Switch
                                        checked={data.enable_edit_previous_day}
                                        onCheckedChange={(v) =>
                                            setData(
                                                'enable_edit_previous_day',
                                                v,
                                            )
                                        }
                                        aria-label="Enable editing previous days"
                                    />
                                </div>
                            </div>

                            <div className="rounded-[12px] border border-black/8 bg-white p-4 dark:border-white/8 dark:bg-zinc-900">
                                <div className="flex items-start justify-between gap-4">
                                    <div className="flex items-start gap-3">
                                        <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-[10px] bg-stone-100 dark:bg-zinc-800">
                                            <ListChecks className="h-4 w-4 text-gray-500 dark:text-gray-400" />
                                        </div>
                                        <div>
                                            <p className="text-[13px] font-medium text-gray-900 dark:text-gray-100">
                                                Bulk status update
                                            </p>
                                            <p className="mt-0.5 text-[12px] text-gray-500 dark:text-gray-400">
                                                Show a &quot;Set status&quot;
                                                action on the RMO management
                                                page so several selected orders
                                                can be re-statused at once. The
                                                same date rules still apply —
                                                orders outside the editable
                                                window are skipped.
                                            </p>
                                        </div>
                                    </div>
                                    <Switch
                                        checked={data.enable_bulk_status_update}
                                        onCheckedChange={(v) =>
                                            setData(
                                                'enable_bulk_status_update',
                                                v,
                                            )
                                        }
                                        aria-label="Enable bulk status update"
                                    />
                                </div>
                            </div>

                            <div className="rounded-[12px] border border-black/8 bg-white p-4 dark:border-white/8 dark:bg-zinc-900">
                                <div className="flex items-start justify-between gap-4">
                                    <div className="flex items-start gap-3">
                                        <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-[10px] bg-stone-100 dark:bg-zinc-800">
                                            <Tags className="h-4 w-4 text-gray-500 dark:text-gray-400" />
                                        </div>
                                        <div>
                                            <p className="text-[13px] font-medium text-gray-900 dark:text-gray-100">
                                                Auto-tag status
                                            </p>
                                            <p className="mt-0.5 text-[12px] text-gray-500 dark:text-gray-400">
                                                Let the RMO status follow the
                                                courier, so no CSR has to close
                                                a finished row out by hand. A
                                                parcel reporting{' '}
                                                <strong>Delivered</strong>{' '}
                                                re-tags its RMO status to{' '}
                                                <strong>DELIVERED</strong>, and{' '}
                                                <strong>Returning</strong> to{' '}
                                                <strong>RETURNING</strong>.
                                                Every other parcel status stays
                                                under CSR control.
                                            </p>
                                        </div>
                                    </div>
                                    <Switch
                                        checked={data.enable_auto_tag_status}
                                        onCheckedChange={(v) =>
                                            setData('enable_auto_tag_status', v)
                                        }
                                        aria-label="Enable auto-tag status"
                                    />
                                </div>

                                {data.enable_auto_tag_status && (
                                    <p className="mt-3 border-t border-black/6 pt-3 text-[11px] text-gray-400 dark:border-white/6 dark:text-gray-500">
                                        Auto-tagging wins over manual edits — it
                                        re-applies every night at midnight,
                                        covering the day that just ended. Saving
                                        re-tags today&apos;s orders straight
                                        away, so you can check it without
                                        waiting for the nightly run.
                                    </p>
                                )}
                            </div>
                        </div>

                        {canManageNotifications && (
                            <div className="space-y-3">
                                <p className="font-mono text-[10px] tracking-wider text-gray-400 uppercase dark:text-gray-500">
                                    Discord
                                </p>

                                <div className="rounded-[12px] border border-black/8 bg-white p-4 dark:border-white/8 dark:bg-zinc-900">
                                    <div className="flex items-start justify-between gap-4">
                                        <div className="flex items-start gap-3">
                                            <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-[10px] bg-stone-100 dark:bg-zinc-800">
                                                <BellRing className="h-4 w-4 text-gray-500 dark:text-gray-400" />
                                            </div>
                                            <div>
                                                <p className="text-[13px] font-medium text-gray-900 dark:text-gray-100">
                                                    Daily RMO report
                                                </p>
                                                <p className="mt-0.5 text-[12px] text-gray-500 dark:text-gray-400">
                                                    Post the day&apos;s RMO
                                                    numbers to Discord once a
                                                    day: for delivery, called,
                                                    delivered, returning and
                                                    problematic, plus call logs
                                                    synced, total and average
                                                    call duration, connected
                                                    calls and hit rate. Covers
                                                    the whole workspace across
                                                    all users.
                                                </p>
                                            </div>
                                        </div>
                                        <Switch
                                            checked={
                                                data.discord_daily_stats_enabled
                                            }
                                            onCheckedChange={(v) =>
                                                setData(
                                                    'discord_daily_stats_enabled',
                                                    v,
                                                )
                                            }
                                            aria-label="Enable daily RMO Discord report"
                                        />
                                    </div>

                                    <div className="mt-3 space-y-3 border-t border-black/6 pt-3 dark:border-white/6">
                                        <div className="grid gap-1.5">
                                            <Label
                                                htmlFor="discord_webhook_url"
                                                className="text-[12px] text-gray-500 dark:text-gray-400"
                                            >
                                                Discord webhook URL
                                            </Label>
                                            <Input
                                                id="discord_webhook_url"
                                                type="url"
                                                value={data.discord_webhook_url}
                                                onChange={(e) =>
                                                    setData(
                                                        'discord_webhook_url',
                                                        e.target.value,
                                                    )
                                                }
                                                disabled={
                                                    !data.discord_daily_stats_enabled
                                                }
                                                placeholder="https://discord.com/api/webhooks/…"
                                                autoComplete="off"
                                                className="h-8"
                                            />
                                            {!data.discord_webhook_url && (
                                                <p className="text-[11px] text-gray-400 dark:text-gray-500">
                                                    No webhook set — this report
                                                    will not be sent until you
                                                    add one.
                                                </p>
                                            )}
                                            <InputError
                                                message={
                                                    errors.discord_webhook_url
                                                }
                                            />
                                        </div>

                                        <div className="flex items-center gap-2">
                                            <Clock className="h-3.5 w-3.5 text-gray-400 dark:text-gray-500" />
                                            <Label
                                                htmlFor="discord_send_at"
                                                className="text-[12px] text-gray-500 dark:text-gray-400"
                                            >
                                                Send daily at
                                            </Label>
                                            <select
                                                id="discord_send_at"
                                                value={data.discord_send_at}
                                                onChange={(e) =>
                                                    setData(
                                                        'discord_send_at',
                                                        e.target.value,
                                                    )
                                                }
                                                disabled={
                                                    !data.discord_daily_stats_enabled
                                                }
                                                className="h-8 w-24 rounded-md border border-black/8 bg-white px-2 text-[12px] text-gray-800 outline-none disabled:opacity-50 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-100"
                                            >
                                                {HOUR_OPTIONS.map((o) => (
                                                    <option
                                                        key={o.value}
                                                        value={o.value}
                                                    >
                                                        {o.label}
                                                    </option>
                                                ))}
                                            </select>
                                            <InputError
                                                message={errors.discord_send_at}
                                            />
                                        </div>

                                        <p className="text-[11px] text-gray-400 dark:text-gray-500">
                                            Server time, on the hour. The report
                                            covers that same day up to the
                                            moment it is sent.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        )}

                        <div className="flex items-center gap-4">
                            <Button disabled={processing}>Save</Button>

                            <Transition
                                show={recentlySuccessful}
                                enter="transition ease-in-out"
                                enterFrom="opacity-0"
                                leave="transition ease-in-out"
                                leaveTo="opacity-0"
                            >
                                <p className="text-sm text-neutral-600 dark:text-neutral-400">
                                    Saved
                                </p>
                            </Transition>
                        </div>
                    </form>
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
