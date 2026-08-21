import HeadingSmall from '@/components/heading-small';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { PERMISSIONS } from '@/constants/permissions';
import { usePermission } from '@/hooks/use-permission';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { type BreadcrumbItem } from '@/types';
import { Workspace } from '@/types/models/Workspace';
import { Transition } from '@headlessui/react';
import { Form, Head } from '@inertiajs/react';
import { ReceiptText } from 'lucide-react';

type BillingSettings = {
    billing_name: string | null;
    billing_address: string | null;
    billing_email: string | null;
};

export default function Billing({
    workspace,
    settings,
}: {
    workspace: Workspace;
    settings: BillingSettings;
}) {
    const baseUrl = `/workspaces/${workspace.slug}/settings/billing`;
    const canManage = usePermission(PERMISSIONS.ManageBillingSettings);

    const breadcrumbs: BreadcrumbItem[] = [{ title: 'Billing', href: baseUrl }];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Billing" />

            <SettingsLayout workspace={workspace}>
                <div className="space-y-6">
                    <HeadingSmall
                        title="Billing Details"
                        description="Who this workspace's invoices are billed to."
                    />

                    <div className="flex items-start gap-2.5 rounded-[12px] border border-emerald-500/20 bg-emerald-500/[0.04] p-3 text-[12px] leading-relaxed text-emerald-800 dark:text-emerald-300">
                        <ReceiptText className="mt-0.5 h-4 w-4 shrink-0 text-emerald-600 dark:text-emerald-400" />
                        <p>
                            These details prefill the &ldquo;bill to&rdquo;
                            fields when an admin raises an invoice for this
                            workspace. Leave a field blank to fall back to the
                            account details of the workspace owner.
                        </p>
                    </div>

                    <Form
                        action={baseUrl}
                        method="put"
                        options={{ preserveScroll: true }}
                        className="space-y-6"
                    >
                        {({ processing, recentlySuccessful, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="billing_name">
                                        Billing name
                                    </Label>
                                    <Input
                                        id="billing_name"
                                        type="text"
                                        name="billing_name"
                                        className="mt-1 block w-full"
                                        defaultValue={
                                            settings.billing_name ?? ''
                                        }
                                        disabled={!canManage}
                                        autoComplete="organization"
                                        placeholder="Registered business name"
                                    />
                                    <InputError
                                        className="mt-1"
                                        message={errors.billing_name}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="billing_email">
                                        Billing email
                                    </Label>
                                    <Input
                                        id="billing_email"
                                        type="email"
                                        name="billing_email"
                                        className="mt-1 block w-full"
                                        defaultValue={
                                            settings.billing_email ?? ''
                                        }
                                        disabled={!canManage}
                                        autoComplete="email"
                                        placeholder="accounts@example.com"
                                    />
                                    <InputError
                                        className="mt-1"
                                        message={errors.billing_email}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="billing_address">
                                        Billing address
                                    </Label>
                                    <Textarea
                                        id="billing_address"
                                        name="billing_address"
                                        rows={4}
                                        className="mt-1 block w-full"
                                        defaultValue={
                                            settings.billing_address ?? ''
                                        }
                                        disabled={!canManage}
                                        placeholder="Street, city, province, postal code"
                                    />
                                    <InputError
                                        className="mt-1"
                                        message={errors.billing_address}
                                    />
                                </div>

                                {canManage && (
                                    <div className="flex items-center gap-4">
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            Save
                                        </Button>

                                        <Transition
                                            show={recentlySuccessful}
                                            enter="transition ease-in-out"
                                            enterFrom="opacity-0"
                                            leave="transition ease-in-out"
                                            leaveTo="opacity-0"
                                        >
                                            <p className="text-sm text-neutral-600">
                                                Saved
                                            </p>
                                        </Transition>
                                    </div>
                                )}
                            </>
                        )}
                    </Form>
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
