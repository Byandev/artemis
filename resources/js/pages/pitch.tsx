import AppearanceToggleDropdown from '@/components/appearance-dropdown';
import { type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { useState } from 'react';

const slides = [
    { id: 'cover', label: 'Cover' },
    { id: 'problem', label: 'Problem' },
    { id: 'numbers', label: 'The Numbers' },
    { id: 'solution', label: 'Solution' },
    { id: 'how', label: 'How It Works' },
    { id: 'pricing', label: 'Pricing' },
    { id: 'revenue', label: 'Revenue' },
    { id: 'use-cases', label: 'Use Cases' },
    { id: 'market', label: 'Market' },
    { id: 'traction', label: 'Traction' },
    { id: 'milestones', label: 'Milestones' },
    { id: 'ask', label: 'The Ask' },
];

function Slide({ children, id }: { children: React.ReactNode; id: string }) {
    return (
        <section id={id} className="relative flex min-h-screen flex-col justify-center px-6 py-20 md:px-16 lg:px-24">
            {children}
        </section>
    );
}

function SlideTag({ children }: { children: React.ReactNode }) {
    return (
        <p className="mb-5 font-mono text-[10px] font-semibold uppercase tracking-[0.2em] text-brand-500">{children}</p>
    );
}

function SlideTitle({ children }: { children: React.ReactNode }) {
    return (
        <h2 className="mb-6 max-w-3xl text-[clamp(1.75rem,4.5vw,3.5rem)] font-bold leading-[1.08] tracking-tight">{children}</h2>
    );
}

function StatCard({ num, label, sub }: { num: string; label: string; sub?: string }) {
    return (
        <div className="rounded-2xl border border-gray-200/80 bg-white p-6 dark:border-white/6 dark:bg-zinc-900/80 sm:p-8">
            <div className="mb-2 text-3xl font-bold tracking-tight text-brand-500 sm:text-4xl">{num}</div>
            <p className="text-sm font-semibold text-gray-900 dark:text-gray-100">{label}</p>
            {sub && <p className="mt-1 text-[12px] text-gray-400 dark:text-gray-500">{sub}</p>}
        </div>
    );
}

export default function PitchDeck() {
    const { auth } = usePage<SharedData>().props;
    const [navOpen, setNavOpen] = useState(false);

    return (
        <>
            <Head title="Artemis — Pitch Deck">
                <meta name="robots" content="noindex, nofollow" />
            </Head>

            <div className="relative min-h-screen overflow-hidden bg-white text-gray-900 dark:bg-zinc-950 dark:text-gray-100">
                {/* Background */}
                <div className="pointer-events-none fixed -top-60 left-1/2 h-[700px] w-[1100px] -translate-x-1/2 bg-[radial-gradient(ellipse,rgba(16,211,161,0.06),transparent_65%)]" />
                <div className="pointer-events-none fixed inset-0 bg-[linear-gradient(rgba(0,0,0,0.018)_1px,transparent_1px),linear-gradient(90deg,rgba(0,0,0,0.018)_1px,transparent_1px)] bg-[size:60px_60px] dark:bg-[linear-gradient(rgba(255,255,255,0.01)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,0.01)_1px,transparent_1px)]" />

                {/* Nav */}
                <nav className="fixed top-0 z-50 w-full border-b border-gray-200/80 bg-white/80 backdrop-blur-2xl dark:border-white/6 dark:bg-zinc-950/80">
                    <div className="mx-auto flex max-w-[1400px] items-center justify-between px-6 py-3 md:px-16">
                        <Link href="/" className="flex items-center gap-2.5">
                            <img src="/img/logo/artemis.png" alt="Artemis" className="h-8 w-8 object-contain" />
                            <span className="text-lg font-semibold tracking-tight">Artemis</span>
                            <span className="ml-2 rounded-full border border-gray-200 bg-gray-50 px-2 py-0.5 font-mono text-[9px] uppercase tracking-wider text-gray-400 dark:border-white/8 dark:bg-white/5 dark:text-gray-500">Pitch Deck</span>
                        </Link>
                        <div className="flex items-center gap-4">
                            {/* Slide nav toggle */}
                            <button
                                onClick={() => setNavOpen(!navOpen)}
                                className="hidden items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-1.5 font-mono text-[10px] uppercase tracking-wider text-gray-500 transition-all hover:border-brand-300 hover:text-brand-600 dark:border-white/8 dark:bg-zinc-900 dark:text-gray-400 md:inline-flex"
                            >
                                {navOpen ? 'Close' : 'Slides'}
                                <span className="text-gray-300 dark:text-gray-600">{slides.length}</span>
                            </button>
                            <AppearanceToggleDropdown />
                        </div>
                    </div>
                    {/* Slide nav dropdown */}
                    {navOpen && (
                        <div className="border-t border-gray-100 bg-white/95 px-6 py-3 backdrop-blur-xl dark:border-white/5 dark:bg-zinc-950/95 md:px-16">
                            <div className="flex flex-wrap gap-2">
                                {slides.map((s, i) => (
                                    <a
                                        key={s.id}
                                        href={`#${s.id}`}
                                        onClick={() => setNavOpen(false)}
                                        className="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 px-2.5 py-1.5 text-[11px] font-medium text-gray-600 transition-all hover:border-brand-300 hover:text-brand-600 dark:border-white/8 dark:text-gray-400 dark:hover:border-brand-500/30 dark:hover:text-brand-400"
                                    >
                                        <span className="font-mono text-[9px] text-gray-300 dark:text-gray-600">{String(i + 1).padStart(2, '0')}</span>
                                        {s.label}
                                    </a>
                                ))}
                            </div>
                        </div>
                    )}
                </nav>

                {/* ── SLIDE 1: Cover ── */}
                <Slide id="cover">
                    <div className="mx-auto max-w-4xl text-center">
                        <div className="mb-8 inline-flex items-center gap-2.5 rounded-full border border-brand-200/60 bg-brand-50/80 px-4 py-2 dark:border-brand-500/15 dark:bg-brand-500/8">
                            <span className="h-1.5 w-1.5 rounded-full bg-brand-500 shadow-[0_0_6px_var(--color-brand-500)]" />
                            <span className="font-mono text-[10px] font-semibold uppercase tracking-[0.2em] text-brand-700 dark:text-brand-400">Partner Presentation</span>
                        </div>
                        <div className="mb-8 flex justify-center">
                            <img src="/img/logo/artemis.png" alt="Artemis" className="h-20 w-20 object-contain" />
                        </div>
                        <h1 className="mb-6 text-[clamp(2.5rem,6vw,5rem)] font-bold leading-[0.95] tracking-tight">
                            Hunt down{' '}
                            <span className="bg-gradient-to-br from-brand-500 to-brand-700 bg-clip-text italic text-transparent">RTS.</span>
                            <br />
                            Protect your margin.
                        </h1>
                        <p className="mx-auto mb-8 max-w-xl text-lg leading-relaxed text-gray-500 dark:text-gray-400">
                            The 1st analytics & automation platform for Philippine COD e-commerce.
                        </p>
                        <div className="flex items-center justify-center gap-6 font-mono text-[10px] uppercase tracking-wider text-gray-400 dark:text-gray-500">
                            <span>Analytics</span>
                            <span className="text-brand-500">·</span>
                            <span>Parcel Journey</span>
                            <span className="text-brand-500">·</span>
                            <span>RTS Reduction</span>
                            <span className="text-brand-500">·</span>
                            <span>Pancake POS</span>
                        </div>
                    </div>
                </Slide>

                {/* ── SLIDE 2: Problem ── */}
                <Slide id="problem">
                    <div className="mx-auto max-w-4xl">
                        <SlideTag>01 — The Problem</SlideTag>
                        <SlideTitle>
                            Philippine COD e-commerce is massive — but <span className="text-brand-500 italic">operationally broken.</span>
                        </SlideTitle>
                        <p className="mb-12 max-w-2xl text-lg leading-relaxed text-gray-500 dark:text-gray-400">
                            Over 60% of PH online purchases are COD. But sellers are losing 20-40% of every shipment to RTS — and most don't even know their real number.
                        </p>
                        <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                            <StatCard num="60%+" label="of PH orders are COD" sub="Cash-on-delivery remains dominant" />
                            <StatCard num="20-40%" label="Average RTS rate" sub="Failed deliveries, returned parcels" />
                            <StatCard num="₱430" label="Cost per failed parcel" sub="Shipping + packaging + margin" />
                            <StatCard num="0" label="Analytics tools built for PH COD" sub="Artemis is the first" />
                        </div>
                    </div>
                </Slide>

                {/* ── SLIDE 3: The Numbers ── */}
                <Slide id="numbers">
                    <div className="mx-auto max-w-4xl">
                        <SlideTag>02 — The Numbers</SlideTag>
                        <SlideTitle>
                            Every failed parcel is <span className="text-brand-500 italic">pure loss.</span>
                        </SlideTitle>
                        <p className="mb-12 max-w-2xl text-lg leading-relaxed text-gray-500 dark:text-gray-400">
                            A typical mid-sized seller (3,000 orders/month) at 30% RTS bleeds ₱387,000 every month. That's ₱4.6M per year — gone.
                        </p>
                        <div className="overflow-hidden rounded-2xl border border-gray-200/80 bg-white dark:border-white/6 dark:bg-zinc-900/80">
                            <div className="border-b border-gray-100 bg-gray-50/60 px-6 py-4 dark:border-white/5 dark:bg-white/[0.02]">
                                <p className="font-mono text-[10px] uppercase tracking-wider text-gray-400 dark:text-gray-500">Cost anatomy of a single RTS parcel</p>
                            </div>
                            <div className="divide-y divide-gray-100 dark:divide-white/5">
                                {[
                                    { label: 'Wasted ad spend', value: '₱50', desc: 'You paid to acquire this customer' },
                                    { label: 'Forward shipping', value: '₱120', desc: 'Paid to send it' },
                                    { label: 'Return shipping', value: '₱100', desc: 'Paid to get it back' },
                                    { label: 'Packaging', value: '₱15', desc: 'Box, tape, filler — wasted' },
                                    { label: 'Damage loss', value: '₱20', desc: '10% come back unsellable' },
                                    { label: 'Unrealized profit', value: '₱175', desc: 'The sale that didn\'t happen' },
                                ].map((r) => (
                                    <div key={r.label} className="flex items-center justify-between px-6 py-3.5">
                                        <div>
                                            <p className="text-[14px] font-medium text-gray-900 dark:text-gray-100">{r.label}</p>
                                            <p className="text-[12px] text-gray-400 dark:text-gray-500">{r.desc}</p>
                                        </div>
                                        <span className="font-mono text-[14px] font-semibold text-gray-900 dark:text-gray-100">{r.value}</span>
                                    </div>
                                ))}
                                <div className="flex items-center justify-between bg-brand-50/50 px-6 py-4 dark:bg-brand-500/5">
                                    <p className="text-[15px] font-bold text-gray-900 dark:text-gray-100">Total per RTS parcel</p>
                                    <span className="font-mono text-lg font-bold text-brand-600 dark:text-brand-400">₱480</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </Slide>

                {/* ── SLIDE 4: Solution ── */}
                <Slide id="solution">
                    <div className="mx-auto max-w-4xl">
                        <SlideTag>03 — The Solution</SlideTag>
                        <SlideTitle>
                            Artemis connects to <span className="text-brand-500 italic">Pancake POS</span> and gives sellers the visibility they need.
                        </SlideTitle>
                        <p className="mb-12 max-w-2xl text-lg leading-relaxed text-gray-500 dark:text-gray-400">
                            Purpose-built for Philippine COD. Not a generic dashboard — a complete analytics and automation platform.
                        </p>
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            {[
                                { title: 'Sales Analytics', desc: 'Revenue, orders, AOV, trends across all pages' },
                                { title: 'Delivery Analytics', desc: 'Success rates, attempt counts, courier performance' },
                                { title: 'RTS Analytics', desc: 'Return rates by page, product, city, time' },
                                { title: 'Parcel Journey', desc: 'Per-order tracking + auto notifications via Chat/SMS' },
                                { title: 'Operations Insights', desc: 'Fulfillment lead times, bottleneck detection' },
                                { title: 'Multi-workspace', desc: 'Role-based access for teams with granular permissions' },
                            ].map((f) => (
                                <div key={f.title} className="rounded-2xl border border-gray-200/80 bg-white p-6 dark:border-white/6 dark:bg-zinc-900/80">
                                    <div className="mb-3 flex h-9 w-9 items-center justify-center rounded-xl border border-brand-200/60 bg-brand-50/80 dark:border-brand-500/20 dark:bg-brand-500/10">
                                        <svg className="h-4 w-4 text-brand-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}><path strokeLinecap="round" strokeLinejoin="round" d="M5 13l4 4L19 7" /></svg>
                                    </div>
                                    <h3 className="mb-1 text-[14px] font-bold text-gray-900 dark:text-gray-100">{f.title}</h3>
                                    <p className="text-[13px] leading-relaxed text-gray-500 dark:text-gray-400">{f.desc}</p>
                                </div>
                            ))}
                        </div>
                    </div>
                </Slide>

                {/* ── SLIDE 5: How It Works ── */}
                <Slide id="how">
                    <div className="mx-auto max-w-4xl">
                        <SlideTag>04 — How It Works</SlideTag>
                        <SlideTitle>
                            Signup to first insight — <span className="text-brand-500 italic">under 5 minutes.</span>
                        </SlideTitle>
                        <div className="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                            {[
                                { num: '01', title: 'Sign up', desc: 'Create workspace. Name, email, done. No credit card.' },
                                { num: '02', title: 'Connect Pancake', desc: 'Link your Pancake POS page. We pull data automatically.' },
                                { num: '03', title: 'See your numbers', desc: 'Real RTS rate, bleed, problem areas — within minutes.' },
                                { num: '04', title: 'Act on it', desc: 'Enable notifications, follow insights, watch RTS drop.' },
                            ].map((s) => (
                                <div key={s.num} className="border-t-2 border-brand-500 pt-6">
                                    <p className="mb-3 font-mono text-[11px] font-semibold uppercase tracking-[0.2em] text-brand-500">{s.num}</p>
                                    <h4 className="mb-2 text-lg font-bold tracking-tight">{s.title}</h4>
                                    <p className="text-[13px] leading-relaxed text-gray-500 dark:text-gray-400">{s.desc}</p>
                                </div>
                            ))}
                        </div>
                    </div>
                </Slide>

                {/* ── SLIDE 6: Pricing ── */}
                <Slide id="pricing">
                    <div className="mx-auto max-w-5xl">
                        <SlideTag>05 — Pricing Model</SlideTag>
                        <SlideTitle>
                            Base subscription + <span className="text-brand-500 italic">usage-based</span> Parcel Journey.
                        </SlideTitle>
                        <p className="mb-10 max-w-2xl text-base leading-relaxed text-gray-500 dark:text-gray-400">
                            Tiers by monthly order volume. Parcel Journey charged per delivered order only — ₱1 per 5 delivered. Unlimited Pancake pages on all plans.
                        </p>

                        <div className="grid gap-4 lg:grid-cols-4">
                            {[
                                { name: 'Free Trial', price: '₱0', period: '14 days', orders: '500', retention: '1 month', pj: 'Chat only (free)', support: 'Chat', team: '1 user', featured: false },
                                { name: 'Starter', price: '₱1,499', period: '/mo', orders: '3,000', retention: '3 months', pj: '₱1/5 delivered', support: 'Chat', team: '2 users', featured: false },
                                { name: 'Growth', price: '₱3,999', period: '/mo', orders: '10,000', retention: '6 months', pj: '₱1/5 delivered', support: 'Priority', team: '5 users', featured: true },
                                { name: 'Scale', price: '₱9,999', period: '/mo', orders: '30,000', retention: '12 months', pj: '₱1/5 delivered', support: 'Dedicated', team: 'Unlimited', featured: false },
                            ].map((t) => (
                                <div key={t.name} className={`flex flex-col rounded-2xl border p-6 ${t.featured ? 'border-brand-500/80 bg-gradient-to-b from-brand-50/90 to-white shadow-xl shadow-brand-500/8 dark:border-brand-500/60 dark:from-brand-500/[0.06] dark:to-zinc-900' : 'border-gray-200/80 bg-white dark:border-white/6 dark:bg-zinc-900/80'}`}>
                                    {t.featured && <span className="mb-4 inline-flex self-start rounded-full bg-brand-500 px-2.5 py-0.5 font-mono text-[9px] font-semibold uppercase tracking-[0.2em] text-white">Popular</span>}
                                    <p className="font-mono text-[10px] uppercase tracking-wider text-gray-400 dark:text-gray-500">{t.name}</p>
                                    <div className="mt-2 mb-4 flex items-baseline gap-1">
                                        <span className="text-3xl font-bold tracking-tight">{t.price}</span>
                                        <span className="text-sm text-gray-400">{t.period}</span>
                                    </div>
                                    <div className="flex-1 space-y-2.5 text-[12px] text-gray-600 dark:text-gray-400">
                                        <div className="flex justify-between"><span>Orders</span><span className="font-semibold text-gray-900 dark:text-gray-100">{t.orders}/mo</span></div>
                                        <div className="flex justify-between"><span>Retention</span><span className="font-semibold text-gray-900 dark:text-gray-100">{t.retention}</span></div>
                                        <div className="flex justify-between"><span>Parcel Journey</span><span className="font-semibold text-gray-900 dark:text-gray-100">{t.pj}</span></div>
                                        <div className="flex justify-between"><span>Support</span><span className="font-semibold text-gray-900 dark:text-gray-100">{t.support}</span></div>
                                        <div className="flex justify-between"><span>Team</span><span className="font-semibold text-gray-900 dark:text-gray-100">{t.team}</span></div>
                                    </div>
                                </div>
                            ))}
                        </div>

                        <div className="mt-8 rounded-2xl border border-gray-200/80 bg-gray-50/60 p-6 dark:border-white/6 dark:bg-white/[0.02]">
                            <p className="mb-3 font-mono text-[10px] font-semibold uppercase tracking-wider text-brand-500">Why this pricing works</p>
                            <div className="grid gap-4 text-[13px] leading-relaxed text-gray-600 dark:text-gray-400 sm:grid-cols-3">
                                <div><strong className="text-gray-900 dark:text-gray-100">Low barrier.</strong> ₱1,499/mo is less than 1 day of RTS bleed for most sellers.</div>
                                <div><strong className="text-gray-900 dark:text-gray-100">Aligned incentives.</strong> Parcel Journey charged on delivered orders only — we succeed when the seller succeeds.</div>
                                <div><strong className="text-gray-900 dark:text-gray-100">Natural upsell.</strong> Sellers grow orders → hit tier cap → upgrade. Usage-based PJ scales with volume.</div>
                            </div>
                        </div>
                    </div>
                </Slide>

                {/* ── SLIDE 7: Revenue ── */}
                <Slide id="revenue">
                    <div className="mx-auto max-w-4xl">
                        <SlideTag>06 — Revenue Model</SlideTag>
                        <SlideTitle>
                            ₱2,244 average revenue <span className="text-brand-500 italic">per customer per month.</span>
                        </SlideTitle>

                        <div className="mb-10 grid gap-5 sm:grid-cols-3">
                            <div className="rounded-2xl border border-gray-200/80 bg-white p-6 dark:border-white/6 dark:bg-zinc-900/80">
                                <p className="mb-1 font-mono text-[10px] uppercase tracking-wider text-gray-400 dark:text-gray-500">Starter total</p>
                                <p className="text-2xl font-bold tracking-tight">₱1,919<span className="text-sm font-normal text-gray-400">/mo</span></p>
                                <p className="mt-1 text-[12px] text-gray-400">₱1,499 base + ₱420 PJ</p>
                            </div>
                            <div className="rounded-2xl border-2 border-brand-500/80 bg-gradient-to-b from-brand-50/90 to-white p-6 shadow-lg shadow-brand-500/5 dark:border-brand-500/60 dark:from-brand-500/[0.06] dark:to-zinc-900">
                                <p className="mb-1 font-mono text-[10px] uppercase tracking-wider text-brand-600 dark:text-brand-400">Growth total</p>
                                <p className="text-2xl font-bold tracking-tight">₱5,399<span className="text-sm font-normal text-gray-400">/mo</span></p>
                                <p className="mt-1 text-[12px] text-gray-400">₱3,999 base + ₱1,400 PJ</p>
                            </div>
                            <div className="rounded-2xl border border-gray-200/80 bg-white p-6 dark:border-white/6 dark:bg-zinc-900/80">
                                <p className="mb-1 font-mono text-[10px] uppercase tracking-wider text-gray-400 dark:text-gray-500">Scale total</p>
                                <p className="text-2xl font-bold tracking-tight">₱14,199<span className="text-sm font-normal text-gray-400">/mo</span></p>
                                <p className="mt-1 text-[12px] text-gray-400">₱9,999 base + ₱4,200 PJ</p>
                            </div>
                        </div>

                        <div className="rounded-2xl border border-gray-200/80 bg-white p-6 dark:border-white/6 dark:bg-zinc-900/80 sm:p-8">
                            <p className="mb-4 font-mono text-[10px] font-semibold uppercase tracking-wider text-brand-500">ROI — Artemis pays for itself at less than 1% RTS reduction</p>
                            <div className="space-y-3">
                                {[
                                    { plan: 'Starter', orders: '3,000', bleed: '₱387,000', cost: '₱1,919', breakeven: '0.5%' },
                                    { plan: 'Growth', orders: '10,000', bleed: '₱1,290,000', cost: '₱5,399', breakeven: '0.4%' },
                                    { plan: 'Scale', orders: '30,000', bleed: '₱3,870,000', cost: '₱14,199', breakeven: '0.4%' },
                                ].map((r) => (
                                    <div key={r.plan} className="flex flex-wrap items-center gap-x-6 gap-y-1 rounded-xl border border-gray-100 px-4 py-3 text-[13px] dark:border-white/5">
                                        <span className="w-16 font-semibold text-gray-900 dark:text-gray-100">{r.plan}</span>
                                        <span className="text-gray-400">{r.orders} orders</span>
                                        <span className="text-gray-400">Monthly bleed: <span className="font-semibold text-gray-700 dark:text-gray-300">{r.bleed}</span></span>
                                        <span className="text-gray-400">Artemis: <span className="font-semibold text-gray-700 dark:text-gray-300">{r.cost}</span></span>
                                        <span className="ml-auto font-mono text-[11px] font-semibold text-brand-500">Break-even: {r.breakeven} RTS reduction</span>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </div>
                </Slide>

                {/* ── SLIDE 8: Use Cases ── */}
                <Slide id="use-cases">
                    <div className="mx-auto max-w-4xl">
                        <SlideTag>07 — Use Cases</SlideTag>
                        <SlideTitle>
                            Real scenarios, <span className="text-brand-500 italic">real ROI.</span>
                        </SlideTitle>

                        <div className="grid gap-5 md:grid-cols-2">
                            {[
                                {
                                    title: 'Small Starter Seller',
                                    profile: '1 page · 1,500 orders/mo · 35% RTS',
                                    plan: 'Starter — ₱1,919/mo',
                                    before: '35% RTS → ₱225k bleed',
                                    after: '28% RTS → ₱180k bleed',
                                    savings: '₱45,150/mo saved',
                                    roi: '22x ROI',
                                },
                                {
                                    title: 'Multi-Page Growth Seller',
                                    profile: '5 pages · 8,000 orders/mo · 28% RTS',
                                    plan: 'Growth — ₱5,399/mo',
                                    before: '28% RTS → ₱963k bleed',
                                    after: '22% RTS → ₱756k bleed',
                                    savings: '₱206,400/mo saved',
                                    roi: '38x ROI',
                                },
                                {
                                    title: 'Scale Operation',
                                    profile: '12 pages · 25,000 orders/mo · 24% RTS',
                                    plan: 'Scale — ₱14,199/mo',
                                    before: '24% RTS → ₱2.58M bleed',
                                    after: '19% RTS → ₱2.04M bleed',
                                    savings: '₱537,500/mo saved',
                                    roi: '37x ROI',
                                },
                                {
                                    title: 'The Skeptic Free Trial',
                                    profile: '2 pages · 800 orders/mo · 30% RTS',
                                    plan: 'Free → Starter convert',
                                    before: 'Day 1: "RTS is probably 25%"',
                                    after: 'Day 1: Actual RTS is 33%',
                                    savings: 'Day 14: RTS drops 3pts',
                                    roi: 'Converts to paid',
                                },
                            ].map((c) => (
                                <div key={c.title} className="overflow-hidden rounded-2xl border border-gray-200/80 bg-white dark:border-white/6 dark:bg-zinc-900/80">
                                    <div className="border-b border-gray-100 bg-gray-50/60 px-6 py-4 dark:border-white/5 dark:bg-white/[0.02]">
                                        <h3 className="text-[14px] font-bold text-gray-900 dark:text-gray-100">{c.title}</h3>
                                        <p className="mt-0.5 font-mono text-[10px] uppercase tracking-wider text-gray-400 dark:text-gray-500">{c.profile}</p>
                                    </div>
                                    <div className="space-y-2.5 px-6 py-5 text-[13px]">
                                        <div className="flex justify-between"><span className="text-gray-400">Plan</span><span className="font-semibold text-gray-900 dark:text-gray-100">{c.plan}</span></div>
                                        <div className="flex justify-between"><span className="text-gray-400">Before</span><span className="text-gray-600 dark:text-gray-300">{c.before}</span></div>
                                        <div className="flex justify-between"><span className="text-gray-400">After 90 days</span><span className="text-gray-600 dark:text-gray-300">{c.after}</span></div>
                                        <div className="flex justify-between border-t border-gray-100 pt-2.5 dark:border-white/5">
                                            <span className="font-semibold text-brand-600 dark:text-brand-400">{c.savings}</span>
                                            <span className="font-mono text-[11px] font-bold text-brand-500">{c.roi}</span>
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                </Slide>

                {/* ── SLIDE 9: Market ── */}
                <Slide id="market">
                    <div className="mx-auto max-w-4xl">
                        <SlideTag>08 — Market</SlideTag>
                        <SlideTitle>
                            First mover in an <span className="text-brand-500 italic">unserved niche.</span>
                        </SlideTitle>

                        <div className="mb-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                            <StatCard num="5-10k" label="Active Pancake sellers" sub="Doing 500+ orders/mo" />
                            <StatCard num="₱2,244" label="ARPU" sub="Average revenue per customer" />
                            <StatCard num="₱13.4M" label="ARR at 5% capture" sub="500 customers" />
                            <StatCard num="0" label="Direct competitors" sub="We're building the category" />
                        </div>

                        <div className="grid gap-5 sm:grid-cols-3">
                            {[
                                { title: 'Moat: Integration depth', desc: 'First to build deep analytics on Pancake POS data. Deep integration = switching cost.' },
                                { title: 'Moat: COD-specific', desc: 'Parcel Journey, RTS analytics, delivery scoring — features generic tools don\'t have.' },
                                { title: 'Moat: Network effects', desc: 'More sellers = better benchmarks, better recommendations, stronger value prop.' },
                            ].map((m) => (
                                <div key={m.title} className="rounded-2xl border border-gray-200/80 bg-white p-6 dark:border-white/6 dark:bg-zinc-900/80">
                                    <h3 className="mb-2 text-[14px] font-bold text-gray-900 dark:text-gray-100">{m.title}</h3>
                                    <p className="text-[13px] leading-relaxed text-gray-500 dark:text-gray-400">{m.desc}</p>
                                </div>
                            ))}
                        </div>
                    </div>
                </Slide>

                {/* ── SLIDE 10: Traction ── */}
                <Slide id="traction">
                    <div className="mx-auto max-w-4xl">
                        <SlideTag>09 — Unit Economics</SlideTag>
                        <SlideTitle>
                            Healthy SaaS economics <span className="text-brand-500 italic">from day one.</span>
                        </SlideTitle>

                        <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                            <StatCard num="~₱0" label="Early CAC" sub="Organic: FB groups, blog, word of mouth" />
                            <StatCard num="₱3-5k" label="Scaled CAC" sub="Targeted ads to Pancake sellers" />
                            <StatCard num="₱44,880" label="LTV" sub="20-month avg lifetime × ₱2,244 ARPU" />
                            <StatCard num="9-15x" label="LTV:CAC ratio" sub="Healthy = 3x+. We're well above." />
                        </div>

                        <div className="mt-8 rounded-2xl border border-gray-200/80 bg-white p-6 dark:border-white/6 dark:bg-zinc-900/80 sm:p-8">
                            <p className="mb-5 font-mono text-[10px] font-semibold uppercase tracking-wider text-brand-500">Year 1 revenue scenarios</p>
                            <div className="grid gap-4 sm:grid-cols-3">
                                {[
                                    { label: 'Conservative', customers: '120', mrr: '₱295k', arr: '₱3.5M', year: '₱1.9M' },
                                    { label: 'Moderate', customers: '250', mrr: '₱611k', arr: '₱7.3M', year: '₱3.8M' },
                                    { label: 'Optimistic', customers: '400', mrr: '₱1.0M', arr: '₱12.0M', year: '₱6.2M' },
                                ].map((s) => (
                                    <div key={s.label} className={`rounded-xl border p-5 ${s.label === 'Moderate' ? 'border-brand-500/60 bg-brand-50/30 dark:border-brand-500/40 dark:bg-brand-500/5' : 'border-gray-100 dark:border-white/5'}`}>
                                        <p className={`mb-3 font-mono text-[10px] uppercase tracking-wider ${s.label === 'Moderate' ? 'text-brand-600 dark:text-brand-400' : 'text-gray-400 dark:text-gray-500'}`}>{s.label}</p>
                                        <div className="space-y-1.5 text-[13px]">
                                            <div className="flex justify-between"><span className="text-gray-400">Customers</span><span className="font-semibold text-gray-900 dark:text-gray-100">{s.customers}</span></div>
                                            <div className="flex justify-between"><span className="text-gray-400">MRR (M12)</span><span className="font-semibold text-gray-900 dark:text-gray-100">{s.mrr}</span></div>
                                            <div className="flex justify-between"><span className="text-gray-400">ARR run rate</span><span className="font-semibold text-gray-900 dark:text-gray-100">{s.arr}</span></div>
                                            <div className="flex justify-between border-t border-gray-100 pt-1.5 dark:border-white/5"><span className="text-gray-400">Year 1 total</span><span className="font-bold text-brand-600 dark:text-brand-400">{s.year}</span></div>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </div>
                </Slide>

                {/* ── SLIDE 11: Milestones ── */}
                <Slide id="milestones">
                    <div className="mx-auto max-w-4xl">
                        <SlideTag>10 — Milestones</SlideTag>
                        <SlideTitle>
                            Clear path to <span className="text-brand-500 italic">profitability.</span>
                        </SlideTitle>

                        <div className="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                            {[
                                { milestone: 'Break-even', customers: '30', mrr: '₱73k', timeline: 'Month 2-3', desc: 'Covers infra, SMS, salary' },
                                { milestone: 'First hire', customers: '80', mrr: '₱188k', timeline: 'Month 5-6', desc: 'CS/support team member' },
                                { milestone: 'Sustainable', customers: '150', mrr: '₱362k', timeline: 'Month 8-9', desc: 'Small team, healthy margins' },
                                { milestone: 'Serious SaaS', customers: '300+', mrr: '₱730k+', timeline: 'Month 12+', desc: '₱8.8M+ ARR' },
                            ].map((m) => (
                                <div key={m.milestone} className="rounded-2xl border border-gray-200/80 bg-white p-6 dark:border-white/6 dark:bg-zinc-900/80">
                                    <p className="mb-1 font-mono text-[10px] uppercase tracking-wider text-brand-500">{m.timeline}</p>
                                    <h3 className="mb-3 text-lg font-bold tracking-tight">{m.milestone}</h3>
                                    <div className="space-y-1 text-[13px] text-gray-500 dark:text-gray-400">
                                        <p><span className="font-semibold text-gray-900 dark:text-gray-100">{m.customers}</span> customers</p>
                                        <p><span className="font-semibold text-gray-900 dark:text-gray-100">{m.mrr}</span> MRR</p>
                                        <p>{m.desc}</p>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                </Slide>

                {/* ── SLIDE 12: The Ask ── */}
                <Slide id="ask">
                    <div className="mx-auto max-w-4xl text-center">
                        <SlideTag>11 — The Ask</SlideTag>
                        <h2 className="mb-8 text-[clamp(2rem,5vw,3.5rem)] font-bold leading-[1.08] tracking-tight">
                            What we're looking for in a <span className="text-brand-500 italic">partner.</span>
                        </h2>

                        <div className="mb-12 grid gap-5 text-left sm:grid-cols-2">
                            {[
                                { title: 'Distribution', desc: 'Access to COD seller networks, Facebook groups, and communities where Pancake sellers gather.', icon: 'M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z' },
                                { title: 'Domain Expertise', desc: 'Operational insights from running COD businesses at scale. Help us prioritize what sellers actually need.', icon: 'M4.26 10.147a60.438 60.438 0 00-.491 6.347A48.62 48.62 0 0112 20.904a48.62 48.62 0 018.232-4.41 60.46 60.46 0 00-.491-6.347m-15.482 0a50.636 50.636 0 00-2.658-.813A59.906 59.906 0 0112 3.493a59.903 59.903 0 0110.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.717 50.717 0 0112 13.489a50.702 50.702 0 017.74-3.342M6.75 15a.75.75 0 100-1.5.75.75 0 000 1.5zm0 0v-3.675A55.378 55.378 0 0112 8.443m-7.007 11.55A5.981 5.981 0 006.75 15.75v-1.5' },
                                { title: 'Capital (optional)', desc: 'To accelerate hiring and growth beyond organic. Not required — the business is designed to be capital-efficient.', icon: 'M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z' },
                                { title: 'Strategic Advice', desc: 'Pricing validation, feature prioritization, go-to-market strategy — from people who understand the PH COD ecosystem.', icon: 'M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z' },
                            ].map((a) => (
                                <div key={a.title} className="rounded-2xl border border-gray-200/80 bg-white p-6 dark:border-white/6 dark:bg-zinc-900/80 sm:p-8">
                                    <div className="mb-4 flex h-10 w-10 items-center justify-center rounded-xl border border-brand-200/60 bg-brand-50/80 dark:border-brand-500/20 dark:bg-brand-500/10">
                                        <svg className="h-5 w-5 text-brand-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}><path strokeLinecap="round" strokeLinejoin="round" d={a.icon} /></svg>
                                    </div>
                                    <h3 className="mb-2 text-[15px] font-bold text-gray-900 dark:text-gray-100">{a.title}</h3>
                                    <p className="text-[13px] leading-relaxed text-gray-500 dark:text-gray-400">{a.desc}</p>
                                </div>
                            ))}
                        </div>

                        {/* Closing */}
                        <div className="mb-8 flex justify-center">
                            <img src="/img/logo/artemis.png" alt="Artemis" className="h-14 w-14 object-contain" />
                        </div>
                        <p className="mb-3 text-2xl font-bold tracking-tight sm:text-3xl">
                            Hunt down RTS. <span className="text-brand-500 italic">Protect your margin.</span>
                        </p>
                        <p className="mx-auto mb-8 max-w-md text-[15px] text-gray-500 dark:text-gray-400">
                            The 1st analytics & automation platform for Philippine COD e-commerce.
                        </p>
                        <div className="flex flex-col items-center justify-center gap-3 sm:flex-row">
                            <Link
                                href="/"
                                className="inline-flex h-11 w-full items-center justify-center gap-2 rounded-xl bg-brand-500! px-6 text-[14px] font-semibold text-white shadow-lg shadow-brand-500/20 transition-all hover:-translate-y-0.5 hover:bg-brand-600 sm:w-auto"
                            >
                                Visit artemis
                                <svg width="14" height="10" viewBox="0 0 16 12" fill="none"><path d="M1 6h14m0 0L10 1m5 5l-5 5" stroke="currentColor" strokeWidth="2" strokeLinecap="round" /></svg>
                            </Link>
                            <Link
                                href="/contact"
                                className="inline-flex h-11 w-full items-center justify-center rounded-xl border border-gray-200 bg-white px-6 text-[14px] font-semibold text-gray-700 transition-all hover:border-brand-300 hover:text-brand-600 sm:w-auto dark:border-white/10 dark:bg-zinc-900 dark:text-gray-300"
                            >
                                Get in touch
                            </Link>
                        </div>
                    </div>
                </Slide>
            </div>
        </>
    );
}
