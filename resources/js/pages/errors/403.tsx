import AuthCardLayout from '@/layouts/auth/auth-card-layout';
import { dashboard, home } from '@/routes';
import { type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';

const nextSteps = [
    'If you believe this is a mistake, contact your administrator or support.',
    'Try signing in with an account that has access.',
    'Go back to the previous page or return to the dashboard.',
];

export default function AccessDenied() {
    const { auth } = usePage<SharedData>().props;

    const primaryHref = auth.user ? dashboard() : home();
    const primaryLabel = auth.user ? 'Go to dashboard' : 'Go home';

    return (
        <>
            <Head title="Access denied" />

            <AuthCardLayout
                title="Access denied"
                description="You do not have permission to view this page."
            >
                <div className="space-y-6">
                    <div className="rounded-2xl border border-rose-200 bg-rose-50/80 px-4 py-3.5 text-rose-900 dark:border-rose-500/20 dark:bg-rose-500/10 dark:text-rose-100">
                        <p className="font-mono text-[11px] font-semibold uppercase tracking-[0.18em] text-rose-700 dark:text-rose-300">
                            Access denied
                        </p>
                        <p className="mt-2 text-[14px] leading-relaxed text-rose-800 dark:text-rose-100/90">
                            You do not have permission to view this page.
                        </p>
                    </div>

                    <div className="space-y-3">
                        <p className="font-mono text-[10px] font-semibold uppercase tracking-[0.18em] text-gray-400 dark:text-gray-500">
                            Next steps
                        </p>
                        <ul className="space-y-3 text-[14px] leading-relaxed text-gray-600 dark:text-gray-300">
                            {nextSteps.map((step) => (
                                <li key={step} className="flex gap-3">
                                    <span className="mt-1 inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-emerald-500/10 text-[11px] font-semibold text-emerald-600 dark:bg-emerald-400/10 dark:text-emerald-300">
                                        •
                                    </span>
                                    <span>{step}</span>
                                </li>
                            ))}
                        </ul>
                    </div>

                    <div className="flex flex-col gap-3 sm:flex-row">
                        <button
                            type="button"
                            onClick={() => window.history.back()}
                            className="inline-flex h-10 flex-1 items-center justify-center rounded-xl border border-gray-200 bg-white px-4 text-[13px] font-semibold text-gray-700 transition-colors hover:bg-gray-50 dark:border-white/10 dark:bg-zinc-900 dark:text-gray-200 dark:hover:bg-zinc-800"
                        >
                            Back
                        </button>

                        <Link
                            href={primaryHref.url}
                            className="inline-flex h-10 flex-1 items-center justify-center rounded-xl bg-brand-500 px-4 text-[13px] font-semibold text-white shadow-sm shadow-brand-500/20 transition-all hover:bg-brand-600"
                        >
                            {primaryLabel}
                        </Link>
                    </div>
                </div>
            </AuthCardLayout>
        </>
    );
}
