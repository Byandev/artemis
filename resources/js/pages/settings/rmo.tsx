import HeadingSmall from '@/components/heading-small';
import { Button } from '@/components/ui/button';
import { Switch } from '@/components/ui/switch';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { type BreadcrumbItem } from '@/types';
import { Workspace } from '@/types/models/Workspace';
import { Transition } from '@headlessui/react';
import { Head, useForm } from '@inertiajs/react';
import { CalendarClock } from 'lucide-react';
import { type FormEventHandler } from 'react';

interface Props {
    workspace: Workspace;
    settings: {
        enable_edit_previous_day: boolean;
    };
}

export default function RmoSettings({ workspace, settings }: Props) {
    const baseUrl = `/workspaces/${workspace.slug}/settings/rmo`;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'RMO management', href: baseUrl },
    ];

    const { data, setData, put, processing, recentlySuccessful } = useForm({
        enable_edit_previous_day: settings.enable_edit_previous_day,
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
                        description="Control how far back the RMO management page stays editable."
                    />

                    <form onSubmit={submit} className="space-y-8">
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
                                            Allow assigning and updating status
                                            on <strong>every</strong> past
                                            delivery date, not just yesterday.
                                            When off, only today&apos;s orders
                                            and yesterday&apos;s
                                            delivered/returned parcels can be
                                            changed.
                                        </p>
                                    </div>
                                </div>
                                <Switch
                                    checked={data.enable_edit_previous_day}
                                    onCheckedChange={(v) =>
                                        setData('enable_edit_previous_day', v)
                                    }
                                    aria-label="Enable editing previous days"
                                />
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
