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
import { Clock, PackageCheck, TriangleAlert } from 'lucide-react';
import { type ComponentType, type FormEventHandler } from 'react';

interface NotificationSettings {
    discord_webhook_url: string | null;
    deliveries_enabled: boolean;
    deliveries_send_at: string;
    awaiting_enabled: boolean;
    awaiting_send_at: string;
}

interface Props {
    workspace: Workspace;
    settings: NotificationSettings;
    hasEnvWebhookFallback: boolean;
}

// The Discord notifications this workspace can receive. Kept in one place so the
// list is easy to extend as new inventory alerts are added.
const NOTIFICATIONS: {
    key: 'deliveries' | 'awaiting';
    icon: ComponentType<{ className?: string }>;
    title: string;
    description: string;
}[] = [
    {
        key: 'deliveries',
        icon: PackageCheck,
        title: 'Deliveries received',
        description:
            'A daily summary of items received that day, with an on-time / late / early note per item.',
    },
    {
        key: 'awaiting',
        icon: TriangleAlert,
        title: 'Orders not yet delivered',
        description:
            'A daily list of purchase orders still awaiting delivery (overdue + due today), most overdue first.',
    },
];

export default function NotificationsSettings({
    workspace,
    settings,
    hasEnvWebhookFallback,
}: Props) {
    const baseUrl = `/workspaces/${workspace.slug}/settings/notifications`;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Discord notifications', href: baseUrl },
    ];

    const { data, setData, put, processing, errors, recentlySuccessful } =
        useForm({
            discord_webhook_url: settings.discord_webhook_url ?? '',
            deliveries_enabled: settings.deliveries_enabled,
            deliveries_send_at: settings.deliveries_send_at,
            awaiting_enabled: settings.awaiting_enabled,
            awaiting_send_at: settings.awaiting_send_at,
        });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(baseUrl, { preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Discord notifications" />

            <SettingsLayout workspace={workspace}>
                <div className="space-y-6">
                    <HeadingSmall
                        title="Discord Notifications"
                        description="Send inventory delivery updates to a Discord channel on a daily schedule."
                    />

                    <form onSubmit={submit} className="space-y-8">
                        {/* Webhook */}
                        <div className="grid gap-2">
                            <Label htmlFor="discord_webhook_url">
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
                                placeholder="https://discord.com/api/webhooks/…"
                                autoComplete="off"
                            />
                            <p className="text-[12px] text-gray-500 dark:text-gray-400">
                                Server Settings → Integrations → Webhooks → New
                                Webhook → Copy URL.
                                {!data.discord_webhook_url &&
                                    (hasEnvWebhookFallback
                                        ? ' Leave blank to use the app default webhook.'
                                        : ' No webhook is set — notifications will not be sent until you add one.')}
                            </p>
                            <InputError
                                className="mt-1"
                                message={errors.discord_webhook_url}
                            />
                        </div>

                        {/* Notification list */}
                        <div className="space-y-3">
                            <p className="font-mono text-[10px] font-medium tracking-wider text-gray-400 uppercase dark:text-gray-500">
                                Notifications
                            </p>

                            {NOTIFICATIONS.map((n) => {
                                const enabledKey =
                                    `${n.key}_enabled` as const;
                                const timeKey = `${n.key}_send_at` as const;
                                const enabled = data[enabledKey];
                                const Icon = n.icon;

                                return (
                                    <div
                                        key={n.key}
                                        className="rounded-[12px] border border-black/8 bg-white p-4 dark:border-white/8 dark:bg-zinc-900"
                                    >
                                        <div className="flex items-start justify-between gap-4">
                                            <div className="flex items-start gap-3">
                                                <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-[10px] bg-stone-100 dark:bg-zinc-800">
                                                    <Icon className="h-4 w-4 text-gray-500 dark:text-gray-400" />
                                                </div>
                                                <div>
                                                    <p className="text-[13px] font-medium text-gray-900 dark:text-gray-100">
                                                        {n.title}
                                                    </p>
                                                    <p className="mt-0.5 text-[12px] text-gray-500 dark:text-gray-400">
                                                        {n.description}
                                                    </p>
                                                </div>
                                            </div>
                                            <Switch
                                                checked={enabled}
                                                onCheckedChange={(v) =>
                                                    setData(enabledKey, v)
                                                }
                                                aria-label={`Enable ${n.title}`}
                                            />
                                        </div>

                                        <div className="mt-3 flex items-center gap-2 border-t border-black/6 pt-3 dark:border-white/6">
                                            <Clock className="h-3.5 w-3.5 text-gray-400 dark:text-gray-500" />
                                            <Label
                                                htmlFor={timeKey}
                                                className="text-[12px] text-gray-500 dark:text-gray-400"
                                            >
                                                Send daily at
                                            </Label>
                                            <Input
                                                id={timeKey}
                                                type="time"
                                                value={data[timeKey]}
                                                onChange={(e) =>
                                                    setData(
                                                        timeKey,
                                                        e.target.value,
                                                    )
                                                }
                                                disabled={!enabled}
                                                className="h-8 w-32"
                                            />
                                            <InputError
                                                message={errors[timeKey]}
                                            />
                                        </div>
                                    </div>
                                );
                            })}
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
