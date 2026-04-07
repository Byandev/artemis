import ComponentCard from '@/components/common/ComponentCard';
import PageHeader from '@/components/common/PageHeader';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import AppLayout from '@/layouts/app-layout';
import { Workspace } from '@/types/models/Workspace';
import { Head, router, useForm } from '@inertiajs/react';
import axios from 'axios';
import { Check, Copy, Eye, EyeOff, KeyRound, Loader2, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';

interface ApiKey {
    id: number;
    name: string;
    key_prefix: string;
    last_used_at: string | null;
    created_at: string;
}

interface Props {
    workspace: Workspace;
    apiKeys: ApiKey[];
}

export default function ApiKeys({ workspace, apiKeys }: Props) {
    const [createOpen, setCreateOpen]     = useState(false);
    const [keyToRevoke, setKeyToRevoke]   = useState<ApiKey | null>(null);
    // id → decrypted raw key (fetched on demand)
    const [rawKeys, setRawKeys]           = useState<Record<number, string>>({});
    // id → currently visible
    const [visible, setVisible]           = useState<Record<number, boolean>>({});
    // id → loading
    const [loadingId, setLoadingId]       = useState<number | null>(null);
    // id → just copied
    const [copiedId, setCopiedId]         = useState<number | null>(null);

    const form = useForm({ name: '' });

    const handleCreate = (e: React.FormEvent) => {
        e.preventDefault();
        form.post(`/workspaces/${workspace.slug}/api-keys`, {
            preserveScroll: true,
            onSuccess: () => { form.reset(); setCreateOpen(false); },
        });
    };

    const handleRevoke = (key: ApiKey) => {
        router.delete(`/workspaces/${workspace.slug}/api-keys/${key.id}`, {
            preserveScroll: true,
            onSuccess: () => setKeyToRevoke(null),
        });
    };

    const toggleVisible = async (key: ApiKey) => {
        // If already visible, just hide
        if (visible[key.id]) {
            setVisible(prev => ({ ...prev, [key.id]: false }));
            return;
        }
        // If raw key already fetched, just show
        if (rawKeys[key.id]) {
            setVisible(prev => ({ ...prev, [key.id]: true }));
            return;
        }
        // Fetch from backend
        setLoadingId(key.id);
        try {
            const res = await axios.get(`/workspaces/${workspace.slug}/api-keys/${key.id}/reveal`);
            setRawKeys(prev => ({ ...prev, [key.id]: res.data.key }));
            setVisible(prev => ({ ...prev, [key.id]: true }));
        } catch (err: any) {
            const msg = err?.response?.data?.error ?? 'Failed to reveal key.';
            alert(msg);
        } finally {
            setLoadingId(null);
        }
    };

    const copyKey = async (key: ApiKey) => {
        const raw = rawKeys[key.id];
        if (!raw) return;
        await navigator.clipboard.writeText(raw);
        setCopiedId(key.id);
        setTimeout(() => setCopiedId(null), 2000);
    };

    return (
        <AppLayout>
            <Head title={`${workspace.name} — API Keys`} />
            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
                <PageHeader title="API Keys" description="Manage API keys for external integrations">
                    <Dialog open={createOpen} onOpenChange={setCreateOpen}>
                        <DialogTrigger asChild>
                            <Button size="sm">
                                <Plus className="mr-1.5 h-4 w-4" />
                                New Key
                            </Button>
                        </DialogTrigger>
                        <DialogContent className="sm:max-w-md p-0 gap-0 overflow-hidden">
                            <div className="px-5 pt-5 pb-4 border-b border-black/6 dark:border-white/6">
                                <DialogHeader>
                                    <DialogTitle className="text-[15px] font-semibold text-gray-900 dark:text-gray-100">
                                        Create API Key
                                    </DialogTitle>
                                    <DialogDescription className="text-[12px] text-gray-400 dark:text-gray-500 mt-0.5">
                                        Give this key a name so you remember where it's used.
                                    </DialogDescription>
                                </DialogHeader>
                            </div>
                            <form onSubmit={handleCreate}>
                                <div className="space-y-5 px-5 py-4">
                                    <div className="space-y-1.5">
                                        <label className="block font-mono text-[10px] font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500">
                                            Name <span className="text-red-400">*</span>
                                        </label>
                                        <input
                                            type="text"
                                            autoFocus
                                            placeholder="e.g. External integration"
                                            value={form.data.name}
                                            onChange={e => form.setData('name', e.target.value)}
                                            className="h-10 w-full rounded-[10px] border border-black/8 bg-stone-50 px-3 font-mono! text-[13px]! text-gray-800 placeholder:text-gray-300 outline-none transition-all focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-100 dark:placeholder:text-gray-600 dark:focus:border-emerald-400"
                                            required
                                        />
                                        {form.errors.name && (
                                            <p className="font-mono text-[11px] text-red-500">{form.errors.name}</p>
                                        )}
                                    </div>
                                </div>
                                <div className="flex items-center justify-end gap-2 border-t border-black/6 dark:border-white/6 px-5 py-3">
                                    <button
                                        type="button"
                                        onClick={() => setCreateOpen(false)}
                                        className="flex h-9 items-center rounded-lg border border-black/8 bg-stone-100 px-4 font-mono! text-[12px]! font-medium text-gray-600 transition-all hover:bg-stone-200 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-300 dark:hover:bg-zinc-700"
                                    >
                                        Cancel
                                    </button>
                                    <button
                                        type="submit"
                                        disabled={form.processing}
                                        className="flex h-9 items-center rounded-lg bg-emerald-600 px-4 font-mono! text-[12px]! font-medium text-white transition-all hover:bg-emerald-700 disabled:opacity-50"
                                    >
                                        {form.processing ? 'Creating…' : 'Create Key'}
                                    </button>
                                </div>
                            </form>
                        </DialogContent>
                    </Dialog>
                </PageHeader>

                <ComponentCard>
                    {apiKeys.length === 0 ? (
                        <div className="flex flex-col items-center justify-center py-14 text-center">
                            <div className="mb-3 flex h-12 w-12 items-center justify-center rounded-2xl bg-gray-100 dark:bg-zinc-800">
                                <KeyRound className="h-5 w-5 text-gray-400" />
                            </div>
                            <p className="font-mono text-[13px] font-medium text-gray-700 dark:text-gray-300">No API keys yet</p>
                            <p className="mt-1 font-mono text-[11px] text-gray-400 dark:text-gray-500">
                                Create a key to start integrating with external platforms.
                            </p>
                        </div>
                    ) : (
                        <div className="divide-y divide-black/4 dark:divide-white/4">
                            {apiKeys.map(key => {
                                const isVisible  = visible[key.id];
                                const isLoading  = loadingId === key.id;
                                const isCopied   = copiedId === key.id;
                                const hasRaw     = !!rawKeys[key.id];

                                return (
                                    <div key={key.id} className="flex items-center gap-3 py-3.5 first:pt-0 last:pb-0">
                                        <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-gray-100 dark:bg-zinc-800">
                                            <KeyRound className="h-3.5 w-3.5 text-gray-400" />
                                        </div>

                                        <div className="min-w-0 flex-1">
                                            <p className="truncate font-mono text-[13px] font-medium text-gray-800 dark:text-gray-100">
                                                {key.name}
                                            </p>

                                            {/* Key display */}
                                            <div className="mt-1 flex items-center gap-1.5">
                                                <code className="rounded bg-gray-100 px-1.5 py-0.5 font-mono text-[11px] text-gray-500 dark:bg-zinc-800 dark:text-gray-400">
                                                    {isVisible && hasRaw
                                                        ? rawKeys[key.id]
                                                        : `${key.key_prefix}••••••••••••••••`}
                                                </code>

                                                <button
                                                    onClick={() => toggleVisible(key)}
                                                    title={isVisible ? 'Hide key' : 'Reveal key'}
                                                    className="text-gray-300 transition-colors hover:text-gray-500 dark:text-gray-600 dark:hover:text-gray-400"
                                                >
                                                    {isLoading
                                                        ? <Loader2 className="h-3.5 w-3.5 animate-spin" />
                                                        : isVisible
                                                            ? <EyeOff className="h-3.5 w-3.5" />
                                                            : <Eye className="h-3.5 w-3.5" />}
                                                </button>

                                                {hasRaw && (
                                                    <button
                                                        onClick={() => copyKey(key)}
                                                        title="Copy key"
                                                        className="text-gray-300 transition-colors hover:text-gray-500 dark:text-gray-600 dark:hover:text-gray-400"
                                                    >
                                                        {isCopied
                                                            ? <Check className="h-3.5 w-3.5 text-emerald-500" />
                                                            : <Copy className="h-3.5 w-3.5" />}
                                                    </button>
                                                )}
                                            </div>

                                            <p className="mt-0.5 font-mono text-[10px] text-gray-300 dark:text-gray-600">
                                                {key.last_used_at
                                                    ? `Last used ${new Date(key.last_used_at).toLocaleDateString()}`
                                                    : 'Never used'}
                                            </p>
                                        </div>

                                        <span className="shrink-0 font-mono text-[10px] text-gray-300 dark:text-gray-600">
                                            {new Date(key.created_at).toLocaleDateString()}
                                        </span>

                                        <button
                                            onClick={() => setKeyToRevoke(key)}
                                            className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-gray-300 transition-colors hover:bg-red-50 hover:text-red-500 dark:text-gray-600 dark:hover:bg-red-950/30 dark:hover:text-red-400"
                                        >
                                            <Trash2 className="h-3.5 w-3.5" />
                                        </button>
                                    </div>
                                );
                            })}
                        </div>
                    )}
                </ComponentCard>

                {/* Usage hint */}
                <div className="mt-4 rounded-xl border border-black/5 bg-gray-50 p-4 dark:border-white/5 dark:bg-zinc-900">
                    <p className="mb-1.5 font-mono text-[10px] font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">
                        Usage
                    </p>
                    <code className="font-mono text-[11px] text-gray-500 dark:text-gray-400">
                        Authorization: Bearer {'<your-api-key>'}
                    </code>
                    <p className="mt-1 font-mono text-[11px] text-gray-400 dark:text-gray-500">
                        Test your connection: <span className="text-gray-600 dark:text-gray-300">GET /api/v1/public/health</span>
                    </p>
                </div>
            </div>

            {/* Revoke confirmation */}
            {!!keyToRevoke && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <div className="absolute inset-0 bg-slate-900/30 backdrop-blur-[2px]" onClick={() => setKeyToRevoke(null)} />
                    <div className="relative w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-xl dark:bg-zinc-900">
                        <div className="flex flex-col items-center p-6 text-center">
                            <div className="mb-4 rounded-xl bg-red-50 p-3 dark:bg-red-950/30">
                                <Trash2 className="h-6 w-6 text-red-500" />
                            </div>
                            <h3 className="mb-1 text-[15px] font-semibold text-gray-900 dark:text-gray-100">Revoke API Key</h3>
                            <p className="mb-5 text-[12px] text-gray-500 dark:text-gray-400">
                                Any integration using <span className="font-semibold text-gray-800 dark:text-gray-200">{keyToRevoke.name}</span> will
                                stop working immediately.
                            </p>
                            <div className="flex w-full items-center gap-3">
                                <button
                                    onClick={() => setKeyToRevoke(null)}
                                    className="flex h-9 flex-1 items-center justify-center rounded-lg border border-black/8 bg-stone-100 font-mono! text-[12px]! font-medium text-gray-600 transition-all hover:bg-stone-200 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-300"
                                >
                                    Cancel
                                </button>
                                <button
                                    onClick={() => handleRevoke(keyToRevoke)}
                                    className="flex h-9 flex-1 items-center justify-center rounded-lg bg-red-600 font-mono! text-[12px]! font-medium text-white transition-all hover:bg-red-700"
                                >
                                    Revoke Key
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            )}
        </AppLayout>
    );
}
