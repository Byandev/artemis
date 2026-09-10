import { Button } from '@/components/ui/button';
import axios from 'axios';
import { Loader2, RefreshCw } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';

interface Props {
    workspaceSlug: string;
}

/**
 * Runs both nightly CSR rollups on the spot, for today and yesterday.
 *
 * The schedule (03:00 and 04:15) still owns the full backfill — this is the
 * manual nudge for when the current day's figures are needed before then. The
 * commands only queue the aggregation jobs, so the request returns straight
 * away and the numbers move once the queue drains: hence "queued", not "done".
 */
export default function CsrSyncButton({ workspaceSlug }: Props) {
    const [running, setRunning] = useState(false);

    const run = async () => {
        if (running) return;

        setRunning(true);

        try {
            await axios.post(`/api/workspaces/${workspaceSlug}/csrs/sync`);

            toast.success('Sync queued', {
                description:
                    "Today's and yesterday's records are rebuilding — reload in a moment to see the updated figures.",
            });
        } catch (error) {
            const status = axios.isAxiosError(error)
                ? error.response?.status
                : undefined;

            toast.error('Sync failed', {
                description:
                    status === 429
                        ? 'Too many runs in a row — wait a minute and try again.'
                        : status === 403
                          ? 'You do not have permission to run this sync.'
                          : 'The sync could not be started.',
            });
        } finally {
            setRunning(false);
        }
    };

    return (
        <Button
            variant="outline"
            size="sm"
            onClick={run}
            disabled={running}
            className="shrink-0"
            title="Re-run the CSR daily and call rollups for today and yesterday"
        >
            {running ? (
                <Loader2 className="size-3.5 animate-spin" />
            ) : (
                <RefreshCw className="size-3.5" />
            )}
            {running ? 'Syncing…' : 'Sync now'}
        </Button>
    );
}
