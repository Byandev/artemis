import AppearanceToggleDropdown from '@/components/appearance-dropdown';
import { dashboard, login, register } from '@/routes';
import { type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { useState } from 'react';

type Tier = {
    name: string;
    price: string;
    gencysPrice: string;
    period: string;
    orders: string;
    pages: string;
    ret: string;
    pj: string;
    sup: string;
    featured: boolean;
    note: string;
    gencysNote: string;
    cta: string;
};

const tiers: Tier[] = [
    {
        name: 'Free Trial',
        price: '₱0',
        gencysPrice: '₱0',
        period: '30 days',
        orders: '10,000',
        pages: '1',
        ret: '6 mo',
        pj: '✅ Included',
        sup: 'Chat',
        featured: false,
        note: '',
        gencysNote: '',
        cta: 'Start free',
    },
    {
        name: 'Solo',
        price: '₱4,499',
        gencysPrice: '₱3,999',
        period: '/mo',
        orders: '3,000',
        pages: '5',
        ret: '3 mo',
        pj: '✅ Included',
        sup: 'Priority',
        featured: false,
        note: '',
        gencysNote: '',
        cta: 'Get Solo',
    },
    {
        name: 'Pro',
        price: '₱8,999',
        gencysPrice: '₱7,999',
        period: '/mo',
        orders: '10,000',
        pages: '25',
        ret: '6 mo',
        pj: '✅ Included',
        sup: 'Priority',
        featured: true,
        note: '',
        gencysNote: '',
        cta: 'Get Pro',
    },
    {
        name: 'Business',
        price: '₱13,499',
        gencysPrice: '₱11,999',
        period: '/mo',
        orders: '20,000',
        pages: '100',
        ret: '12 mo',
        pj: '✅ Included',
        sup: 'Dedicated',
        featured: false,
        note: '+₱4,500 per additional 10,000 orders/mo',
        gencysNote: '+₱3,000 per additional 10,000 orders/mo',
        cta: 'Get Business',
    },
];

export default function Pricing() {
    const { auth } = usePage<SharedData>().props;
    const [gencysPartner, setGencysPartner] = useState(false);

    return (
        <>
            <Head title="Pricing — Artemis" />
            <div className="relative min-h-screen overflow-hidden bg-white text-gray-900 dark:bg-zinc-950 dark:text-gray-100">
                {/* Background decorations */}
                <div className="pointer-events-none absolute -top-40 left-1/2 h-[500px] w-[900px] -translate-x-1/2 bg-[radial-gradient(ellipse,rgba(16,211,161,0.12),transparent_70%)]" />
                <div className="pointer-events-none absolute inset-0 bg-[linear-gradient(rgba(0,0,0,0.03)_1px,transparent_1px),linear-gradient(90deg,rgba(0,0,0,0.03)_1px,transparent_1px)] bg-[size:60px_60px] dark:bg-[linear-gradient(rgba(255,255,255,0.015)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,0.015)_1px,transparent_1px)]" />

                {/* Nav */}
                <nav className="sticky top-0 z-50 border-b border-gray-200/80 bg-white/80 backdrop-blur-2xl dark:border-white/6 dark:bg-zinc-950/80">
                    <div className="mx-auto flex max-w-[1200px] items-center justify-between px-5 py-4 md:px-10">
                        <Link href="/" className="flex items-center gap-3">
                            <img
                                src="/img/logo/artemis.png"
                                alt="Artemis"
                                className="h-9 w-9 object-contain"
                            />
                            <span className="text-[22px] font-semibold tracking-tight">
                                Artemis
                            </span>
                        </Link>
                        <div className="flex items-center gap-6 text-sm">
                            <Link
                                href="/"
                                className="hidden text-[13px] text-gray-500 transition-colors hover:text-brand-500 md:block dark:text-gray-400 dark:hover:text-brand-400"
                            >
                                Home
                            </Link>
                            <Link
                                href="/rts-calculator"
                                className="hidden text-[13px] text-gray-500 transition-colors hover:text-brand-500 md:block dark:text-gray-400 dark:hover:text-brand-400"
                            >
                                RTS Calculator
                            </Link>
                            <div className="hidden h-4 w-px bg-gray-200 md:block dark:bg-white/10" />
                            <AppearanceToggleDropdown />
                            {auth.user ? (
                                <Link
                                    href={dashboard()}
                                    className="inline-flex h-9 items-center rounded-lg bg-brand-500! px-5 text-[13px] font-semibold text-white shadow-sm shadow-brand-500/20 transition-all hover:bg-brand-600 hover:shadow-md hover:shadow-brand-500/25"
                                >
                                    Dashboard
                                </Link>
                            ) : (
                                <>
                                    <Link
                                        href={login()}
                                        className="hidden text-[13px] text-gray-500 transition-colors hover:text-brand-500 md:block dark:text-gray-400 dark:hover:text-brand-400"
                                    >
                                        Log in
                                    </Link>
                                    <Link
                                        href={register()}
                                        className="inline-flex h-9 items-center rounded-lg bg-brand-500! px-5 text-[13px] font-semibold text-white shadow-sm shadow-brand-500/20 transition-all hover:bg-brand-600 hover:shadow-md hover:shadow-brand-500/25"
                                    >
                                        Start free
                                    </Link>
                                </>
                            )}
                        </div>
                    </div>
                </nav>

                {/* Hero */}
                <section className="relative px-5 pt-20 pb-10 text-center md:px-10 md:pt-24">
                    <div className="mx-auto max-w-[1200px]">
                        <p className="mb-5 font-mono text-[11px] tracking-[0.2em] text-brand-500 uppercase">
                            — Pricing
                        </p>
                        <h1 className="mb-6 text-4xl font-bold tracking-tight md:text-5xl lg:text-6xl">
                            Simple pricing.{' '}
                            <span className="text-brand-500 italic">
                                Start free.
                            </span>
                        </h1>
                        <p className="mx-auto max-w-2xl text-lg leading-relaxed text-gray-500 dark:text-gray-400">
                            Pick the plan that fits your order volume.{' '}
                            <span className="font-semibold text-gray-700 dark:text-gray-300">
                                Call Logs Sync Mobile App bundled into every
                                plan
                            </span>{' '}
                            — no add-ons, no usage fees, no surprises.
                        </p>

                        {/* Gencys Partner toggle */}
                        <div className="mt-10 flex items-center justify-center gap-3">
                            <button
                                type="button"
                                role="switch"
                                aria-checked={gencysPartner}
                                onClick={() => setGencysPartner((v) => !v)}
                                className={`relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition-colors ${gencysPartner ? 'bg-brand-500' : 'bg-gray-200 dark:bg-white/10'}`}
                            >
                                <span
                                    className={`inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform ${gencysPartner ? 'translate-x-6' : 'translate-x-1'}`}
                                />
                            </button>
                            <span className="text-sm font-semibold text-gray-700 dark:text-gray-200">
                                Gencys Partner pricing
                            </span>
                            {gencysPartner && (
                                <span className="rounded-full border border-brand-200 bg-brand-50 px-2 py-0.5 font-mono text-[8px] font-bold tracking-[0.15em] text-brand-700 uppercase dark:border-brand-500/20 dark:bg-brand-500/10 dark:text-brand-400">
                                    Applied
                                </span>
                            )}
                        </div>
                    </div>
                </section>

                {/* Pricing cards */}
                <section className="px-5 pb-16 md:px-10">
                    <div className="mx-auto grid max-w-[1200px] grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-4">
                        {tiers.map((t) => (
                            <div
                                key={t.name}
                                className={`relative flex flex-col overflow-hidden rounded-2xl border p-6 ${t.featured ? 'border-brand-500 bg-gradient-to-b from-brand-50 via-white to-white shadow-2xl ring-1 shadow-brand-500/15 ring-brand-500/20 dark:border-brand-400 dark:from-brand-500/10 dark:via-zinc-900 dark:to-zinc-900 dark:shadow-brand-500/10 dark:ring-brand-400/10' : 'border-gray-200/80 bg-gradient-to-b from-white to-gray-50/50 shadow-lg shadow-gray-200/30 dark:border-white/8 dark:from-zinc-900 dark:to-zinc-900/80 dark:shadow-black/20'}`}
                            >
                                {t.featured && (
                                    <div className="pointer-events-none absolute -top-10 -right-10 h-32 w-32 rounded-full bg-brand-500/10 blur-2xl" />
                                )}
                                {t.featured && (
                                    <span className="relative mb-3 inline-flex self-start rounded-full bg-gradient-to-r from-brand-500 to-brand-600 px-2.5 py-0.5 font-mono text-[8px] font-bold tracking-[0.2em] text-white uppercase shadow-sm shadow-brand-500/25">
                                        Recommended
                                    </span>
                                )}
                                <p
                                    className={`font-mono text-[9px] tracking-[0.2em] uppercase ${t.featured ? 'text-brand-600 dark:text-brand-400' : 'text-gray-400 dark:text-gray-500'}`}
                                >
                                    {t.name}
                                </p>
                                <div className="mt-2 mb-5 flex items-baseline gap-1.5">
                                    <span
                                        className={`text-3xl font-bold tracking-tight ${t.featured ? 'text-brand-600 dark:text-brand-400' : ''}`}
                                    >
                                        {gencysPartner ? t.gencysPrice : t.price}
                                    </span>
                                    {gencysPartner &&
                                        t.gencysPrice !== t.price && (
                                            <span className="text-xs text-gray-400 line-through">
                                                {t.price}
                                            </span>
                                        )}
                                    <span className="text-xs text-gray-400">
                                        {t.period}
                                    </span>
                                </div>
                                <div className="flex-1 space-y-2.5 border-t border-gray-100 pt-5 text-[11px] dark:border-white/5">
                                    {[
                                        ['Orders', `${t.orders}/mo`],
                                        ['Pages', t.pages],
                                        ['Data retention', t.ret],
                                        ['Call Logs Sync Mobile App', t.pj],
                                        ['Support', t.sup],
                                    ].map(([k, v]) => (
                                        <div
                                            key={k}
                                            className="flex justify-between"
                                        >
                                            <span className="text-gray-400">
                                                {k}
                                            </span>
                                            <span
                                                className={`font-semibold ${t.featured ? 'text-gray-900 dark:text-gray-100' : 'text-gray-700 dark:text-gray-200'}`}
                                            >
                                                {v}
                                            </span>
                                        </div>
                                    ))}
                                </div>
                                {(gencysPartner ? t.gencysNote : t.note) && (
                                    <p className="mt-3 border-t border-gray-100 pt-3 text-[10px] leading-relaxed font-medium text-brand-600 dark:border-white/5 dark:text-brand-400">
                                        {gencysPartner
                                            ? t.gencysNote
                                            : t.note}
                                    </p>
                                )}
                                <Link
                                    href={auth.user ? dashboard() : register()}
                                    className={`mt-5 inline-flex h-10 items-center justify-center rounded-lg px-5 text-[13px] font-semibold transition-all ${t.featured ? 'bg-brand-500! text-white shadow-sm shadow-brand-500/20 hover:bg-brand-600 hover:shadow-md hover:shadow-brand-500/25' : 'border border-gray-200 bg-white text-gray-700 hover:border-brand-300 hover:text-brand-600 dark:border-white/10 dark:bg-zinc-900 dark:text-gray-300'}`}
                                >
                                    {t.cta}
                                </Link>
                            </div>
                        ))}
                    </div>

                    {/* Enterprise callout */}
                    <div className="mx-auto mt-4 flex max-w-[1200px] flex-col items-center justify-between gap-4 rounded-2xl border border-gray-200/80 bg-white p-6 sm:flex-row dark:border-white/8 dark:bg-zinc-900/80">
                        <div>
                            <p className="font-mono text-[9px] tracking-[0.2em] text-gray-400 uppercase dark:text-gray-500">
                                Enterprise
                            </p>
                            <p className="mt-1 text-lg font-bold tracking-tight">
                                Unlimited orders & pages, dedicated support
                            </p>
                            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                Custom pricing negotiated per business — 24-month
                                data retention, full analytics.
                            </p>
                        </div>
                        <Link
                            href="/contact"
                            className="inline-flex h-10 shrink-0 items-center justify-center rounded-lg border border-gray-200 bg-white px-6 text-[13px] font-semibold text-gray-700 transition-all hover:border-brand-300 hover:text-brand-600 dark:border-white/10 dark:bg-zinc-900 dark:text-gray-300"
                        >
                            Talk to sales
                        </Link>
                    </div>
                </section>

                {/* Footer */}
                <footer className="border-t border-gray-200 dark:border-white/8">
                    <div className="mx-auto flex max-w-[1200px] flex-col items-center justify-between gap-4 px-5 py-8 text-center sm:flex-row sm:text-left md:px-10">
                        <p className="text-[13px] text-gray-400">
                            © Artemis. All prices in PHP, billed monthly.
                        </p>
                        <div className="flex items-center gap-6 text-[13px]">
                            <Link
                                href="/"
                                className="text-gray-500 transition-colors hover:text-brand-500 dark:text-gray-400"
                            >
                                Home
                            </Link>
                            <Link
                                href="/rts-calculator"
                                className="text-gray-500 transition-colors hover:text-brand-500 dark:text-gray-400"
                            >
                                RTS Calculator
                            </Link>
                            <Link
                                href="/contact"
                                className="text-gray-500 transition-colors hover:text-brand-500 dark:text-gray-400"
                            >
                                Contact
                            </Link>
                        </div>
                    </div>
                </footer>
            </div>
        </>
    );
}
