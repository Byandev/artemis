import { dashboard, login, register } from '@/routes';
import { type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';

const features = [
    {
        icon: (
            <svg className="h-5 w-5 text-brand-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M2.25 18L9 11.25l4.306 4.307a11.95 11.95 0 015.814-5.519l2.74-1.22m0 0l-5.94-2.28m5.94 2.28l-2.28 5.941" />
            </svg>
        ),
        title: 'Sales Analytics',
        description: 'Track revenue, orders, AOV, and repeat purchase rate across all pages and shops.',
    },
    {
        icon: (
            <svg className="h-5 w-5 text-brand-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12" />
            </svg>
        ),
        title: 'Delivery Analytics',
        description: 'Monitor delivery outcomes, attempt counts, and customer RTS scores in real time.',
    },
    {
        icon: (
            <svg className="h-5 w-5 text-brand-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3" />
            </svg>
        ),
        title: 'RTS Analytics',
        description: 'Deep-dive into return-to-sender rates by page, shop, user, and city heatmap.',
    },
    {
        icon: (
            <svg className="h-5 w-5 text-brand-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" />
                <path strokeLinecap="round" strokeLinejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" />
            </svg>
        ),
        title: 'Parcel Journey',
        description: 'Per-order timeline from confirmation to final delivery — every status, every rider.',
    },
    {
        icon: (
            <svg className="h-5 w-5 text-brand-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
        ),
        title: 'Operations Insights',
        description: 'Fulfillment lead times — confirmed to shipped, shipped to delivered, and more.',
    },
    {
        icon: (
            <svg className="h-5 w-5 text-brand-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z" />
            </svg>
        ),
        title: 'Role-based Access',
        description: 'Multi-workspace support with team permissions and granular access control.',
    },
];

export default function Welcome() {
    const { auth } = usePage<SharedData>().props;

    return (
        <>
            <Head title="Artemis" />
            <div className="relative min-h-screen bg-white dark:bg-zinc-950 text-gray-900 dark:text-gray-100 overflow-hidden">

                {/* Background decorations */}
                <div className="pointer-events-none absolute -top-40 -right-40 h-[32rem] w-[32rem] rounded-full bg-brand-500/8 blur-3xl dark:bg-brand-500/6" />
                <div className="pointer-events-none absolute bottom-0 -left-40 h-96 w-96 rounded-full bg-brand-500/5 blur-3xl" />
                <div className="pointer-events-none absolute inset-0 bg-[linear-gradient(rgba(0,0,0,0.03)_1px,transparent_1px),linear-gradient(90deg,rgba(0,0,0,0.03)_1px,transparent_1px)] dark:bg-[linear-gradient(rgba(255,255,255,0.02)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,0.02)_1px,transparent_1px)] bg-[size:48px_48px]" />

                {/* Header */}
                <header className="relative z-10 flex items-center justify-between px-6 py-5 md:px-12">
                    <div className="flex items-center gap-2.5">
                        <img src="/img/logo/artemis.png" alt="Artemis" className="h-8 w-8 object-contain" />
                        <span className="text-[16px] font-semibold tracking-tight">Artemis</span>
                    </div>

                    <nav className="flex items-center gap-3">
                        {auth.user ? (
                            <Link
                                href={dashboard()}
                                className="inline-flex h-9 items-center rounded-lg bg-brand-500 px-5 text-[13px] font-medium text-white transition-colors hover:bg-brand-600"
                            >
                                Dashboard
                            </Link>
                        ) : (
                            <>
                                <Link
                                    href={login()}
                                    className="inline-flex h-9 items-center rounded-lg px-4 text-[13px] font-medium text-gray-600 dark:text-gray-300 transition-colors hover:text-gray-900 dark:hover:text-white"
                                >
                                    Log in
                                </Link>
                                <Link
                                    href={register()}
                                    className="inline-flex h-9 items-center rounded-lg bg-brand-500! px-5 text-[13px] font-medium text-white transition-colors hover:bg-brand-600"
                                >
                                    Get started
                                </Link>
                            </>
                        )}
                    </nav>
                </header>

                {/* Hero */}
                <div className="relative z-10 px-6 pb-8 pt-16 md:pt-20">
                    <div className="mx-auto max-w-2xl text-center">

                        {/* Badge */}
                        <div className="mb-6 inline-flex items-center gap-2 rounded-full border border-brand-200 dark:border-brand-500/20 bg-brand-50 dark:bg-brand-500/10 px-3.5 py-1.5">
                            <span className="h-1.5 w-1.5 rounded-full bg-brand-500" />
                            <span className="font-mono text-[11px] font-semibold tracking-widest text-brand-700 dark:text-brand-400 uppercase">
                                Now available
                            </span>
                        </div>

                        {/* Logo + Title */}
                        <div className="mb-5 flex flex-col items-center gap-4">
                            <img
                                src="/img/logo/artemis.png"
                                alt="Artemis"
                                className="h-20 w-20 object-contain"
                            />
                            <h1 className="text-[52px] font-bold tracking-tight leading-none md:text-[64px]">
                                Artemis
                            </h1>
                        </div>

                        {/* Subtitle */}
                        <p className="mb-2 text-[17px] leading-relaxed text-gray-600 dark:text-gray-400 md:text-[19px]">
                            From order to delivery — every metric that matters.
                        </p>
                        <p className="mb-10 text-[13px] text-gray-500 dark:text-gray-400">
                            Sales · Operations · Delivery · RTS · Parcel Journey
                        </p>

                        {/* CTA */}
                        {auth.user ? (
                            <Link
                                href={dashboard()}
                                className="inline-flex h-11 items-center rounded-xl bg-brand-500 px-8 text-[14px] font-medium text-white shadow-lg shadow-brand-500/20 transition-all hover:bg-brand-600"
                            >
                                Go to Dashboard
                            </Link>
                        ) : (
                            <div className="flex flex-col items-center justify-center gap-3 sm:flex-row">
                                <Link
                                    href={register()}
                                    className="inline-flex h-11 items-center rounded-xl bg-brand-500! px-8 text-[14px] font-medium text-white shadow-lg shadow-brand-500/20 transition-all hover:bg-brand-600"
                                >
                                    Get started — it's free
                                </Link>
                                <Link
                                    href={login()}
                                    className="inline-flex h-11 items-center rounded-xl border border-gray-200 dark:border-white/8 bg-white dark:bg-zinc-800 px-8 text-[14px] font-medium text-gray-700 dark:text-gray-300 transition-all hover:border-gray-300 dark:hover:border-white/15"
                                >
                                    Sign in
                                </Link>
                            </div>
                        )}
                    </div>

                    {/* Feature grid */}
                    <div className="mx-auto mt-20 max-w-4xl">
                        <p className="mb-8 text-center text-[11px] font-semibold tracking-widest text-gray-400 dark:text-gray-500 uppercase">
                            Everything you need to run e-commerce operations
                        </p>
                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            {features.map((f) => (
                                <div
                                    key={f.title}
                                    className="flex gap-3.5 rounded-2xl border border-gray-200 dark:border-white/6 bg-white dark:bg-zinc-900/60 p-4"
                                >
                                    <div className="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-brand-50 dark:bg-brand-500/10">
                                        {f.icon}
                                    </div>
                                    <div>
                                        <p className="text-[13px] font-semibold text-gray-900 dark:text-gray-100">{f.title}</p>
                                        <p className="mt-0.5 text-[12px] leading-relaxed text-gray-500 dark:text-gray-400">{f.description}</p>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                </div>

                {/* Footer */}
                <footer className="relative z-10 py-8 text-center">
                    <p className="text-[12px] text-gray-400 dark:text-gray-600">
                        © {new Date().getFullYear()} Artemis. All rights reserved.
                    </p>
                </footer>
            </div>
        </>
    );
}
