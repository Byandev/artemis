import HeadingSmall from '@/components/heading-small';
import HelpTooltip from '@/components/help-tooltip';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { type BreadcrumbItem } from '@/types';
import { Workspace } from '@/types/models/Workspace';
import { Transition } from '@headlessui/react';
import { Form, Head, router } from '@inertiajs/react';
import { CheckCircle2, Workflow } from 'lucide-react';

export default function ErpCredentials({ workspace }: { workspace: Workspace }) {
    const baseUrl = `/workspaces/${workspace.slug}/settings/erp-credentials`;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'ERP credentials', href: baseUrl },
    ];

    const passwordSet = Boolean(workspace.erp_password_set);

    const clearPassword = () => {
        router.delete(baseUrl, { preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="ERP credentials" />

            <SettingsLayout workspace={workspace}>
                <div className="space-y-6">
                    <div className="flex items-center gap-1.5">
                        <HeadingSmall
                            title="ERP Credentials"
                            description="Connection details the automation pipeline uses to reach your external ERP."
                        />
                        <HelpTooltip side="right">
                            These credentials are used by our{' '}
                            <span className="font-semibold">n8n</span>{' '}
                            integration to authenticate against your external ERP
                            and automatically fetch its data into this workspace.
                            The password is stored encrypted and is never shown
                            again after saving.
                        </HelpTooltip>
                    </div>

                    <div className="flex items-start gap-2.5 rounded-[12px] border border-emerald-500/20 bg-emerald-500/[0.04] p-3 text-[12px] leading-relaxed text-emerald-800 dark:text-emerald-300">
                        <Workflow className="mt-0.5 h-4 w-4 shrink-0 text-emerald-600 dark:text-emerald-400" />
                        <p>
                            Used for the n8n automation that fetches data from
                            your external ERP. Enter the login the pipeline
                            should use — keep it to a dedicated integration
                            account where possible.
                        </p>
                    </div>

                    <Form
                        action={baseUrl}
                        method="put"
                        options={{ preserveScroll: true }}
                        resetOnSuccess={['erp_password']}
                        className="space-y-6"
                    >
                        {({ processing, recentlySuccessful, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="erp_email">ERP email</Label>
                                    <Input
                                        id="erp_email"
                                        type="email"
                                        name="erp_email"
                                        className="mt-1 block w-full"
                                        defaultValue={workspace.erp_email ?? ''}
                                        autoComplete="off"
                                        placeholder="integration@your-erp.com"
                                    />
                                    <InputError
                                        className="mt-1"
                                        message={errors.erp_email}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <div className="flex items-center justify-between">
                                        <Label htmlFor="erp_password">
                                            ERP password
                                        </Label>
                                        {passwordSet && (
                                            <span className="inline-flex items-center gap-1 font-mono text-[10px] tracking-wide text-emerald-600 uppercase dark:text-emerald-400">
                                                <CheckCircle2 className="h-3 w-3" />
                                                Configured
                                            </span>
                                        )}
                                    </div>
                                    <Input
                                        id="erp_password"
                                        type="password"
                                        name="erp_password"
                                        className="mt-1 block w-full"
                                        autoComplete="new-password"
                                        placeholder={
                                            passwordSet
                                                ? '•••••••• (leave blank to keep current)'
                                                : 'Enter ERP password'
                                        }
                                    />
                                    <InputError
                                        className="mt-1"
                                        message={errors.erp_password}
                                    />
                                    {passwordSet && (
                                        <button
                                            type="button"
                                            onClick={clearPassword}
                                            className="mt-1 self-start text-[12px] text-red-500 transition-colors hover:text-red-600 hover:underline"
                                        >
                                            Remove stored password
                                        </button>
                                    )}
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
                            </>
                        )}
                    </Form>
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
