import RegisteredUserController from '@/actions/App/Http/Controllers/Auth/RegisteredUserController';
import TextLink from '@/components/text-link';
import AuthLayout from '@/layouts/auth-layout';
import { login } from '@/routes';
import { Role } from '@/types/models/Role';
import { Form, Head, usePage } from '@inertiajs/react';
import { Info, Loader2 } from 'lucide-react';

interface WorkspaceInvitation {
    id: number;
    workspace_id: number;
    email: string;
    role: Role;
    workspace: {
        name: string;
        slug: string;
    };
}

interface RegisterProps {
    invitation?: WorkspaceInvitation | null;
    invitationToken?: string | null;
    [key: string]: unknown;
}

export default function Register() {
    const { invitation, invitationToken } = usePage<RegisterProps>().props;

    return (
        <AuthLayout
            title="Create an account"
            description={invitation ? `Join ${invitation.workspace.name}` : 'Enter your details to get started'}
        >
            <Head title="Register" />

            {invitation && (
                <div className="mb-5 flex items-start gap-2.5 rounded-xl border border-brand-500/20 bg-brand-500/5 px-3.5 py-3">
                    <Info className="mt-0.5 h-3.5 w-3.5 shrink-0 text-brand-500" />
                    <p className="font-mono text-[11px] leading-relaxed text-gray-600 dark:text-gray-400">
                        You've been invited to join{' '}
                        <span className="font-semibold text-gray-800 dark:text-gray-200">{invitation.workspace.name}</span>{' '}
                        as a <span className="font-semibold text-gray-800 dark:text-gray-200">{invitation.role?.name}</span>.
                        Create your account to accept.
                    </p>
                </div>
            )}

            <Form
                {...RegisteredUserController.store.form()}
                resetOnSuccess={['password', 'password_confirmation']}
                disableWhileProcessing
                className="flex flex-col gap-5"
            >
                {({ processing, errors }) => (
                    <>
                        {invitationToken && (
                            <input type="hidden" name="invitation" value={invitationToken} />
                        )}

                        <div className="space-y-4">
                            {/* Full name */}
                            <div className="space-y-1.5">
                                <label htmlFor="name" className="block font-mono text-[10px] font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500">
                                    Full name <span className="text-red-400">*</span>
                                </label>
                                <input
                                    id="name"
                                    type="text"
                                    name="name"
                                    required
                                    autoFocus
                                    tabIndex={1}
                                    autoComplete="name"
                                    placeholder="Juan dela Cruz"
                                    className="h-10 w-full rounded-[10px] border border-black/8 bg-stone-50 px-3 font-mono! text-[13px]! text-gray-800 placeholder:text-gray-300 outline-none transition-all focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-100 dark:placeholder:text-gray-600 dark:focus:border-emerald-400"
                                />
                                {errors.name && <p className="font-mono text-[11px] text-red-500">{errors.name}</p>}
                            </div>

                            {/* Email */}
                            <div className="space-y-1.5">
                                <label htmlFor="email" className="block font-mono text-[10px] font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500">
                                    Email address <span className="text-red-400">*</span>
                                </label>
                                <input
                                    id="email"
                                    type="email"
                                    name="email"
                                    required
                                    tabIndex={2}
                                    autoComplete="email"
                                    placeholder="email@example.com"
                                    defaultValue={invitation?.email || ''}
                                    readOnly={!!invitation}
                                    className="h-10 w-full rounded-[10px] border border-black/8 bg-stone-50 px-3 font-mono! text-[13px]! text-gray-800 placeholder:text-gray-300 outline-none transition-all focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 read-only:opacity-60 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-100 dark:placeholder:text-gray-600 dark:focus:border-emerald-400"
                                />
                                {errors.email && <p className="font-mono text-[11px] text-red-500">{errors.email}</p>}
                            </div>

                            {/* Password */}
                            <div className="space-y-1.5">
                                <label htmlFor="password" className="block font-mono text-[10px] font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500">
                                    Password <span className="text-red-400">*</span>
                                </label>
                                <input
                                    id="password"
                                    type="password"
                                    name="password"
                                    required
                                    tabIndex={3}
                                    autoComplete="new-password"
                                    placeholder="At least 8 characters"
                                    className="h-10 w-full rounded-[10px] border border-black/8 bg-stone-50 px-3 font-mono! text-[13px]! text-gray-800 placeholder:text-gray-300 outline-none transition-all focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-100 dark:placeholder:text-gray-600 dark:focus:border-emerald-400"
                                />
                                {errors.password && <p className="font-mono text-[11px] text-red-500">{errors.password}</p>}
                            </div>

                            {/* Confirm password */}
                            <div className="space-y-1.5">
                                <label htmlFor="password_confirmation" className="block font-mono text-[10px] font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500">
                                    Confirm password <span className="text-red-400">*</span>
                                </label>
                                <input
                                    id="password_confirmation"
                                    type="password"
                                    name="password_confirmation"
                                    required
                                    tabIndex={4}
                                    autoComplete="new-password"
                                    placeholder="Re-enter your password"
                                    className="h-10 w-full rounded-[10px] border border-black/8 bg-stone-50 px-3 font-mono! text-[13px]! text-gray-800 placeholder:text-gray-300 outline-none transition-all focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-100 dark:placeholder:text-gray-600 dark:focus:border-emerald-400"
                                />
                                {errors.password_confirmation && <p className="font-mono text-[11px] text-red-500">{errors.password_confirmation}</p>}
                            </div>

                            {/* Submit */}
                            <button
                                type="submit"
                                tabIndex={5}
                                disabled={processing}
                                className="mt-1 flex h-10 w-full items-center justify-center gap-2 rounded-[10px] bg-emerald-600 font-mono! text-[13px]! font-semibold text-white transition-all hover:bg-emerald-700 disabled:opacity-50"
                            >
                                {processing && <Loader2 className="h-3.5 w-3.5 animate-spin" />}
                                Create account
                            </button>
                        </div>

                        <p className="text-center font-mono text-[11px] text-gray-400 dark:text-gray-500">
                            Already have an account?{' '}
                            <TextLink
                                href={invitationToken ? login({ query: { invitation: invitationToken } }).url : login().url}
                                className="font-mono! text-[11px]!"
                                tabIndex={6}
                            >
                                Log in
                            </TextLink>
                        </p>
                    </>
                )}
            </Form>
        </AuthLayout>
    );
}
