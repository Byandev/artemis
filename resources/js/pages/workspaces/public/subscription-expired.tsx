import { Workspace } from '@/types/models/Workspace';
import { Head } from '@inertiajs/react';
import { CalendarX } from 'lucide-react';

interface Props {
    workspace: Pick<Workspace, 'id' | 'name' | 'slug'>;
}

export default function SubscriptionExpired({ workspace }: Props) {
    return (
        <div className="flex min-h-screen items-center justify-center bg-stone-100 p-4 dark:bg-zinc-950">
            <Head title="Subscription expired" />
            <div className="w-full max-w-sm rounded-[16px] border border-black/8 bg-white p-6 shadow-[0_8px_30px_rgba(0,0,0,0.08)] dark:border-white/8 dark:bg-zinc-900">
                <div className="flex flex-col items-center text-center">
                    <div className="mb-3 flex h-11 w-11 items-center justify-center rounded-full bg-amber-500/10 text-amber-600 dark:text-amber-400">
                        <CalendarX className="h-5 w-5" />
                    </div>
                    <h1 className="text-[15px] font-semibold text-gray-800 dark:text-gray-100">
                        Page unavailable
                    </h1>
                    <p className="mt-1 text-[12px] text-gray-500 dark:text-gray-400">
                        {workspace.name}&apos;s subscription has expired, so
                        this page can&apos;t be opened. Ask the workspace owner
                        to renew their plan.
                    </p>
                </div>
            </div>
        </div>
    );
}
