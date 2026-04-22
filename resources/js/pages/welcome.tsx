import AppearanceToggleDropdown from '@/components/appearance-dropdown';
import { dashboard, login, register } from '@/routes';
import { type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { useState } from 'react';

const features = [
    {
        icon: (
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.8} strokeLinecap="round" strokeLinejoin="round">
                <path d="M3 17l6-6 4 4 8-8" />
                <path d="M14 7h7v7" />
            </svg>
        ),
        title: 'Sales Analytics',
        description: 'Track revenue, orders, AOV, and repeat purchase rate across all your pages and shops — in one unified view.',
    },
    {
        icon: (
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.8} strokeLinecap="round" strokeLinejoin="round">
                <rect x="1" y="3" width="15" height="13" rx="1" />
                <path d="M16 8h4l3 3v5h-7V8z" />
                <circle cx="5.5" cy="18.5" r="2.5" />
                <circle cx="18.5" cy="18.5" r="2.5" />
            </svg>
        ),
        title: 'Delivery Analytics',
        description: 'Monitor delivery outcomes, attempt counts, and customer RTS scores in real time. Spot risky buyers before you ship.',
    },
    {
        icon: (
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.8} strokeLinecap="round" strokeLinejoin="round">
                <path d="M9 14L4 9l5-5" />
                <path d="M4 9h11a5 5 0 015 5v2" />
            </svg>
        ),
        title: 'RTS Analytics',
        description: 'Deep-dive into return-to-sender rates by page, shop, user, and city heatmap. Find the exact source of your losses.',
    },
    {
        icon: (
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.8} strokeLinecap="round" strokeLinejoin="round">
                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z" />
                <circle cx="12" cy="10" r="3" />
            </svg>
        ),
        title: 'Parcel Journey',
        description: 'Per-order timeline from confirmation to final delivery — every status, every rider, every customer notification logged.',
    },
    {
        icon: (
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.8} strokeLinecap="round" strokeLinejoin="round">
                <circle cx="12" cy="12" r="10" />
                <path d="M12 6v6l4 2" />
            </svg>
        ),
        title: 'Operations Insights',
        description: 'Fulfillment lead times — confirmed to shipped, shipped to delivered, and every bottleneck in between.',
    },
    {
        icon: (
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.8} strokeLinecap="round" strokeLinejoin="round">
                <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2" />
                <circle cx="9" cy="7" r="4" />
                <path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75" />
            </svg>
        ),
        title: 'Role-based Access',
        description: 'Multi-workspace support with team permissions and granular access control. Your CS team sees only what they need.',
    },
];

const steps = [
    { num: '01 / SIGN UP', title: 'Create your workspace', description: 'Enter your business name and a few basics. No credit card, no lengthy forms.' },
    { num: '02 / CONNECT', title: 'Connect Pancake', description: 'Link your Pancake POS page — we pull your orders, customers, and delivery data automatically.' },
    { num: '03 / SEE', title: 'Your first insight', description: 'Within minutes, see your real RTS rate, lost profit, and highest-risk zones.' },
    { num: '04 / ACT', title: 'Move the number', description: 'Turn on notifications, follow our recommendations, and watch RTS drop month over month.' },
];

const faqs = [
    {
        q: 'How does Artemis get my data?',
        a: 'We connect directly to your Pancake POS account. Just link your page and we pull your orders, customers, and delivery data automatically — no manual uploads needed.',
    },
    {
        q: 'Does Artemis really reduce RTS? By how much?',
        a: 'On average, our sellers see a 10% relative reduction in RTS within 90 days — but results vary by product, audience, and courier. We don\'t promise a specific endpoint. What we do is give you the visibility and tools to move the number in the right direction.',
    },
    {
        q: 'What if I don\'t know my profit margin?',
        a: 'Totally fine — most sellers don\'t track it formally. During onboarding we\'ll ask for your average monthly expenses and auto-compute margin from your sales data. Or you can pick your product category and we\'ll use industry averages.',
    },
    {
        q: 'Do I need anything other than Pancake POS?',
        a: 'Nope. If you\'re running your COD business on Pancake, that\'s all we need. We pull orders, delivery statuses, and customer data directly from your Pancake pages.',
    },
    {
        q: 'Is my customer data safe?',
        a: 'Yes. We\'re Data Privacy Act (RA 10173) compliant, store data encrypted at rest, and never share your numbers with anyone else. Your dashboard is your dashboard.',
    },
    {
        q: 'What do I get with the free trial?',
        a: '14-day free trial — connect 1 Pancake page and get 1 month of order and delivery data, plus Parcel Journey tracking via chat. No credit card required.',
    },
];

function FaqItem({ q, a }: { q: string; a: string }) {
    const [open, setOpen] = useState(false);
    return (
        <div className="border-b border-gray-200 dark:border-white/8 py-6">
            <button
                onClick={() => setOpen(!open)}
                className="flex w-full items-center justify-between gap-5 text-left"
            >
                <span className="text-lg font-semibold text-gray-900 dark:text-gray-100 md:text-xl">{q}</span>
                <span
                    className={`flex h-7 w-7 shrink-0 items-center justify-center rounded-full border font-mono text-base transition-all duration-200 ${
                        open
                            ? 'rotate-45 border-brand-500 bg-brand-500 text-white'
                            : 'border-gray-300 dark:border-white/15 text-gray-400 dark:text-gray-500'
                    }`}
                >
                    +
                </span>
            </button>
            {open && (
                <p className="mt-4 max-w-3xl text-[15px] leading-relaxed text-gray-500 dark:text-gray-400">
                    {a}
                </p>
            )}
        </div>
    );
}

export default function Welcome() {
    const { auth } = usePage<SharedData>().props;

    const ctaHref = auth.user ? dashboard() : register();
    const ctaLabel = auth.user ? 'Go to Dashboard' : 'Get started — it\'s free';

    return (
        <>
            <Head title="Artemis — Hunt down RTS. Protect your margin." />
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
                            <a
                                href="#features"
                                className="hidden text-[13px] text-gray-500 transition-colors hover:text-brand-500 md:block dark:text-gray-400 dark:hover:text-brand-400"
                            >
                                Features
                            </a>
                            <a
                                href="#free-trial"
                                className="hidden text-[13px] text-gray-500 transition-colors hover:text-brand-500 md:block dark:text-gray-400 dark:hover:text-brand-400"
                            >
                                Free trial
                            </a>
                            <a
                                href="#how"
                                className="hidden text-[13px] text-gray-500 transition-colors hover:text-brand-500 md:block dark:text-gray-400 dark:hover:text-brand-400"
                            >
                                How it works
                            </a>
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
                <section className="relative px-5 pt-24 pb-20 md:px-10 md:pt-28">
                    <div className="relative mx-auto max-w-[1200px] text-center">
                        <div className="mb-10 inline-flex items-center gap-2.5 rounded-full border border-brand-200 bg-brand-50 px-4 py-2 dark:border-brand-500/20 dark:bg-brand-500/10">
                            <span className="h-1.5 w-1.5 rounded-full bg-brand-500 shadow-[0_0_8px_theme(colors.brand-500)]" />
                            <span className="font-mono text-[11px] font-semibold tracking-[0.18em] text-brand-700 uppercase dark:text-brand-400">
                                Now hunting · Built for PH COD sellers
                            </span>
                        </div>

                        <h1 className="mx-auto mb-7 text-5xl! leading-[0.98] font-bold tracking-tight md:text-7xl lg:text-[104px]">
                            Hunt down{' '}
                            <span className="bg-gradient-to-br from-brand-500 to-brand-700 bg-clip-text font-bold text-transparent italic">
                                RTS.
                            </span>
                            <br />
                            Protect your margin.
                        </h1>

                        <p className="mx-auto mb-4 max-w-xl text-base leading-relaxed text-gray-500 sm:text-lg md:text-xl dark:text-gray-400">
                            Artemis is the <span className="font-semibold text-brand-500">1st</span> analytics & automation platform for
                            Philippine COD e-commerce. Track every metric that
                            matters, cut return-to-sender rates, and keep every
                            customer informed.
                        </p>

                        <p className="mb-12 font-mono text-xs tracking-[0.15em] text-gray-400 uppercase dark:text-gray-600">
                            Sales <span className="mx-2 text-brand-500">·</span>{' '}
                            Operations{' '}
                            <span className="mx-2 text-brand-500">·</span>{' '}
                            Delivery{' '}
                            <span className="mx-2 text-brand-500">·</span> RTS{' '}
                            <span className="mx-2 text-brand-500">·</span>{' '}
                            Parcel Journey
                        </p>

                        <div className="mb-4 flex flex-col items-center justify-center gap-3.5 sm:flex-row">
                            <Link
                                href={ctaHref}
                                className="inline-flex h-12 w-full items-center justify-center gap-2.5 rounded-xl bg-brand-500! px-8 text-base font-semibold text-white shadow-lg shadow-brand-500/20 transition-all hover:-translate-y-0.5 hover:bg-brand-600 sm:w-auto"
                            >
                                {ctaLabel}
                                <svg
                                    width="16"
                                    height="12"
                                    viewBox="0 0 16 12"
                                    fill="none"
                                >
                                    <path
                                        d="M1 6h14m0 0L10 1m5 5l-5 5"
                                        stroke="currentColor"
                                        strokeWidth="2"
                                        strokeLinecap="round"
                                    />
                                </svg>
                            </Link>
                            <a
                                href="#how"
                                className="inline-flex h-12 w-full items-center justify-center rounded-xl border border-gray-200 bg-white px-8 text-base font-semibold text-gray-700 transition-all hover:border-brand-300 hover:text-brand-600 sm:w-auto dark:border-white/10 dark:bg-zinc-900 dark:text-gray-300 dark:hover:border-brand-500/40 dark:hover:text-brand-400"
                            >
                                See how it works
                            </a>
                        </div>
                        <span className="block font-mono text-[11px] tracking-[0.1em] text-gray-400 uppercase dark:text-gray-600">
                            — No credit card · Connect 1 Pancake page · 14-day
                            free trial
                        </span>

                        {/* Stats */}
                        <div className="mx-auto mt-16 grid max-w-3xl grid-cols-2 gap-6 border-t border-gray-200 pt-12 md:grid-cols-4 md:gap-0 dark:border-white/8">
                            {[
                                {
                                    num: '30%',
                                    em: true,
                                    label: 'Avg RTS rate\nbefore Artemis',
                                },
                                {
                                    num: '10%',
                                    em: false,
                                    label: 'Typical reduction\nwithin 90 days',
                                },
                                {
                                    num: '₱120k+',
                                    em: false,
                                    label: 'Avg profit recovered\n/ year · mid seller',
                                },
                                {
                                    num: '2 min',
                                    em: false,
                                    label: 'Onboarding to\nfirst insight',
                                },
                            ].map((s, i) => (
                                <div
                                    key={i}
                                    className="text-left md:border-r md:border-gray-200 md:px-6 md:first:pl-0 md:last:border-r-0 dark:md:border-white/8"
                                >
                                    <div
                                        className={`mb-2.5 text-4xl font-bold tracking-tight ${s.em ? 'text-brand-500' : 'text-gray-900 dark:text-gray-100'}`}
                                    >
                                        {s.num}
                                    </div>
                                    <div className="font-mono text-[10px] leading-snug tracking-[0.15em] whitespace-pre-line text-gray-400 uppercase dark:text-gray-500">
                                        {s.label}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                </section>

                {/* Problem */}
                <section className="relative overflow-hidden bg-gray-50 py-24 md:py-28 dark:bg-zinc-900/50">
                    <div className="pointer-events-none absolute top-1/2 right-[-200px] h-[600px] w-[600px] -translate-y-1/2 bg-[radial-gradient(circle,rgba(16,211,161,0.08),transparent_65%)]" />
                    <div className="relative mx-auto max-w-[1200px] px-5 text-center md:px-10">
                        <p className="mb-5 font-mono text-[11px] tracking-[0.2em] text-brand-500 uppercase">
                            — The silent margin killer
                        </p>
                        <h2 className="mb-6 text-4xl font-bold tracking-tight md:text-5xl lg:text-6xl">
                            Every failed parcel is{' '}
                            <span className="text-brand-500 italic">
                                pure loss.
                            </span>
                        </h2>
                        <p className="mx-auto mb-6 max-w-xl text-lg leading-relaxed text-gray-500 dark:text-gray-400">
                            For Filipino COD sellers, RTS isn't just a number —
                            it's money walking out the door. Shipping paid
                            twice, packaging wasted, inventory tied up, and no
                            sale to show for it.
                        </p>
                        <Link
                            href="/rts-calculator"
                            className="mb-14 inline-flex items-center gap-2 text-sm font-semibold text-brand-500 transition-colors hover:text-brand-600"
                        >
                            Calculate your RTS bleed
                            <svg
                                width="14"
                                height="10"
                                viewBox="0 0 16 12"
                                fill="none"
                            >
                                <path
                                    d="M1 6h14m0 0L10 1m5 5l-5 5"
                                    stroke="currentColor"
                                    strokeWidth="2"
                                    strokeLinecap="round"
                                />
                            </svg>
                        </Link>

                        <div className="grid gap-4 text-left sm:gap-5 md:grid-cols-3">
                            {[
                                {
                                    num: '20–40%',
                                    title: 'Typical RTS rate',
                                    desc: 'Most PH COD sellers run between 20–40% return-to-sender. Marami hindi alam ang totoong number nila kasi walang proper tool.',
                                },
                                {
                                    num: '₱150+',
                                    title: 'Cost per failed parcel',
                                    desc: 'Shipping both ways, packaging, handling, and inventory time. Every RTS quietly eats ₱150–₱250 off your margin.',
                                },
                                {
                                    num: '₱50k+',
                                    title: 'Monthly bleed · mid seller',
                                    desc: "1,000 orders × 30% RTS × ₱175 in unrealized profit. That's half a year of rent — gone every single month.",
                                },
                            ].map((b, i) => (
                                <div
                                    key={i}
                                    className="group relative overflow-hidden rounded-xl border border-gray-200 bg-white p-6 transition-all hover:-translate-y-1 hover:border-brand-400 sm:p-8 dark:border-white/8 dark:bg-zinc-900"
                                >
                                    <div className="absolute top-0 right-0 left-0 h-0.5 bg-gradient-to-r from-brand-400 to-brand-600" />
                                    <div className="mb-3 bg-gradient-to-br from-brand-500 to-brand-700 bg-clip-text text-5xl leading-none font-bold tracking-tight text-transparent italic sm:mb-4 sm:text-6xl">
                                        {b.num}
                                    </div>
                                    <h3 className="mb-3 text-xl font-semibold text-gray-900 dark:text-gray-100">
                                        {b.title}
                                    </h3>
                                    <p className="text-[15px] leading-relaxed text-gray-500 dark:text-gray-400">
                                        {b.desc}
                                    </p>
                                </div>
                            ))}
                        </div>
                    </div>
                </section>

                {/* Features */}
                <section id="features" className="py-24 md:py-28">
                    <div className="mx-auto max-w-[1200px] px-5 text-center md:px-10">
                        <p className="mb-5 font-mono text-[11px] tracking-[0.2em] text-brand-500 uppercase">
                            — What Artemis does
                        </p>
                        <h2 className="mb-6 text-4xl font-bold tracking-tight md:text-5xl lg:text-6xl">
                            Every metric that{' '}
                            <span className="text-brand-500 italic">
                                actually moves
                            </span>{' '}
                            your business.
                        </h2>
                        <p className="mx-auto mb-14 max-w-xl text-lg leading-relaxed text-gray-500 dark:text-gray-400">
                            From order to delivery — across every page, shop,
                            and courier. Built specifically for how Filipino
                            e-commerce actually runs.
                        </p>

                        <div className="grid gap-4 sm:gap-5 md:grid-cols-2">
                            {features.map((f) => (
                                <div
                                    key={f.title}
                                    className="group rounded-xl border border-gray-200 bg-white p-6 text-left transition-all hover:-translate-y-1 hover:border-brand-300 sm:p-8 dark:border-white/8 dark:bg-zinc-900 dark:hover:border-brand-500/30"
                                >
                                    <div className="mb-5 flex h-11 w-11 items-center justify-center rounded-xl border border-brand-200 bg-brand-50 text-brand-500 sm:mb-6 dark:border-brand-500/25 dark:bg-brand-500/10">
                                        {f.icon}
                                    </div>
                                    <h3 className="mb-2 text-lg font-semibold tracking-tight text-gray-900 sm:mb-2.5 sm:text-xl md:text-2xl dark:text-gray-100">
                                        {f.title}
                                    </h3>
                                    <p className="text-[14px] leading-relaxed text-gray-500 sm:text-[15px] dark:text-gray-400">
                                        {f.description}
                                    </p>
                                </div>
                            ))}
                        </div>
                    </div>
                </section>

                {/* Free Trial Offer */}
                <section
                    id="free-trial"
                    className="relative overflow-hidden border-t border-b border-gray-200 bg-gray-50 py-24 md:py-28 dark:border-white/8 dark:bg-zinc-900/50"
                >
                    <div className="pointer-events-none absolute top-1/2 left-1/2 h-[500px] w-[700px] -translate-x-1/2 -translate-y-1/2 bg-[radial-gradient(ellipse,rgba(16,211,161,0.08),transparent_65%)]" />
                    <div className="relative mx-auto max-w-[1200px] px-5 md:px-10">
                        <div className="mx-auto max-w-3xl text-center">
                            <p className="mb-5 font-mono text-[11px] tracking-[0.2em] text-brand-500 uppercase">
                                — Launch offer
                            </p>
                            <h2 className="mb-6 text-4xl font-bold tracking-tight md:text-5xl lg:text-6xl">
                                Start free.{' '}
                                <span className="text-brand-500 italic">
                                    See it work.
                                </span>
                            </h2>
                            <p className="mx-auto mb-12 max-w-lg text-lg leading-relaxed text-gray-500 dark:text-gray-400">
                                14 days free. Connect one Pancake page and get 1
                                month of data — enough to see your real numbers
                                and start moving them.
                            </p>
                        </div>

                        <div className="relative mx-auto max-w-2xl overflow-hidden rounded-2xl border-2 border-brand-500/80 bg-gradient-to-b from-brand-50/90 to-white shadow-xl shadow-brand-500/8 dark:border-brand-500/60 dark:from-brand-500/[0.06] dark:to-zinc-900">
                            <div className="absolute -top-16 -right-16 h-40 w-40 rounded-full bg-brand-500/10 blur-3xl" />
                            <div className="relative px-8 py-10 text-center md:px-12">
                                <div className="mb-6 inline-flex items-center gap-2 rounded-full border border-brand-300 bg-brand-50 px-4 py-1.5 dark:border-brand-500/30 dark:bg-brand-500/10">
                                    <span className="font-mono text-[10px] font-semibold tracking-[0.2em] text-brand-600 uppercase dark:text-brand-400">
                                        14-day free trial
                                    </span>
                                </div>

                                <h3 className="mb-3 text-3xl font-bold tracking-tight text-gray-900 md:text-4xl dark:text-gray-100">
                                    ₱0
                                </h3>
                                <p className="mb-8 text-sm text-gray-500 dark:text-gray-400">
                                    No credit card required · No commitment
                                </p>

                                <div className="mb-8 h-px bg-gray-200 dark:bg-white/8" />

                                <ul className="mx-auto mb-10 max-w-sm space-y-0 text-left">
                                    {[
                                        'Connect 1 Pancake page',
                                        '1 month of order & delivery data',
                                        'Parcel Journey tracking via chat',
                                        'Chat support',
                                    ].map((feat) => (
                                        <li
                                            key={feat}
                                            className="relative py-3 pl-8 text-[15px] text-gray-700 dark:text-gray-300"
                                        >
                                            <span className="absolute top-3.5 left-0 flex h-4 w-4 items-center justify-center rounded-full border border-brand-500 bg-brand-50 dark:bg-brand-500/10">
                                                <svg
                                                    width="9"
                                                    height="7"
                                                    viewBox="0 0 8 6"
                                                    fill="none"
                                                >
                                                    <path
                                                        d="M1 3l2 2 4-4"
                                                        stroke="currentColor"
                                                        strokeWidth="1.5"
                                                        strokeLinecap="round"
                                                        strokeLinejoin="round"
                                                        className="text-brand-500"
                                                    />
                                                </svg>
                                            </span>
                                            {feat}
                                        </li>
                                    ))}
                                </ul>

                                <Link
                                    href={ctaHref}
                                    className="inline-flex h-12 w-full max-w-sm items-center justify-center gap-2.5 rounded-xl bg-brand-500! text-base font-semibold text-white shadow-lg shadow-brand-500/20 transition-all hover:-translate-y-0.5 hover:bg-brand-600"
                                >
                                    {ctaLabel}
                                    <svg
                                        width="16"
                                        height="12"
                                        viewBox="0 0 16 12"
                                        fill="none"
                                    >
                                        <path
                                            d="M1 6h14m0 0L10 1m5 5l-5 5"
                                            stroke="currentColor"
                                            strokeWidth="2"
                                            strokeLinecap="round"
                                        />
                                    </svg>
                                </Link>
                                <p className="mt-4 font-mono text-[10px] tracking-[0.15em] text-gray-400 uppercase dark:text-gray-500">
                                    Setup takes under 2 minutes
                                </p>
                            </div>
                        </div>
                    </div>
                </section>

                {/* How it works */}
                <section id="how" className="py-24 md:py-28">
                    <div className="mx-auto max-w-[1200px] px-5 text-center md:px-10">
                        <p className="mb-5 font-mono text-[11px] tracking-[0.2em] text-brand-500 uppercase">
                            — How it works
                        </p>
                        <h2 className="mb-6 text-4xl font-bold tracking-tight md:text-5xl lg:text-6xl">
                            From signup to first insight —{' '}
                            <span className="text-brand-500 italic">
                                in under five minutes.
                            </span>
                        </h2>

                        <div className="mt-14 grid gap-6 md:grid-cols-2 lg:grid-cols-4">
                            {steps.map((s) => (
                                <div
                                    key={s.num}
                                    className="relative border-t-2 border-brand-500 pt-7"
                                >
                                    <p className="mb-4 font-mono text-[11px] font-medium tracking-[0.2em] text-brand-500 uppercase">
                                        {s.num}
                                    </p>
                                    <h4 className="mb-2.5 text-xl font-semibold tracking-tight text-gray-900 dark:text-gray-100">
                                        {s.title}
                                    </h4>
                                    <p className="text-sm leading-relaxed text-gray-500 dark:text-gray-400">
                                        {s.description}
                                    </p>
                                </div>
                            ))}
                        </div>
                    </div>
                </section>

                {/* Results from real sellers */}
                <section className="relative overflow-hidden border-t border-gray-200 py-24 md:py-28 dark:border-white/8">
                    <div className="mx-auto max-w-[1200px] px-5 text-center md:px-10">
                        <p className="mb-5 font-mono text-[11px] tracking-[0.2em] text-brand-500 uppercase">
                            — Real results from real sellers
                        </p>
                        <h2 className="mb-6 text-4xl font-bold tracking-tight md:text-5xl lg:text-6xl">
                            The numbers{' '}
                            <span className="text-brand-500 italic">
                                speak.
                            </span>
                        </h2>
                        <p className="mx-auto mb-14 max-w-xl text-lg leading-relaxed text-gray-500 dark:text-gray-400">
                            These are actual RTS results from Pancake sellers
                            using Artemis — tracked across months, not
                            cherry-picked.
                        </p>

                        <div className="grid gap-5 text-left md:grid-cols-2">
                            {[
                                {
                                    name: 'Health & Wellness Brand',
                                    category: 'Herbal spray · 10M+ parcels',
                                    before: '17.27%',
                                    after: '14.35%',
                                    change: '-17%',
                                    period: 'over 5 months',
                                    detail: 'Started with no SMS/chat notifications. After enabling Parcel Journey tracking, RTS dropped steadily from 17.27% to 14.35% — consistent improvement month over month.',
                                },
                                {
                                    name: 'Care Brand',
                                    category: 'Inhaler product · 9M+ parcels',
                                    before: '32.95%',
                                    after: '17.21%',
                                    change: '-48%',
                                    period: 'in 3 months',
                                    detail: 'Went from zero customer notifications to full SMS + chat coverage. RTS cut from 32.95% to 17.21% in just 3 months.',
                                },
                                {
                                    name: 'Herbal Brand',
                                    category: 'Capsule product · 4.5M+ parcels',
                                    before: '14.12%',
                                    after: '9.72%',
                                    change: '-31%',
                                    period: 'steady over 8 months',
                                    detail: 'Already running decent ops. Artemis helped fine-tune — consistently sub-15% RTS, now consistently under 10%.',
                                },
                                {
                                    name: 'Pain Relief Brand',
                                    category: 'Topical cream · 8.4M+ parcels',
                                    before: '18.29%',
                                    after: '12.74%',
                                    change: '-30%',
                                    period: 'over 8 months',
                                    detail: 'High-volume seller running across multiple regions. Consistent downward trend in RTS — from 18.29% down to 12.74% with SMS + chat notifications active.',
                                },
                            ].map((c) => (
                                <div
                                    key={c.name}
                                    className="group relative overflow-hidden rounded-2xl border border-gray-200 bg-white p-5 transition-all hover:-translate-y-1 hover:border-brand-300 sm:p-7 md:p-8 dark:border-white/8 dark:bg-zinc-900 dark:hover:border-brand-500/30"
                                >
                                    <div className="absolute top-0 right-0 left-0 h-0.5 bg-gradient-to-r from-brand-400 to-brand-600" />

                                    <div className="mb-5">
                                        <h3 className="text-[15px] font-bold text-gray-900 dark:text-gray-100">
                                            {c.name}
                                        </h3>
                                        <p className="mt-0.5 font-mono text-[10px] tracking-wider text-gray-400 uppercase dark:text-gray-500">
                                            {c.category}
                                        </p>
                                    </div>

                                    <div className="mb-5 flex flex-wrap items-center gap-3 sm:gap-4">
                                        <div>
                                            <p className="font-mono text-[10px] tracking-wider text-gray-400 uppercase dark:text-gray-500">
                                                Before
                                            </p>
                                            <p className="text-xl font-bold tracking-tight text-gray-400 line-through decoration-gray-300 sm:text-2xl dark:text-gray-500 dark:decoration-gray-600">
                                                {c.before}
                                            </p>
                                        </div>
                                        <svg
                                            className="h-4 w-4 shrink-0 text-brand-500"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                            stroke="currentColor"
                                            strokeWidth={2.5}
                                        >
                                            <path
                                                strokeLinecap="round"
                                                strokeLinejoin="round"
                                                d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"
                                            />
                                        </svg>
                                        <div>
                                            <p className="font-mono text-[10px] tracking-wider text-brand-600 uppercase dark:text-brand-400">
                                                After
                                            </p>
                                            <p className="text-xl font-bold tracking-tight text-brand-600 sm:text-2xl dark:text-brand-400">
                                                {c.after}
                                            </p>
                                        </div>
                                        <div className="sm:ml-auto">
                                            <span className="inline-flex items-center rounded-full border border-brand-200 bg-brand-50 px-2.5 py-1 font-mono text-[11px] font-semibold text-brand-700 dark:border-brand-500/20 dark:bg-brand-500/10 dark:text-brand-400">
                                                {c.change}
                                            </span>
                                        </div>
                                    </div>

                                    <p className="mb-3 text-[13px] leading-relaxed text-gray-500 dark:text-gray-400">
                                        {c.detail}
                                    </p>
                                    <p className="font-mono text-[10px] tracking-wider text-gray-400 uppercase dark:text-gray-500">
                                        {c.period}
                                    </p>
                                </div>
                            ))}
                        </div>

                        <p className="mt-8 text-[12px] text-gray-400 dark:text-gray-500">
                            Data sourced from actual Pancake POS pages. Brand
                            names anonymized. Results vary by product, audience,
                            and courier.
                        </p>
                    </div>
                </section>

                {/* FAQ */}
                <section id="faq" className="py-24 md:py-28">
                    <div className="mx-auto max-w-[1200px] px-5 text-center md:px-10">
                        <p className="mb-5 font-mono text-[11px] tracking-[0.2em] text-brand-500 uppercase">
                            — Questions, answered
                        </p>
                        <h2 className="mb-6 text-4xl font-bold tracking-tight md:text-5xl lg:text-6xl">
                            The short{' '}
                            <span className="text-brand-500 italic">FAQ.</span>
                        </h2>

                        <div className="mx-auto mt-12 max-w-3xl">
                            {faqs.map((faq) => (
                                <FaqItem key={faq.q} q={faq.q} a={faq.a} />
                            ))}
                        </div>
                    </div>
                </section>

                {/* Final CTA */}
                <section className="relative overflow-hidden py-32 md:py-36">
                    <div className="pointer-events-none absolute top-1/2 left-1/2 h-[600px] w-full -translate-x-1/2 -translate-y-1/2 bg-[radial-gradient(ellipse,rgba(16,211,161,0.15),transparent_60%)]" />
                    <div className="relative mx-auto max-w-[1200px] px-5 text-center md:px-10">
                        <h2 className="mx-auto mb-6 max-w-3xl text-4xl font-bold tracking-tight md:text-5xl lg:text-6xl">
                            Your RTS is a number.{' '}
                            <span className="text-brand-500 italic">
                                Let's move it.
                            </span>
                        </h2>
                        <p className="mx-auto mb-10 max-w-xl text-lg leading-relaxed text-gray-500 dark:text-gray-400">
                            14 days free. Connect 1 Pancake page, get 1 month of
                            data. Parcel Journey + chat support. Are you ready?
                        </p>
                        <div className="flex flex-col items-center justify-center gap-3.5 sm:flex-row">
                            <Link
                                href={ctaHref}
                                className="inline-flex h-12 w-full items-center justify-center gap-2.5 rounded-xl bg-brand-500! px-8 text-base font-semibold text-white shadow-lg shadow-brand-500/20 transition-all hover:-translate-y-0.5 hover:bg-brand-600 sm:w-auto"
                            >
                                {ctaLabel}
                                <svg
                                    width="16"
                                    height="12"
                                    viewBox="0 0 16 12"
                                    fill="none"
                                >
                                    <path
                                        d="M1 6h14m0 0L10 1m5 5l-5 5"
                                        stroke="currentColor"
                                        strokeWidth="2"
                                        strokeLinecap="round"
                                    />
                                </svg>
                            </Link>
                            <Link
                                href="/rts-calculator"
                                className="inline-flex h-12 w-full items-center justify-center rounded-xl border border-gray-200 bg-white px-8 text-base font-semibold text-gray-700 transition-all hover:border-brand-300 hover:text-brand-600 sm:w-auto dark:border-white/10 dark:bg-zinc-900 dark:text-gray-300 dark:hover:border-brand-500/40 dark:hover:text-brand-400"
                            >
                                Calculate your RTS bleed
                            </Link>
                        </div>
                    </div>
                </section>

                {/* Footer */}
                <footer className="border-t border-gray-200 dark:border-white/8">
                    <div className="mx-auto max-w-[1200px] px-5 py-16 md:px-10">
                        <div className="mb-12 grid grid-cols-2 gap-8 sm:gap-10 md:grid-cols-[2fr_1fr_1fr_1fr] md:gap-12">
                            <div className="col-span-2 md:col-span-1">
                                <div className="mb-3 flex items-center gap-3">
                                    <img
                                        src="/img/logo/artemis.png"
                                        alt="Artemis"
                                        className="h-8 w-8 object-contain"
                                    />
                                    <span className="text-2xl font-semibold tracking-tight">
                                        Artemis
                                    </span>
                                </div>
                                <p className="max-w-xs text-sm leading-relaxed text-gray-500 dark:text-gray-400">
                                    The analytics & automation platform for
                                    Philippine COD e-commerce. From order to
                                    delivery — every metric that matters.
                                </p>
                            </div>
                            <div>
                                <h5 className="mb-4 font-mono text-[10px] tracking-[0.2em] text-gray-400 uppercase dark:text-gray-600">
                                    Product
                                </h5>
                                <div className="flex flex-col gap-2">
                                    <a
                                        href="#features"
                                        className="text-sm text-gray-600 transition-colors hover:text-brand-500 dark:text-gray-300"
                                    >
                                        Features
                                    </a>
                                    <a
                                        href="#free-trial"
                                        className="text-sm text-gray-600 transition-colors hover:text-brand-500 dark:text-gray-300"
                                    >
                                        Free trial
                                    </a>
                                    <a
                                        href="#how"
                                        className="text-sm text-gray-600 transition-colors hover:text-brand-500 dark:text-gray-300"
                                    >
                                        How it works
                                    </a>
                                    <a
                                        href="#faq"
                                        className="text-sm text-gray-600 transition-colors hover:text-brand-500 dark:text-gray-300"
                                    >
                                        FAQ
                                    </a>
                                    <Link
                                        href="/rts-calculator"
                                        className="text-sm text-gray-600 transition-colors hover:text-brand-500 dark:text-gray-300"
                                    >
                                        RTS Calculator
                                    </Link>
                                </div>
                            </div>
                            <div>
                                <h5 className="mb-4 font-mono text-[10px] tracking-[0.2em] text-gray-400 uppercase dark:text-gray-600">
                                    Company
                                </h5>
                                <div className="flex flex-col gap-2">
                                    <Link
                                        href="/about"
                                        className="text-sm text-gray-600 transition-colors hover:text-brand-500 dark:text-gray-300"
                                    >
                                        About
                                    </Link>
                                    <Link
                                        href="/blog"
                                        className="text-sm text-gray-600 transition-colors hover:text-brand-500 dark:text-gray-300"
                                    >
                                        Blog
                                    </Link>
                                    <Link
                                        href="/contact"
                                        className="text-sm text-gray-600 transition-colors hover:text-brand-500 dark:text-gray-300"
                                    >
                                        Contact
                                    </Link>
                                </div>
                            </div>
                            <div>
                                <h5 className="mb-4 font-mono text-[10px] tracking-[0.2em] text-gray-400 uppercase dark:text-gray-600">
                                    Legal
                                </h5>
                                <div className="flex flex-col gap-2">
                                    <Link
                                        href="/privacy"
                                        className="text-sm text-gray-600 transition-colors hover:text-brand-500 dark:text-gray-300"
                                    >
                                        Privacy
                                    </Link>
                                    <Link
                                        href="/terms"
                                        className="text-sm text-gray-600 transition-colors hover:text-brand-500 dark:text-gray-300"
                                    >
                                        Terms
                                    </Link>
                                    <Link
                                        href="/data-policy"
                                        className="text-sm text-gray-600 transition-colors hover:text-brand-500 dark:text-gray-300"
                                    >
                                        Data policy
                                    </Link>
                                    <Link
                                        href="/security"
                                        className="text-sm text-gray-600 transition-colors hover:text-brand-500 dark:text-gray-300"
                                    >
                                        Security
                                    </Link>
                                </div>
                            </div>
                        </div>
                        <div className="flex flex-col items-center justify-between gap-3 border-t border-gray-200 pt-6 font-mono text-[11px] tracking-[0.1em] text-gray-400 md:flex-row dark:border-white/8 dark:text-gray-600">
                            <span>
                                &copy; {new Date().getFullYear()} Artemis. All
                                rights reserved.
                            </span>
                            <span>Built in the Philippines</span>
                        </div>
                    </div>
                </footer>
            </div>
        </>
    );
}
