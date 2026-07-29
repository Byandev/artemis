import HeadingSmall from '@/components/heading-small';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { type BreadcrumbItem } from '@/types';
import { Workspace } from '@/types/models/Workspace';
import { Transition } from '@headlessui/react';
import { Head, useForm } from '@inertiajs/react';
import { ArrowRight, CalendarClock, ListChecks, Tags } from 'lucide-react';
import { type FormEventHandler } from 'react';

/** Radix Select has no empty-string value, so "unmapped" needs a sentinel. */
const NO_TAG = '__none__';

type AutoTagMap = Record<string, string | null>;

interface Props {
    workspace: Workspace;
    settings: {
        enable_edit_previous_day: boolean;
        enable_bulk_status_update: boolean;
        enable_auto_tag_status: boolean;
        auto_tag_status_map: AutoTagMap;
    };
    /** Parcel statuses that can drive an auto-tag: {key: label}. */
    parcel_statuses: Record<string, string>;
    /** Every RMO status a row may be tagged with. */
    rmo_statuses: string[];
    /** Seeded into an empty map the first time auto-tagging is switched on. */
    default_auto_tag_map: AutoTagMap;
}

export default function RmoSettings({
    workspace,
    settings,
    parcel_statuses,
    rmo_statuses,
    default_auto_tag_map,
}: Props) {
    const baseUrl = `/workspaces/${workspace.slug}/settings/rmo`;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'RMO management', href: baseUrl },
    ];

    const { data, setData, put, processing, recentlySuccessful } = useForm({
        enable_edit_previous_day: settings.enable_edit_previous_day,
        enable_bulk_status_update: settings.enable_bulk_status_update,
        enable_auto_tag_status: settings.enable_auto_tag_status,
        auto_tag_status_map: settings.auto_tag_status_map ?? {},
    });

    const mappedCount = Object.values(data.auto_tag_status_map).filter(
        Boolean,
    ).length;

    /**
     * Switching auto-tagging on with nothing mapped would silently do nothing,
     * so seed the obvious pairs (delivered → DELIVERED) as a starting point.
     */
    const toggleAutoTag = (enabled: boolean) => {
        setData((current) => ({
            ...current,
            enable_auto_tag_status: enabled,
            auto_tag_status_map:
                enabled &&
                Object.values(current.auto_tag_status_map).filter(Boolean)
                    .length === 0
                    ? { ...default_auto_tag_map }
                    : current.auto_tag_status_map,
        }));
    };

    const setMapping = (parcelStatus: string, rmoStatus: string) => {
        setData((current) => ({
            ...current,
            auto_tag_status_map: {
                ...current.auto_tag_status_map,
                [parcelStatus]: rmoStatus === NO_TAG ? null : rmoStatus,
            },
        }));
    };

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
                                                courier. Once a parcel reports{' '}
                                                <strong>Delivered</strong>, its
                                                RMO status re-tags itself to{' '}
                                                <strong>DELIVERED</strong> — no
                                                CSR has to close the row out by
                                                hand.
                                            </p>
                                        </div>
                                    </div>
                                    <Switch
                                        checked={data.enable_auto_tag_status}
                                        onCheckedChange={toggleAutoTag}
                                        aria-label="Enable auto-tag status"
                                    />
                                </div>

                                {data.enable_auto_tag_status && (
                                    <div className="mt-4 space-y-3 border-t border-black/6 pt-4 dark:border-white/6">
                                        <div className="flex items-baseline justify-between gap-4">
                                            <p className="text-[12px] font-medium text-gray-700 dark:text-gray-300">
                                                Parcel status → RMO status
                                            </p>
                                            <p className="text-[11px] text-gray-400 dark:text-gray-500">
                                                {mappedCount} mapped
                                            </p>
                                        </div>

                                        <div className="space-y-2">
                                            {Object.entries(
                                                parcel_statuses,
                                            ).map(([key, label]) => (
                                                <div
                                                    key={key}
                                                    className="flex items-center gap-3"
                                                >
                                                    <span className="w-32 shrink-0 text-[12px] text-gray-600 dark:text-gray-400">
                                                        {label}
                                                    </span>
                                                    <ArrowRight className="h-3.5 w-3.5 shrink-0 text-gray-300 dark:text-gray-600" />
                                                    <Select
                                                        value={
                                                            data
                                                                .auto_tag_status_map[
                                                                key
                                                            ] ?? NO_TAG
                                                        }
                                                        onValueChange={(v) =>
                                                            setMapping(key, v)
                                                        }
                                                    >
                                                        <SelectTrigger
                                                            className="max-w-[240px]"
                                                            aria-label={`RMO status for ${label} parcels`}
                                                        >
                                                            <SelectValue />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem
                                                                value={NO_TAG}
                                                            >
                                                                Don&apos;t
                                                                auto-tag
                                                            </SelectItem>
                                                            {rmo_statuses.map(
                                                                (status) => (
                                                                    <SelectItem
                                                                        key={
                                                                            status
                                                                        }
                                                                        value={
                                                                            status
                                                                        }
                                                                    >
                                                                        {status}
                                                                    </SelectItem>
                                                                ),
                                                            )}
                                                        </SelectContent>
                                                    </Select>
                                                </div>
                                            ))}
                                        </div>

                                        <p className="text-[11px] text-gray-400 dark:text-gray-500">
                                            Auto-tagging wins over manual edits
                                            — a mapped parcel status re-applies
                                            its RMO status every night at
                                            midnight, covering the day that just
                                            ended. Leave a parcel status on
                                            &quot;Don&apos;t auto-tag&quot; to
                                            keep it under CSR control. Saving
                                            re-tags today&apos;s orders straight
                                            away, so you can check the mapping
                                            without waiting for the nightly run.
                                        </p>
                                    </div>
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
