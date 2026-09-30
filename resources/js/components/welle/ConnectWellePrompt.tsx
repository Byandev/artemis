import { Button } from '@/components/ui/button';
import { edit as integrationSettings } from '@/routes/integrations';
import { Link } from '@inertiajs/react';
import { Plug } from 'lucide-react';

/**
 * My ESC before there is a Welle account behind it — the one thing to do, and
 * the way straight to it.
 *
 * Shown in place of the cards rather than above them. Every figure on this page
 * is read out of Welle, so with no account connected there is nothing for a
 * skeleton to resolve into, and a screen of dashes and empty calendars would
 * read as a month spent doing nothing rather than as a setup step never taken.
 *
 * The button goes to this workspace's Settings → Integrations, where the Welle
 * email and password are exchanged for a token — the credentials live on the
 * person, not the workspace, which is why this is a link out to their own
 * settings rather than a form inlined here.
 */
export function ConnectWellePrompt({
    workspaceSlug,
}: {
    workspaceSlug: string;
}) {
    return (
        <div className="flex flex-col items-center justify-center rounded-2xl border border-dashed border-black/10 py-20 text-center dark:border-white/10">
            <div className="mb-4 flex h-12 w-12 items-center justify-center rounded-2xl bg-brand-500/10 text-brand-600 dark:text-brand-400">
                <Plug className="h-6 w-6" />
            </div>
            <p className="text-sm font-medium text-gray-700 dark:text-gray-200">
                Set up your Welle account first
            </p>
            <p className="mt-1 max-w-sm text-xs text-gray-400 dark:text-gray-500">
                My ESC reads your Extreme Self Care days from Welle. Connect
                your Welle email and password once — your password is exchanged
                for a token and never stored — and this page fills in.
            </p>
            <Button asChild className="mt-5" size="sm">
                <Link
                    href={integrationSettings.url({ workspace: workspaceSlug })}
                >
                    <Plug className="mr-1 h-4 w-4" />
                    Connect Welle
                </Link>
            </Button>
        </div>
    );
}
