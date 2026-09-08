import HeadingSmall from '@/components/heading-small';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import {
    edit as editMetaAdsNotifications,
    update as updateMetaAdsNotifications,
} from '@/routes/meta-ads-notifications';
import { type BreadcrumbItem } from '@/types';
import { Workspace } from '@/types/models/Workspace';
import { Transition } from '@headlessui/react';
import { Head, useForm } from '@inertiajs/react';
import { CircleSlash, Clock } from 'lucide-react';
import { type FormEventHandler } from 'react';

interface MetaAdsNotificationSettings {
    inactive_accounts_enabled: boolean;
    inactive_accounts_webhook_url: string | null;
    inactive_accounts_send_at: string;
}

// Whole-hour options (00:00 – 23:00); send times are hour-granularity only.
const HOUR_OPTIONS = Array.from({ length: 24 }, (_, h) => {
    const value = `${String(h).padStart(2, '0')}:00`;
    return { value, label: value };
});

interface Props {
    workspace: Workspace;
    settings: MetaAdsNotificationSettings;
}

export default function MetaAdsNotificationsSettings({
    workspace,
    settings,
}: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'Meta Ads notifications',
            href: editMetaAdsNotifications({ workspace: workspace.slug }).url,
        },
    ];

    const { data, setData, put, processing, errors, recentlySuccessful } =
        useForm({
            inactive_accounts_enabled: settings.inactive_accounts_enabled,
            inactive_accounts_webhook_url:
                settings.inactive_accounts_webhook_url ?? '',
            inactive_accounts_send_at: settings.inactive_accounts_send_at,
        });

    const enabled = data.inactive_accounts_enabled;

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(updateMetaAdsNotifications({ workspace: workspace.slug }).url, {
            preserveScroll: true,
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Meta Ads notifications" />

            <SettingsLayout workspace={workspace}>
                <div className="space-y-6">
                    <HeadingSmall
                        title="Meta Ads Notifications"
                        description="Send Meta ad-account alerts to a Discord channel on a daily schedule."
                    />

                    <form onSubmit={submit} className="space-y-8">
                        <div className="space-y-3">
                            <p className="font-mono text-[10px] font-medium tracking-wider text-gray-400 uppercase dark:text-gray-500">
                                Notifications
                            </p>

                            <div className="rounded-[12px] border border-black/8 bg-white p-4 dark:border-white/8 dark:bg-zinc-900">
                                <div className="flex items-start justify-between gap-4">
                                    <div className="flex items-start gap-3">
                                        <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-[10px] bg-stone-100 dark:bg-zinc-800">
                                            <CircleSlash className="h-4 w-4 text-gray-500 dark:text-gray-400" />
                                        </div>
                                        <div>
                                            <p className="text-[13px] font-medium text-gray-900 dark:text-gray-100">
                                                Inactive ad accounts
                                            </p>
                                            <p className="mt-0.5 text-[12px] text-gray-500 dark:text-gray-400">
                                                A daily list of ad accounts Meta
                                                no longer reports as Active —
                                                disabled, unsettled, in grace
                                                period or closed — worst first.
                                            </p>
                                        </div>
                                    </div>
                                    <Switch
                                        checked={enabled}
                                        onCheckedChange={(v) =>
                                            setData(
                                                'inactive_accounts_enabled',
                                                v,
                                            )
                                        }
                                        aria-label="Enable inactive ad accounts report"
                                    />
                                </div>

                                <div className="mt-3 space-y-3 border-t border-black/6 pt-3 dark:border-white/6">
                                    <div className="grid gap-1.5">
                                        <Label
                                            htmlFor="inactive_accounts_webhook_url"
                                            className="text-[12px] text-gray-500 dark:text-gray-400"
                                        >
                                            Discord webhook URL
                                        </Label>
                                        <Input
                                            id="inactive_accounts_webhook_url"
                                            type="url"
                                            value={
                                                data.inactive_accounts_webhook_url
                                            }
                                            onChange={(e) =>
                                                setData(
                                                    'inactive_accounts_webhook_url',
                                                    e.target.value,
                                                )
                                            }
                                            disabled={!enabled}
                                            placeholder="https://discord.com/api/webhooks/…"
                                            autoComplete="off"
                                            className="h-8"
                                        />
                                        {!data.inactive_accounts_webhook_url && (
                                            <p className="text-[11px] text-gray-400 dark:text-gray-500">
                                                No webhook set — this report
                                                will not be sent until you add
                                                one.
                                            </p>
                                        )}
                                        <InputError
                                            message={
                                                errors.inactive_accounts_webhook_url
                                            }
                                        />
                                    </div>

                                    <div className="flex items-center gap-2">
                                        <Clock className="h-3.5 w-3.5 text-gray-400 dark:text-gray-500" />
                                        <Label
                                            htmlFor="inactive_accounts_send_at"
                                            className="text-[12px] text-gray-500 dark:text-gray-400"
                                        >
                                            Send daily at
                                        </Label>
                                        <select
                                            id="inactive_accounts_send_at"
                                            value={
                                                data.inactive_accounts_send_at
                                            }
                                            onChange={(e) =>
                                                setData(
                                                    'inactive_accounts_send_at',
                                                    e.target.value,
                                                )
                                            }
                                            disabled={!enabled}
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
                                            message={
                                                errors.inactive_accounts_send_at
                                            }
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>

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
