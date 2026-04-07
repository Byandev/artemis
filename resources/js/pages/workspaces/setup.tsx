import AuthLayout from '@/layouts/auth-layout';
import { Head, useForm } from '@inertiajs/react';
import { Loader2 } from 'lucide-react';
import { FormEventHandler } from 'react';

interface Props {
    userName: string;
}

export default function WorkspaceSetup({ userName }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        name: `${userName}'s Workspace`,
        description: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post('/workspaces/setup');
    };

    return (
        <AuthLayout title="Set up your workspace" description="Give your workspace a name to get started">
            <Head title="Create Your Workspace" />

            <form onSubmit={submit} className="flex flex-col gap-5">
                <div className="space-y-4">
                    {/* Workspace name */}
                    <div className="space-y-1.5">
                        <label htmlFor="name" className="block font-mono text-[10px] font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500">
                            Workspace name <span className="text-red-400">*</span>
                        </label>
                        <input
                            id="name"
                            type="text"
                            name="name"
                            required
                            autoFocus
                            value={data.name}
                            onChange={e => setData('name', e.target.value)}
                            placeholder="My Workspace"
                            className="h-10 w-full rounded-[10px] border border-black/8 bg-stone-50 px-3 font-mono! text-[13px]! text-gray-800 placeholder:text-gray-300 outline-none transition-all focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-100 dark:placeholder:text-gray-600 dark:focus:border-emerald-400"
                        />
                        {errors.name
                            ? <p className="font-mono text-[11px] text-red-500">{errors.name}</p>
                            : <p className="font-mono text-[10px] text-gray-400 dark:text-gray-500">You can always change this later.</p>
                        }
                    </div>

                    {/* Submit */}
                    <button
                        type="submit"
                        disabled={processing}
                        className="mt-1 flex h-10 w-full items-center justify-center gap-2 rounded-[10px] bg-emerald-600 font-mono! text-[13px]! font-semibold text-white transition-all hover:bg-emerald-700 disabled:opacity-50"
                    >
                        {processing && <Loader2 className="h-3.5 w-3.5 animate-spin" />}
                        {processing ? 'Creating…' : 'Create Workspace'}
                    </button>
                </div>
            </form>
        </AuthLayout>
    );
}
