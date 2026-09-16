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
import { CheckCircle2, Plug } from 'lucide-react';

export default function Integrations({
    workspace,
    welle,
}: {
    workspace: Workspace;
    /** The signed-in user's Welle account — credentials are per user. */
    welle: { email: string | null; connected: boolean };
}) {
    const baseUrl = `/workspaces/${workspace.slug}/settings/integrations`;
    const welleUrl = `${baseUrl}/welle`;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Integrations', href: baseUrl },
    ];

    const connected = welle.connected;

    const disconnect = () => {
        router.delete(welleUrl, { preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Integrations" />

            <SettingsLayout workspace={workspace}>
                <div className="space-y-6">
                    <div className="flex items-center gap-1.5">
                        <HeadingSmall
                            title="Welle"
                            description="Connect your Welle account for this workspace."
                        />
                        <HelpTooltip side="right">
                            These credentials let the integration sign in to{' '}
                            <span className="font-semibold">Welle</span> as you.
                            They are stored on your own account — not the
                            workspace — and the password is kept encrypted and
                            never shown again after saving.
                        </HelpTooltip>
                    </div>

                    <div className="flex items-start gap-2.5 rounded-[12px] border border-emerald-500/20 bg-emerald-500/[0.04] p-3 text-[12px] leading-relaxed text-emerald-800 dark:text-emerald-300">
                        <Plug className="mt-0.5 h-4 w-4 shrink-0 text-emerald-600 dark:text-emerald-400" />
                        <p>
                            Enter the login the integration should use. Your
                            Welle account is yours alone: other members of this
                            workspace connect their own.
                        </p>
                    </div>

                    <Form
                        action={welleUrl}
                        method="put"
                        options={{ preserveScroll: true }}
                        resetOnSuccess={['welle_password']}
                        className="space-y-6"
                    >
                        {({ processing, recentlySuccessful, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <div className="flex items-center justify-between">
                                        <Label htmlFor="welle_email">
                                            Welle email
                                        </Label>
                                        {connected && (
                                            <span className="inline-flex items-center gap-1 font-mono text-[10px] tracking-wide text-emerald-600 uppercase dark:text-emerald-400">
                                                <CheckCircle2 className="h-3 w-3" />
                                                Connected
                                            </span>
                                        )}
                                    </div>
                                    <Input
                                        id="welle_email"
                                        type="email"
                                        name="welle_email"
                                        className="mt-1 block w-full"
                                        defaultValue={welle.email ?? ''}
                                        autoComplete="off"
                                        placeholder="integration@example.com"
                                    />
                                    <InputError
                                        className="mt-1"
                                        message={errors.welle_email}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="welle_password">
                                        Welle password
                                    </Label>
                                    <Input
                                        id="welle_password"
                                        type="password"
                                        name="welle_password"
                                        className="mt-1 block w-full"
                                        autoComplete="new-password"
                                        placeholder={
                                            connected
                                                ? '•••••••• (leave blank to keep current)'
                                                : 'Enter Welle password'
                                        }
                                    />
                                    <InputError
                                        className="mt-1"
                                        message={errors.welle_password}
                                    />
                                    {connected && (
                                        <button
                                            type="button"
                                            onClick={disconnect}
                                            className="mt-1 self-start text-[12px] text-red-500 transition-colors hover:text-red-600 hover:underline"
                                        >
                                            Disconnect Welle account
                                        </button>
                                    )}
                                </div>

                                <div className="flex items-center gap-4">
                                    <Button disabled={processing}>
                                        {connected ? 'Save' : 'Connect'}
                                    </Button>

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
