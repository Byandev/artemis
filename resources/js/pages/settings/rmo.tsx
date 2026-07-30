import HeadingSmall from '@/components/heading-small';
import { Button } from '@/components/ui/button';
import { Switch } from '@/components/ui/switch';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { type BreadcrumbItem } from '@/types';
import { Workspace } from '@/types/models/Workspace';
import { Transition } from '@headlessui/react';
import { Head, useForm } from '@inertiajs/react';
import { CalendarClock, ListChecks, Tags } from 'lucide-react';
import { type FormEventHandler } from 'react';

interface Props {
    workspace: Workspace;
    settings: {
        enable_edit_previous_day: boolean;
        enable_bulk_status_update: boolean;
        enable_auto_tag_status: boolean;
    };
}

export default function RmoSettings({ workspace, settings }: Props) {
    const baseUrl = `/workspaces/${workspace.slug}/settings/rmo`;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'RMO management', href: baseUrl },
    ];

    const { data, setData, put, processing, recentlySuccessful } = useForm({
        enable_edit_previous_day: settings.enable_edit_previous_day,
        enable_bulk_status_update: settings.enable_bulk_status_update,
        enable_auto_tag_status: settings.enable_auto_tag_status,
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
