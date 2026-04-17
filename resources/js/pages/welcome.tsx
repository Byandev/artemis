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

const tiers = [
    {
        num: '01',
        name: 'Visibility',
        description: 'See what\'s happening. Keep customers informed. The essentials, automated.',
        price: '2,999',
        priceSub: '14-day free trial',
        features: [
            'Full analytics dashboard',
            'Sales, delivery & RTS analytics',
            'Parcel journey tracking',
            'Auto SMS / Viber / Messenger',
            'Up to 3,000 orders / month',
            'Email support',
        ],
        cta: 'Start free trial',
        featured: false,
    },
    {
        num: '02',
        name: 'Strategy',
        description: 'Everything in Visibility, plus a monthly consultation with our analysts.',
        price: '12,999',
        priceSub: '14-day free trial',
        features: [
            'Everything in Visibility',
            'Monthly 1-on-1 strategy call',
            'Custom "what to fix" report',
            'Category benchmarking vs peers',
            'Quarterly business review',
            'Priority chat support',
            'Up to 10,000 orders / month',
        ],
        cta: 'Start free trial',
        featured: true,
        badge: 'Most popular',
    },
    {
        num: '03',
        name: 'Control',
        description: 'Full profit protection. We don\'t just show leaks — we help plug them.',
        price: '29,999',
        priceSub: 'Or performance-based pricing',
        features: [
            'Everything in Strategy',
            'Buyer risk scoring engine',
            'Profit simulator & sweet-spot finder',
            'Auto-confirmation flows',
            'Address validation',
            'Courier & channel API integration',
            'Unlimited orders',
            'Dedicated success manager',
        ],
        cta: 'Talk to sales',
        featured: false,
    },
];

const steps = [
    { num: '01 / SIGN UP', title: 'Create your workspace', description: 'Enter your business name and a few basics. No credit card, no lengthy forms.' },
    { num: '02 / CONNECT', title: 'Link your data', description: 'Connect your couriers and sales channels — or upload a CSV of your recent orders.' },
    { num: '03 / SEE', title: 'Your first insight', description: 'Within minutes, see your real RTS rate, lost profit, and highest-risk zones.' },
    { num: '04 / ACT', title: 'Move the number', description: 'Turn on notifications, follow our recommendations, and watch RTS drop month over month.' },
];

const faqs = [
    {
        q: 'Do I need to integrate my courier account right away?',
        a: 'No — you can start by uploading a CSV of your recent orders. We\'ll show you your baseline analytics within minutes. Courier API integration unlocks real-time tracking, but it\'s optional.',
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
        q: 'Which couriers and sales channels do you support?',
        a: 'J&T, Flash Express, LBC, Ninja Van, SPX, and more. For channels: Shopee, Lazada, TikTok Shop, your own Shopify/Woo storefront, and manual uploads. Adding new integrations continuously.',
    },
    {
        q: 'Is my customer data safe?',
        a: 'Yes. We\'re Data Privacy Act (RA 10173) compliant, store data encrypted at rest, and never share your numbers with anyone else. Your dashboard is your dashboard.',
    },
    {
        q: 'Can I cancel anytime?',
        a: 'Yes. No lock-in, no cancellation fees. If Artemis isn\'t working for your business, you can export your data and leave whenever.',
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
            <div className="relative min-h-screen bg-white text-gray-900 dark:bg-zinc-950 dark:text-gray-100 overflow-hidden">

                {/* Background decorations */}
                <div className="pointer-events-none absolute -top-40 left-1/2 -translate-x-1/2 h-[500px] w-[900px] bg-[radial-gradient(ellipse,rgba(16,211,161,0.12),transparent_70%)]" />
                <div className="pointer-events-none absolute inset-0 bg-[linear-gradient(rgba(0,0,0,0.03)_1px,transparent_1px),linear-gradient(90deg,rgba(0,0,0,0.03)_1px,transparent_1px)] dark:bg-[linear-gradient(rgba(255,255,255,0.015)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,0.015)_1px,transparent_1px)] bg-[size:60px_60px]" />

                {/* Nav */}
                <nav className="sticky top-0 z-50 border-b border-gray-200/80 bg-white/80 backdrop-blur-2xl dark:border-white/6 dark:bg-zinc-950/80">
                    <div className="mx-auto flex max-w-[1200px] items-center justify-between px-5 py-4 md:px-10">
                        <Link href="/" className="flex items-center gap-3">
                            <img src="/img/logo/artemis.png" alt="Artemis" className="h-9 w-9 object-contain" />
                            <span className="text-[22px] font-semibold tracking-tight">Artemis</span>
                        </Link>
                        <div className="flex items-center gap-6 text-sm">
                            <a href="#features" className="hidden text-[13px] text-gray-500 transition-colors hover:text-brand-500 dark:text-gray-400 dark:hover:text-brand-400 md:block">Features</a>
                            <a href="#pricing" className="hidden text-[13px] text-gray-500 transition-colors hover:text-brand-500 dark:text-gray-400 dark:hover:text-brand-400 md:block">Pricing</a>
                            <a href="#how" className="hidden text-[13px] text-gray-500 transition-colors hover:text-brand-500 dark:text-gray-400 dark:hover:text-brand-400 md:block">How it works</a>
                            <Link href="/rts-calculator" className="hidden text-[13px] text-gray-500 transition-colors hover:text-brand-500 dark:text-gray-400 dark:hover:text-brand-400 md:block">RTS Calculator</Link>
                            <div className="hidden h-4 w-px bg-gray-200 dark:bg-white/10 md:block" />
                            <AppearanceToggleDropdown />
                            {auth.user ? (
                                <Link href={dashboard()} className="inline-flex h-9 items-center rounded-lg bg-brand-500! px-5 text-[13px] font-semibold text-white shadow-sm shadow-brand-500/20 transition-all hover:bg-brand-600 hover:shadow-md hover:shadow-brand-500/25">
                                    Dashboard
                                </Link>
                            ) : (
                                <>
                                    <Link href={login()} className="hidden text-[13px] text-gray-500 transition-colors hover:text-brand-500 dark:text-gray-400 dark:hover:text-brand-400 md:block">
                                        Log in
                                    </Link>
                                    <Link href={register()} className="inline-flex h-9 items-center rounded-lg bg-brand-500! px-5 text-[13px] font-semibold text-white shadow-sm shadow-brand-500/20 transition-all hover:bg-brand-600 hover:shadow-md hover:shadow-brand-500/25">
                                        Start free
                                    </Link>
                                </>
                            )}
                        </div>
                    </div>
                </nav>

                {/* Hero */}
                <section className="relative px-5 pb-20 pt-24 md:px-10 md:pt-28">
                    <div className="relative mx-auto max-w-[1200px] text-center">
                        <div className="mb-10 inline-flex items-center gap-2.5 rounded-full border border-brand-200 bg-brand-50 px-4 py-2 dark:border-brand-500/20 dark:bg-brand-500/10">
                            <span className="h-1.5 w-1.5 rounded-full bg-brand-500 shadow-[0_0_8px_theme(colors.brand-500)]" />
                            <span className="font-mono text-[11px] font-semibold uppercase tracking-[0.18em] text-brand-700 dark:text-brand-400">
                                Now hunting · Built for PH COD sellers
                            </span>
                        </div>

                        <h1 className="mx-auto mb-7 text-5xl! font-bold leading-[0.98] tracking-tight md:text-7xl lg:text-[104px]">
                            Hunt down{' '}
                            <span className="bg-gradient-to-br from-brand-500 to-brand-700 bg-clip-text font-bold italic text-transparent">RTS.</span>
                            <br />
                            Protect your margin.
                        </h1>

                        <p className="mx-auto mb-4 max-w-xl text-lg leading-relaxed text-gray-500 dark:text-gray-400 md:text-xl">
                            Artemis is the analytics & automation platform for Philippine COD e-commerce. Track every metric that matters, cut return-to-sender rates, and keep every customer informed.
                        </p>

                        <p className="mb-12 font-mono text-xs uppercase tracking-[0.15em] text-gray-400 dark:text-gray-600">
                            Sales <span className="mx-2 text-brand-500">·</span> Operations <span className="mx-2 text-brand-500">·</span> Delivery <span className="mx-2 text-brand-500">·</span> RTS <span className="mx-2 text-brand-500">·</span> Parcel Journey
                        </p>

                        <div className="mb-4 flex flex-col items-center justify-center gap-3.5 sm:flex-row">
                            <Link
                                href={ctaHref}
                                className="inline-flex h-12 items-center gap-2.5 rounded-xl bg-brand-500! px-8 text-base font-semibold text-white shadow-lg shadow-brand-500/20 transition-all hover:bg-brand-600 hover:-translate-y-0.5"
                            >
                                {ctaLabel}
                                <svg width="16" height="12" viewBox="0 0 16 12" fill="none"><path d="M1 6h14m0 0L10 1m5 5l-5 5" stroke="currentColor" strokeWidth="2" strokeLinecap="round" /></svg>
                            </Link>
                            <a
                                href="#how"
                                className="inline-flex h-12 items-center rounded-xl border border-gray-200 bg-white px-8 text-base font-semibold text-gray-700 transition-all hover:border-brand-300 hover:text-brand-600 dark:border-white/10 dark:bg-zinc-900 dark:text-gray-300 dark:hover:border-brand-500/40 dark:hover:text-brand-400"
                            >
                                See how it works
                            </a>
                        </div>
                        <span className="block font-mono text-[11px] uppercase tracking-[0.1em] text-gray-400 dark:text-gray-600">
                            — No credit card · 14-day free trial · Cancel anytime
                        </span>

                        {/* Stats */}
                        <div className="mx-auto mt-16 grid max-w-3xl grid-cols-2 gap-6 border-t border-gray-200 pt-12 dark:border-white/8 md:grid-cols-4 md:gap-0">
                            {[
                                { num: '30%', em: true, label: 'Avg RTS rate\nbefore Artemis' },
                                { num: '10%', em: false, label: 'Typical reduction\nwithin 90 days' },
                                { num: '₱120k+', em: false, label: 'Avg profit recovered\n/ year · mid seller' },
                                { num: '2 min', em: false, label: 'Onboarding to\nfirst insight' },
                            ].map((s, i) => (
                                <div key={i} className="text-left md:border-r md:border-gray-200 md:px-6 md:last:border-r-0 md:first:pl-0 dark:md:border-white/8">
                                    <div className={`mb-2.5 text-4xl font-bold tracking-tight ${s.em ? 'text-brand-500' : 'text-gray-900 dark:text-gray-100'}`}>
                                        {s.num}
                                    </div>
                                    <div className="whitespace-pre-line font-mono text-[10px] uppercase leading-snug tracking-[0.15em] text-gray-400 dark:text-gray-500">
                                        {s.label}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                </section>

                {/* Problem */}
                <section className="relative overflow-hidden bg-gray-50 py-24 dark:bg-zinc-900/50 md:py-28">
                    <div className="pointer-events-none absolute right-[-200px] top-1/2 h-[600px] w-[600px] -translate-y-1/2 bg-[radial-gradient(circle,rgba(16,211,161,0.08),transparent_65%)]" />
                    <div className="relative mx-auto max-w-[1200px] px-5 md:px-10">
                        <p className="mb-5 font-mono text-[11px] uppercase tracking-[0.2em] text-brand-500">— The silent margin killer</p>
                        <h2 className="mb-6 max-w-3xl text-4xl font-bold tracking-tight md:text-5xl lg:text-6xl">
                            Every failed parcel is{' '}
                            <span className="text-brand-500 italic">pure loss.</span>
                        </h2>
                        <p className="mb-6 max-w-xl text-lg leading-relaxed text-gray-500 dark:text-gray-400">
                            For Filipino COD sellers, RTS isn't just a number — it's money walking out the door. Shipping paid twice, packaging wasted, inventory tied up, and no sale to show for it.
                        </p>
                        <Link
                            href="/rts-calculator"
                            className="mb-14 inline-flex items-center gap-2 text-sm font-semibold text-brand-500 transition-colors hover:text-brand-600"
                        >
                            Calculate your RTS bleed
                            <svg width="14" height="10" viewBox="0 0 16 12" fill="none"><path d="M1 6h14m0 0L10 1m5 5l-5 5" stroke="currentColor" strokeWidth="2" strokeLinecap="round" /></svg>
                        </Link>

                        <div className="grid gap-5 md:grid-cols-3">
                            {[
                                { num: '20–40%', title: 'Typical RTS rate', desc: 'Most PH COD sellers run between 20–40% return-to-sender. Marami hindi alam ang totoong number nila kasi walang proper tool.' },
                                { num: '₱150+', title: 'Cost per failed parcel', desc: 'Shipping both ways, packaging, handling, and inventory time. Every RTS quietly eats ₱150–₱250 off your margin.' },
                                { num: '₱50k+', title: 'Monthly bleed · mid seller', desc: '1,000 orders × 30% RTS × ₱175 in unrealized profit. That\'s half a year of rent — gone every single month.' },
                            ].map((b, i) => (
                                <div
                                    key={i}
                                    className="group relative overflow-hidden rounded-xl border border-gray-200 bg-white p-8 transition-all hover:-translate-y-1 hover:border-brand-400 dark:border-white/8 dark:bg-zinc-900"
                                >
                                    <div className="absolute left-0 right-0 top-0 h-0.5 bg-gradient-to-r from-brand-400 to-brand-600" />
                                    <div className="mb-4 bg-gradient-to-br from-brand-500 to-brand-700 bg-clip-text text-6xl font-bold italic leading-none tracking-tight text-transparent">
                                        {b.num}
                                    </div>
                                    <h3 className="mb-3 text-xl font-semibold text-gray-900 dark:text-gray-100">{b.title}</h3>
                                    <p className="text-[15px] leading-relaxed text-gray-500 dark:text-gray-400">{b.desc}</p>
                                </div>
                            ))}
                        </div>
                    </div>
                </section>

                {/* Features */}
                <section id="features" className="py-24 md:py-28">
                    <div className="mx-auto max-w-[1200px] px-5 md:px-10">
                        <p className="mb-5 font-mono text-[11px] uppercase tracking-[0.2em] text-brand-500">— What Artemis does</p>
                        <h2 className="mb-6 max-w-3xl text-4xl font-bold tracking-tight md:text-5xl lg:text-6xl">
                            Every metric that{' '}
                            <span className="text-brand-500 italic">actually moves</span>{' '}
                            your business.
                        </h2>
                        <p className="mb-14 max-w-xl text-lg leading-relaxed text-gray-500 dark:text-gray-400">
                            From order to delivery — across every page, shop, and courier. Built specifically for how Filipino e-commerce actually runs.
                        </p>

                        <div className="grid gap-5 md:grid-cols-2">
                            {features.map((f) => (
                                <div
                                    key={f.title}
                                    className="group rounded-xl border border-gray-200 bg-white p-8 transition-all hover:-translate-y-1 hover:border-brand-300 dark:border-white/8 dark:bg-zinc-900 dark:hover:border-brand-500/30"
                                >
                                    <div className="mb-6 flex h-11 w-11 items-center justify-center rounded-xl border border-brand-200 bg-brand-50 text-brand-500 dark:border-brand-500/25 dark:bg-brand-500/10">
                                        {f.icon}
                                    </div>
                                    <h3 className="mb-2.5 text-xl font-semibold tracking-tight text-gray-900 dark:text-gray-100 md:text-2xl">{f.title}</h3>
                                    <p className="text-[15px] leading-relaxed text-gray-500 dark:text-gray-400">{f.description}</p>
                                </div>
                            ))}
                        </div>
                    </div>
                </section>

                {/* Pricing */}
                <section id="pricing" className="border-t border-b border-gray-200 bg-gray-50 py-24 dark:border-white/8 dark:bg-zinc-900/50 md:py-28">
                    <div className="mx-auto max-w-[1200px] px-5 md:px-10">
                        <p className="mb-5 font-mono text-[11px] uppercase tracking-[0.2em] text-brand-500">— Pricing that grows with you</p>
                        <h2 className="mb-6 max-w-3xl text-4xl font-bold tracking-tight md:text-5xl lg:text-6xl">
                            Start free.{' '}
                            <span className="text-brand-500 italic">Scale when ready.</span>
                        </h2>
                        <p className="mb-14 max-w-xl text-lg leading-relaxed text-gray-500 dark:text-gray-400">
                            Three tiers built around where you are in the journey. Start with clarity, add strategy, then unlock full profit control.
                        </p>

                        <div className="grid gap-5 md:grid-cols-3">
                            {tiers.map((tier) => (
                                <div
                                    key={tier.num}
                                    className={`relative flex flex-col rounded-xl border p-10 transition-all hover:-translate-y-1 ${
                                        tier.featured
                                            ? 'border-brand-500 bg-gradient-to-b from-brand-50 to-white shadow-xl shadow-brand-500/10 dark:from-brand-500/8 dark:to-zinc-900 md:scale-[1.02]'
                                            : 'border-gray-200 bg-white dark:border-white/8 dark:bg-zinc-900'
                                    }`}
                                >
                                    {tier.featured && (
                                        <span className="absolute -top-3 left-1/2 -translate-x-1/2 rounded-full bg-brand-500 px-3.5 py-1 font-mono text-[10px] font-semibold uppercase tracking-[0.2em] text-white">
                                            {tier.badge}
                                        </span>
                                    )}
                                    <p className={`mb-3.5 font-mono text-[11px] uppercase tracking-[0.2em] ${tier.featured ? 'text-brand-500' : 'text-gray-400 dark:text-gray-500'}`}>
                                        — Tier {tier.num}
                                    </p>
                                    <h3 className="mb-2.5 text-4xl font-bold tracking-tight text-gray-900 dark:text-gray-100">{tier.name}</h3>
                                    <p className="mb-7 min-h-[44px] text-sm leading-relaxed text-gray-500 dark:text-gray-400">{tier.description}</p>
                                    <div className="mb-1 flex items-baseline gap-2 text-4xl font-bold tracking-tight text-gray-900 dark:text-gray-100">
                                        ₱{tier.price}
                                        <span className="text-sm font-normal text-gray-400">/ month</span>
                                    </div>
                                    <p className="mb-7 font-mono text-[11px] uppercase tracking-[0.15em] text-gray-400 dark:text-gray-500">{tier.priceSub}</p>
                                    <div className="mb-6 h-px bg-gray-200 dark:bg-white/8" />
                                    <ul className="mb-8 flex-1 space-y-0">
                                        {tier.features.map((feat) => (
                                            <li key={feat} className="relative py-2.5 pl-7 text-sm text-gray-600 dark:text-gray-300">
                                                <span className="absolute left-0 top-3 flex h-3.5 w-3.5 items-center justify-center rounded-full border border-brand-500 bg-brand-50 dark:bg-brand-500/10">
                                                    <svg width="8" height="6" viewBox="0 0 8 6" fill="none">
                                                        <path d="M1 3l2 2 4-4" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" className="text-brand-500" />
                                                    </svg>
                                                </span>
                                                {feat}
                                            </li>
                                        ))}
                                    </ul>
                                    <div className="mt-auto">
                                        <Link
                                            href={tier.featured ? ctaHref : '#'}
                                            className={`inline-flex h-11 w-full items-center justify-center rounded-lg text-sm font-semibold transition-all ${
                                                tier.featured
                                                    ? 'bg-brand-500! text-white shadow-lg shadow-brand-500/20 hover:bg-brand-600'
                                                    : 'border border-gray-200 bg-white text-gray-700 hover:border-brand-300 hover:text-brand-600 dark:border-white/10 dark:bg-zinc-800 dark:text-gray-300 dark:hover:border-brand-500/40 dark:hover:text-brand-400'
                                            }`}
                                        >
                                            {tier.cta}
                                        </Link>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                </section>

                {/* How it works */}
                <section id="how" className="py-24 md:py-28">
                    <div className="mx-auto max-w-[1200px] px-5 md:px-10">
                        <p className="mb-5 font-mono text-[11px] uppercase tracking-[0.2em] text-brand-500">— How it works</p>
                        <h2 className="mb-6 max-w-3xl text-4xl font-bold tracking-tight md:text-5xl lg:text-6xl">
                            From signup to first insight —{' '}
                            <span className="text-brand-500 italic">in under five minutes.</span>
                        </h2>

                        <div className="mt-14 grid gap-6 md:grid-cols-2 lg:grid-cols-4">
                            {steps.map((s) => (
                                <div key={s.num} className="relative border-t-2 border-brand-500 pt-7">
                                    <p className="mb-4 font-mono text-[11px] font-medium uppercase tracking-[0.2em] text-brand-500">{s.num}</p>
                                    <h4 className="mb-2.5 text-xl font-semibold tracking-tight text-gray-900 dark:text-gray-100">{s.title}</h4>
                                    <p className="text-sm leading-relaxed text-gray-500 dark:text-gray-400">{s.description}</p>
                                </div>
                            ))}
                        </div>
                    </div>
                </section>

                {/* Testimonial */}
                <section className="relative overflow-hidden border-t border-b border-gray-200 bg-gray-50 py-28 dark:border-white/8 dark:bg-zinc-900/50 md:py-32">
                    <div className="pointer-events-none absolute left-1/2 top-1/2 h-[500px] w-[800px] -translate-x-1/2 -translate-y-1/2 bg-[radial-gradient(ellipse,rgba(16,211,161,0.08),transparent_70%)]" />
                    <div className="relative mx-auto max-w-3xl px-5 text-center md:px-10">
                        <span className="mb-6 block text-8xl font-bold leading-[0.3] text-brand-500 opacity-60">"</span>
                        <p className="mb-10 text-2xl font-light italic leading-snug tracking-tight text-gray-900 dark:text-gray-100 md:text-3xl lg:text-4xl">
                            For the first time, we actually know which pages and cities are killing our margin. Nabawasan RTS namin ng 9 points in two months — and that's literally six figures back in our pocket.
                        </p>
                        <div>
                            <p className="text-lg font-semibold text-gray-900 dark:text-gray-100">Patricia L.</p>
                            <p className="font-mono text-[11px] uppercase tracking-[0.2em] text-gray-400 dark:text-gray-500">
                                Founder · Fashion brand, 4,000 orders/month
                            </p>
                        </div>
                    </div>
                </section>

                {/* FAQ */}
                <section id="faq" className="py-24 md:py-28">
                    <div className="mx-auto max-w-[1200px] px-5 md:px-10">
                        <p className="mb-5 font-mono text-[11px] uppercase tracking-[0.2em] text-brand-500">— Questions, answered</p>
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
                    <div className="pointer-events-none absolute left-1/2 top-1/2 h-[600px] w-full -translate-x-1/2 -translate-y-1/2 bg-[radial-gradient(ellipse,rgba(16,211,161,0.15),transparent_60%)]" />
                    <div className="relative mx-auto max-w-[1200px] px-5 text-center md:px-10">
                        <h2 className="mx-auto mb-6 max-w-3xl text-4xl font-bold tracking-tight md:text-5xl lg:text-6xl">
                            Your RTS is a number.{' '}
                            <span className="text-brand-500 italic">Let's move it.</span>
                        </h2>
                        <p className="mx-auto mb-10 max-w-xl text-lg leading-relaxed text-gray-500 dark:text-gray-400">
                            14 days free. No credit card. First insight in under five minutes. Handa ka na ba?
                        </p>
                        <div className="flex flex-col items-center justify-center gap-3.5 sm:flex-row">
                            <Link
                                href={ctaHref}
                                className="inline-flex h-12 items-center gap-2.5 rounded-xl bg-brand-500! px-8 text-base font-semibold text-white shadow-lg shadow-brand-500/20 transition-all hover:bg-brand-600 hover:-translate-y-0.5"
                            >
                                Start free trial
                                <svg width="16" height="12" viewBox="0 0 16 12" fill="none"><path d="M1 6h14m0 0L10 1m5 5l-5 5" stroke="currentColor" strokeWidth="2" strokeLinecap="round" /></svg>
                            </Link>
                            <a
                                href="#"
                                className="inline-flex h-12 items-center rounded-xl border border-gray-200 bg-white px-8 text-base font-semibold text-gray-700 transition-all hover:border-brand-300 hover:text-brand-600 dark:border-white/10 dark:bg-zinc-900 dark:text-gray-300 dark:hover:border-brand-500/40 dark:hover:text-brand-400"
                            >
                                Book a demo call
                            </a>
                        </div>
                    </div>
                </section>

                {/* Footer */}
                <footer className="border-t border-gray-200 dark:border-white/8">
                    <div className="mx-auto max-w-[1200px] px-5 py-16 md:px-10">
                        <div className="mb-12 grid gap-10 md:grid-cols-[2fr_1fr_1fr_1fr] md:gap-12">
                            <div>
                                <div className="mb-3 flex items-center gap-3">
                                    <img src="/img/logo/artemis.png" alt="Artemis" className="h-8 w-8 object-contain" />
                                    <span className="text-2xl font-semibold tracking-tight">Artemis</span>
                                </div>
                                <p className="max-w-xs text-sm leading-relaxed text-gray-500 dark:text-gray-400">
                                    The analytics & automation platform for Philippine COD e-commerce. From order to delivery — every metric that matters.
                                </p>
                            </div>
                            <div>
                                <h5 className="mb-4 font-mono text-[10px] uppercase tracking-[0.2em] text-gray-400 dark:text-gray-600">Product</h5>
                                <div className="flex flex-col gap-2">
                                    <a href="#features" className="text-sm text-gray-600 transition-colors hover:text-brand-500 dark:text-gray-300">Features</a>
                                    <a href="#pricing" className="text-sm text-gray-600 transition-colors hover:text-brand-500 dark:text-gray-300">Pricing</a>
                                    <a href="#how" className="text-sm text-gray-600 transition-colors hover:text-brand-500 dark:text-gray-300">How it works</a>
                                    <a href="#faq" className="text-sm text-gray-600 transition-colors hover:text-brand-500 dark:text-gray-300">FAQ</a>
                                    <Link href="/rts-calculator" className="text-sm text-gray-600 transition-colors hover:text-brand-500 dark:text-gray-300">RTS Calculator</Link>
                                </div>
                            </div>
                            <div>
                                <h5 className="mb-4 font-mono text-[10px] uppercase tracking-[0.2em] text-gray-400 dark:text-gray-600">Company</h5>
                                <div className="flex flex-col gap-2">
                                    <a href="#" className="text-sm text-gray-600 transition-colors hover:text-brand-500 dark:text-gray-300">About</a>
                                    <a href="#" className="text-sm text-gray-600 transition-colors hover:text-brand-500 dark:text-gray-300">Blog</a>
                                    <a href="#" className="text-sm text-gray-600 transition-colors hover:text-brand-500 dark:text-gray-300">Careers</a>
                                    <a href="#" className="text-sm text-gray-600 transition-colors hover:text-brand-500 dark:text-gray-300">Contact</a>
                                </div>
                            </div>
                            <div>
                                <h5 className="mb-4 font-mono text-[10px] uppercase tracking-[0.2em] text-gray-400 dark:text-gray-600">Legal</h5>
                                <div className="flex flex-col gap-2">
                                    <a href="#" className="text-sm text-gray-600 transition-colors hover:text-brand-500 dark:text-gray-300">Privacy</a>
                                    <a href="#" className="text-sm text-gray-600 transition-colors hover:text-brand-500 dark:text-gray-300">Terms</a>
                                    <a href="#" className="text-sm text-gray-600 transition-colors hover:text-brand-500 dark:text-gray-300">Data policy</a>
                                    <a href="#" className="text-sm text-gray-600 transition-colors hover:text-brand-500 dark:text-gray-300">Security</a>
                                </div>
                            </div>
                        </div>
                        <div className="flex flex-col items-center justify-between gap-3 border-t border-gray-200 pt-6 font-mono text-[11px] tracking-[0.1em] text-gray-400 dark:border-white/8 dark:text-gray-600 md:flex-row">
                            <span>&copy; {new Date().getFullYear()} Artemis. All rights reserved.</span>
                            <span>Built in the Philippines</span>
                        </div>
                    </div>
                </footer>
            </div>
        </>
    );
}
