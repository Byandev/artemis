import { Can } from '@/components/can';
import PageHeader from '@/components/common/PageHeader';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { PERMISSIONS } from '@/constants/permissions';
import AppLayout from '@/layouts/app-layout';
import { Workspace } from '@/types/models/Workspace';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Loader2, Save, Search } from 'lucide-react';
import { useMemo, useState } from 'react';
import { toast } from 'sonner';

type AccessLevel = 'view' | 'manage';
type Access = 'none' | AccessLevel;

interface AdAccountItem {
    id: string;
    name: string;
    connected_by?: string;
    owner?: { id: number; name: string } | null;
}

interface Props {
    workspace: Workspace;
    team: { id: number; name: string };
    adAccounts: AdAccountItem[];
    assigned: Record<string, AccessLevel>;
}

const BACK_BTN =
    'flex h-8 items-center gap-1.5 rounded-lg border border-black/8 bg-stone-100 px-3 font-mono! text-[12px]! font-medium text-gray-600 transition-all hover:bg-stone-200 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-300 dark:hover:bg-zinc-700';
const SAVE_BTN =
    'flex h-8 items-center gap-1.5 rounded-lg bg-brand-600 px-3.5 font-mono! text-[12px]! font-medium text-white transition-all hover:bg-brand-700 disabled:opacity-40';

const OPTIONS: { value: Access; label: string }[] = [
    { value: 'none', label: 'No access' },
    { value: 'view', label: 'View' },
    { value: 'manage', label: 'Manage' },
];

export default function TeamAdAccounts({
    workspace,
    team,
    adAccounts,
    assigned,
}: Props) {
    // Map of adAccountId -> 'view' | 'manage'. Absent means no access.
    const [access, setAccess] = useState<Map<string, AccessLevel>>(
        new Map(Object.entries(assigned)),
    );
    const [search, setSearch] = useState('');
    // '' = all owners, 'unassigned' = no owner, otherwise the owner id as a string.
    const [ownerFilter, setOwnerFilter] = useState('');
    const [saving, setSaving] = useState(false);

    // Distinct owners present among the loaded accounts, for the filter dropdown.
    const ownerOptions = useMemo(() => {
        const map = new Map<number, string>();
        for (const a of adAccounts) {
            if (a.owner) map.set(a.owner.id, a.owner.name);
        }
        return Array.from(map, ([id, name]) => ({ id, name })).sort((a, b) =>
            a.name.localeCompare(b.name),
        );
    }, [adAccounts]);

    const filtered = useMemo(
        () =>
            adAccounts.filter((a) => {
                const matchesName = a.name
                    .toLowerCase()
                    .includes(search.toLowerCase().trim());
                const matchesOwner =
                    ownerFilter === ''
                        ? true
                        : ownerFilter === 'unassigned'
                          ? !a.owner
                          : String(a.owner?.id) === ownerFilter;
                return matchesName && matchesOwner;
            }),
        [adAccounts, search, ownerFilter],
    );

    const hasChanges = useMemo(() => {
        const original = new Map(Object.entries(assigned));
        if (original.size !== access.size) return true;
        for (const [id, level] of access) {
            if (original.get(id) !== level) return true;
        }
        return false;
    }, [access, assigned]);

    const setLevel = (id: string, value: Access) => {
        setAccess((prev) => {
            const next = new Map(prev);
            if (value === 'none') next.delete(id);
            else next.set(id, value);
            return next;
        });
    };

    const save = () => {
        setSaving(true);
        router.put(
            `/workspaces/${workspace.slug}/teams/${team.id}/ad-accounts`,
            {
                ad_accounts: Array.from(access.entries()).map(
                    ([id, access_level]) => ({ id, access_level }),
                ),
            },
            {
                preserveScroll: true,
                onSuccess: () => toast.success('Team ad accounts updated'),
                onError: () => toast.error('Failed to save'),
                onFinish: () => setSaving(false),
            },
        );
    };

    return (
        <AppLayout>
            <Head title={`${team.name} Ad Accounts — ${workspace.name}`} />
            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
                <PageHeader
                    title={`${team.name} — Ad Accounts`}
                    description="Grant this team access to ad accounts. View lets members see the account; Manage also lets them change budgets, statuses and approve proposals."
                    stackActionsOnMobile
                >
                    <Link
                        href={`/workspaces/${workspace.slug}/teams`}
                        className={BACK_BTN}
                    >
                        <ArrowLeft className="h-3.5 w-3.5" />
                        Back
                    </Link>
                    <Can permission={PERMISSIONS.EditTeams}>
                        <button
                            onClick={save}
                            disabled={saving || !hasChanges}
                            className={SAVE_BTN}
                        >
                            {saving ? (
                                <Loader2 className="h-3.5 w-3.5 animate-spin" />
                            ) : (
                                <Save className="h-3.5 w-3.5" />
                            )}
                            Save
                        </button>
                    </Can>
                </PageHeader>

                {/* Filters */}
                <div className="mb-3 flex flex-col gap-2 sm:flex-row sm:items-center">
                    <div className="flex flex-1 items-center gap-2 rounded-lg border border-black/8 bg-white px-3 dark:border-white/8 dark:bg-zinc-900">
                        <Search className="h-4 w-4 text-gray-400" />
                        <input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Search ad accounts…"
                            className="h-9 w-full bg-transparent text-[13px] text-gray-700 outline-none placeholder:text-gray-400 dark:text-gray-200"
                        />
                    </div>
                    <Select
                        value={ownerFilter || 'all'}
                        onValueChange={(v) =>
                            setOwnerFilter(v === 'all' ? '' : v)
                        }
                    >
                        <SelectTrigger className="h-9 w-full font-mono text-[11px] sm:w-[220px]">
                            <SelectValue placeholder="All owners" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All owners</SelectItem>
                            <SelectItem value="unassigned">
                                Unassigned
                            </SelectItem>
                            {ownerOptions.map((o) => (
                                <SelectItem key={o.id} value={String(o.id)}>
                                    {o.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                {/* List */}
                {filtered.length === 0 ? (
                    <div className="rounded-[14px] border border-black/6 bg-white py-16 text-center text-sm text-gray-400 dark:border-white/6 dark:bg-zinc-900">
                        No ad accounts found.
                    </div>
                ) : (
                    <div className="grid grid-cols-1 gap-2 sm:grid-cols-2 xl:grid-cols-3">
                        {filtered.map((account) => {
                            const current: Access =
                                access.get(account.id) ?? 'none';
                            return (
                                <div
                                    key={account.id}
                                    className="flex flex-col gap-2.5 rounded-xl border border-black/6 bg-white p-4 dark:border-white/6 dark:bg-zinc-900"
                                >
                                    <div className="min-w-0">
                                        <p className="truncate text-[13px] font-medium text-gray-800 dark:text-gray-200">
                                            {account.name}
                                        </p>
                                        {account.connected_by && (
                                            <p className="truncate text-[11px] text-gray-400">
                                                Connected by{' '}
                                                {account.connected_by}
                                            </p>
                                        )}
                                    </div>
                                    <div className="flex rounded-lg border border-black/8 bg-stone-50 p-0.5 dark:border-white/8 dark:bg-zinc-800">
                                        {OPTIONS.map((opt) => {
                                            const isActive =
                                                current === opt.value;
                                            return (
                                                <button
                                                    key={opt.value}
                                                    type="button"
                                                    onClick={() =>
                                                        setLevel(
                                                            account.id,
                                                            opt.value,
                                                        )
                                                    }
                                                    className={[
                                                        'flex-1 rounded-md px-2.5 py-1 font-mono text-[11px] font-medium transition-all',
                                                        isActive
                                                            ? opt.value ===
                                                              'manage'
                                                                ? 'bg-brand-600 text-white'
                                                                : opt.value ===
                                                                    'view'
                                                                  ? 'bg-gray-700 text-white dark:bg-zinc-600'
                                                                  : 'bg-white text-gray-600 shadow-sm dark:bg-zinc-900 dark:text-gray-300'
                                                            : 'text-gray-400 hover:text-gray-600 dark:hover:text-gray-300',
                                                    ].join(' ')}
                                                >
                                                    {opt.label}
                                                </button>
                                            );
                                        })}
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                )}

                <p className="mt-3 px-1 font-mono text-[11px] text-gray-400">
                    {access.size} of {adAccounts.length} ad account
                    {adAccounts.length === 1 ? '' : 's'} granted
                </p>
            </div>
        </AppLayout>
    );
}
