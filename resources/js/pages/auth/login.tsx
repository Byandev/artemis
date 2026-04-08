import AuthenticatedSessionController from '@/actions/App/Http/Controllers/Auth/AuthenticatedSessionController';
import TextLink from '@/components/text-link';
import AuthLayout from '@/layouts/auth-layout';
import { register } from '@/routes';
import { request } from '@/routes/password';
import { Form, Head, usePage } from '@inertiajs/react';
import { Info, Loader2 } from 'lucide-react';
import { WorkspaceInvitation } from '@/types/models/WorkspaceInvitation';

interface LoginProps {
    status?: string;
    canResetPassword: boolean;
    invitation?: WorkspaceInvitation | null;
    invitationToken?: string | null;
    [key: string]: unknown;
}

export default function Login({ status, canResetPassword }: LoginProps) {
    const { invitation, invitationToken } = usePage<LoginProps>().props;

    return (
        <AuthLayout
            title="Welcome back"
            description={invitation ? `Login to join ${invitation.workspace.name}` : 'Enter your credentials to access your workspace'}
        >
            <Head title="Log in" />

            {invitation && (
                <div className="mb-5 flex items-start gap-2.5 rounded-xl border border-brand-500/20 bg-brand-500/5 px-3.5 py-3">
                    <Info className="mt-0.5 h-3.5 w-3.5 shrink-0 text-brand-500" />
                    <p className="font-mono text-[11px] leading-relaxed text-gray-600 dark:text-gray-400">
                        You've been invited to join <span className="font-semibold text-gray-800 dark:text-gray-200">{invitation.workspace.name}</span>. Login to accept.
                    </p>
                </div>
            )}

            {status && (
                <div className="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-3.5 py-3 dark:border-emerald-900/40 dark:bg-emerald-950/20">
                    <p className="font-mono text-[11px] text-emerald-700 dark:text-emerald-400">{status}</p>
                </div>
            )}

            <Form
                {...AuthenticatedSessionController.store()}
                resetOnSuccess={['password']}
                className="flex flex-col gap-5"
            >
                {({ processing, errors }) => (
                    <>
                        {invitationToken && (
                            <input type="hidden" name="invitation" value={invitationToken} />
                        )}

                        <div className="space-y-4">
                            {/* Email */}
                            <div className="space-y-1.5">
                                <label htmlFor="email" className="block font-mono text-[10px] font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500">
                                    Email address
                                </label>
                                <input
                                    id="email"
                                    type="email"
                                    name="email"
                                    required
                                    autoFocus
                                    tabIndex={1}
                                    autoComplete="email"
                                    placeholder="email@example.com"
                                    defaultValue={invitation?.email || ''}
                                    readOnly={!!invitation}
                                    className="h-10 w-full rounded-[10px] border border-black/8 bg-stone-50 px-3 font-mono! text-[13px]! text-gray-800 placeholder:text-gray-300 outline-none transition-all focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 read-only:opacity-60 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-100 dark:placeholder:text-gray-600 dark:focus:border-emerald-400"
                                />
                                {invitation && (
                                    <p className="font-mono text-[10px] text-gray-400 dark:text-gray-500">This email matches your invitation.</p>
                                )}
                                {errors.email && <p className="font-mono text-[11px] text-red-500">{errors.email}</p>}
                            </div>

                            {/* Password */}
                            <div className="space-y-1.5">
                                <div className="flex items-center justify-between">
                                    <label htmlFor="password" className="block font-mono text-[10px] font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500">
                                        Password
                                    </label>
                                    {canResetPassword && (
                                        <TextLink href={request()} className="font-mono! text-[10px]!" tabIndex={5}>
                                            Forgot password?
                                        </TextLink>
                                    )}
                                </div>
                                <input
                                    id="password"
                                    type="password"
                                    name="password"
                                    required
                                    tabIndex={2}
                                    autoComplete="current-password"
                                    placeholder="••••••••"
                                    className="h-10 w-full rounded-[10px] border border-black/8 bg-stone-50 px-3 font-mono! text-[13px]! text-gray-800 placeholder:text-gray-300 outline-none transition-all focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-100 dark:placeholder:text-gray-600 dark:focus:border-emerald-400"
                                />
                                {errors.password && <p className="font-mono text-[11px] text-red-500">{errors.password}</p>}
                            </div>

                            {/* Remember me */}
                            <label className="flex cursor-pointer items-center gap-2.5">
                                <input
                                    type="checkbox"
                                    name="remember"
                                    tabIndex={3}
                                    className="h-3.5 w-3.5 rounded border-black/20 accent-emerald-600 dark:border-white/20"
                                />
                                <span className="font-mono text-[11px] text-gray-500 dark:text-gray-400">Remember me</span>
                            </label>

                            {/* Submit */}
                            <button
                                type="submit"
                                tabIndex={4}
                                disabled={processing}
                                className="mt-1 flex h-10 w-full items-center justify-center gap-2 rounded-[10px] bg-emerald-600 font-mono! text-[13px]! font-semibold text-white transition-all hover:bg-emerald-700 disabled:opacity-50"
                            >
                                {processing && <Loader2 className="h-3.5 w-3.5 animate-spin" />}
                                Log in
                            </button>
                        </div>

                        <p className="text-center font-mono text-[11px] text-gray-400 dark:text-gray-500">
                            Don't have an account?{' '}
                            <TextLink
                                href={invitationToken ? register({ query: { invitation: invitationToken } }).url : register().url}
                                className="font-mono! text-[11px]!"
                                tabIndex={5}
                            >
                                Sign up
                            </TextLink>
                        </p>
                    </>
                )}
            </Form>
        </AuthLayout>
    );
}
