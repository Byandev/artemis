import PageHeader from '@/components/common/PageHeader';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { format, parseISO } from 'date-fns';
import { AtSign, Hash, Phone, Store, UserRound } from 'lucide-react';

interface AccountShop {
    id: number;
    name: string | null;
}

/**
 * One pancake user linked to the signed-in account, as CSRController@pancakeUsers
 * shares it — narrowed to this workspace, shops included.
 */
interface PancakeUser {
    id: string;
    name: string | null;
    email: string | null;
    phone_number: string | null;
    fb_id: string | null;
    /** `ACTIVE` | `INACTIVE`. */
    status: string;
    shops: AccountShop[];
    /** `YYYY-MM-DD` of the last day either nightly rollup recorded anything. */
    last_active_on: string | null;
}

interface Props {
    workspace: {
        id: number;
        name: string;
        slug: string;
    };
    pancakeUsers: PancakeUser[];
}

/** First letters of the first two words — "Angeline Mercado" becomes "AM". */
function initials(name: string): string {
    return (
        name
            .split(' ')
            .filter(Boolean)
            .slice(0, 2)
            .map((word) => word[0])
            .join('')
            .toUpperCase() || '?'
    );
}

/**
 * Who the workspace thinks the signed-in user is, on Pancake's side.
 *
 * The CSR dashboard sums these pancake users' rollup rows without ever naming
 * them, so this page is the legend for it: every linked login, the shops each
 * one works, and the last day the nightly rollups recorded anything against it.
 * A CSR seeing a figure they do not recognise can tell here whether a second
 * login is folded into it — or whether the one they expected was never linked.
 */
export default function PancakeUsers({ workspace, pancakeUsers }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'CSR Dashboard',
            href: `/workspaces/${workspace.slug}/csr/dashboard`,
        },
        {
            title: 'My Pancake Users',
            href: `/workspaces/${workspace.slug}/csr/pancake-users`,
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="My Pancake Users" />

            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 sm:p-6">
                <PageHeader
                    title="My Pancake Users"
                    description="The Pancake users linked to your account in this workspace — the identities your CSR dashboard figures are drawn from."
                />

                {pancakeUsers.length === 0 ? (
                    <EmptyState workspaceSlug={workspace.slug} />
                ) : (
                    <div className="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
                        {pancakeUsers.map((pancakeUser) => (
                            <PancakeUserCard
                                key={pancakeUser.id}
                                pancakeUser={pancakeUser}
                            />
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}

function PancakeUserCard({ pancakeUser }: { pancakeUser: PancakeUser }) {
    const isActive = pancakeUser.status.toUpperCase() === 'ACTIVE';
    const name = pancakeUser.name || pancakeUser.email || 'Unnamed user';

    return (
        <div className="rounded-[14px] border border-black/6 bg-white p-[18px] dark:border-white/6 dark:bg-zinc-900">
            <div className="mb-4 flex items-start justify-between gap-2">
                <div className="flex min-w-0 items-center gap-2.5">
                    <span
                        className={`inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-[11px] font-bold text-white ${
                            isActive
                                ? 'bg-emerald-500'
                                : 'bg-gray-300 dark:bg-zinc-700'
                        }`}
                    >
                        {initials(name)}
                    </span>
                    <div className="min-w-0">
                        <span className="block truncate text-[13px] font-semibold text-gray-800 dark:text-gray-100">
                            {name}
                        </span>
                        <span className="block text-[11px] text-gray-400 dark:text-gray-500">
                            {pancakeUser.last_active_on
                                ? `Last active ${format(parseISO(pancakeUser.last_active_on), 'MMM d, yyyy')}`
                                : 'No recorded activity yet'}
                        </span>
                    </div>
                </div>

                <span
                    className={`inline-flex shrink-0 items-center rounded-full px-2.5 py-0.5 text-[11px] font-bold ${
                        isActive
                            ? 'bg-[#E6F9F1] text-[#10B981]'
                            : 'bg-[#FFF1F2] text-[#F43F5E]'
                    }`}
                >
                    <span
                        className={`mr-1.5 h-1.5 w-1.5 rounded-full ${isActive ? 'bg-[#10B981]' : 'bg-[#F43F5E]'}`}
                    />
                    {pancakeUser.status.toUpperCase()}
                </span>
            </div>

            <dl className="space-y-2">
                <Field icon={AtSign} label="Email" value={pancakeUser.email} />
                <Field
                    icon={Phone}
                    label="Phone"
                    value={pancakeUser.phone_number}
                />
                <Field
                    icon={Hash}
                    label="Facebook ID"
                    value={pancakeUser.fb_id}
                />
            </dl>

            <div className="mt-4 border-t border-black/6 pt-3 dark:border-white/6">
                <div className="mb-2 flex items-center gap-1.5 text-[11px] font-medium text-gray-400 dark:text-gray-500">
                    <Store className="h-3.5 w-3.5" />
                    <span>
                        {pancakeUser.shops.length === 1
                            ? '1 shop'
                            : `${pancakeUser.shops.length} shops`}
                    </span>
                </div>
                <div className="flex flex-wrap gap-1.5">
                    {pancakeUser.shops.map((shop) => (
                        <span
                            key={shop.id}
                            className="rounded-md bg-stone-100 px-2 py-0.5 text-[11px] text-gray-600 dark:bg-zinc-800 dark:text-gray-300"
                        >
                            {shop.name || `Shop #${shop.id}`}
                        </span>
                    ))}
                </div>
            </div>
        </div>
    );
}

function Field({
    icon: Icon,
    label,
    value,
}: {
    icon: typeof Phone;
    label: string;
    value: string | null;
}) {
    return (
        <div className="flex items-center gap-2">
            <Icon className="h-3.5 w-3.5 shrink-0 text-gray-400 dark:text-gray-500" />
            <dt className="sr-only">{label}</dt>
            <dd className="min-w-0 flex-1 truncate font-mono text-[12px] text-gray-600 dark:text-gray-300">
                {value || (
                    <span className="text-gray-300 italic dark:text-gray-600">
                        Not set
                    </span>
                )}
            </dd>
        </div>
    );
}

/**
 * No linked pancake user is the same story the dashboard tells in zeros, so
 * this says what to do about it rather than just reporting the absence.
 */
function EmptyState({ workspaceSlug }: { workspaceSlug: string }) {
    return (
        <div className="rounded-[14px] border border-dashed border-black/10 bg-white p-10 text-center dark:border-white/10 dark:bg-zinc-900">
            <span className="mx-auto mb-3 flex h-10 w-10 items-center justify-center rounded-full bg-stone-100 dark:bg-zinc-800">
                <UserRound className="h-5 w-5 text-gray-400 dark:text-gray-500" />
            </span>
            <p className="text-[13px] font-semibold text-gray-800 dark:text-gray-100">
                No Pancake user linked to you
            </p>
            <p className="mx-auto mt-1 max-w-md text-[12px] text-gray-400 dark:text-gray-500">
                Your CSR dashboard reads the rollups keyed to your Pancake
                logins, so until an admin links one on the Employees page it
                will show zeros. Ask a workspace admin to connect yours.
            </p>
            <Link
                href={`/workspaces/${workspaceSlug}/csr/dashboard`}
                className="mt-4 inline-flex h-9 items-center rounded-[10px] border border-black/6 bg-stone-50 px-3 text-[12px] font-medium text-gray-600 transition-colors hover:bg-stone-100 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-300 dark:hover:bg-zinc-700"
            >
                Back to CSR Dashboard
            </Link>
        </div>
    );
}
