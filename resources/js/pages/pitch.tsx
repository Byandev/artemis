import AppearanceToggleDropdown from '@/components/appearance-dropdown';
import { type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';

const SLIDES = [
    { id: 'cover', label: 'Cover' },
    { id: 'problem', label: 'The Problem' },
    { id: 'numbers', label: 'Your RTS Cost' },
    { id: 'solution', label: 'Artemis' },
    { id: 'how', label: 'How It Works' },
    { id: 'results', label: 'Results' },
    { id: 'pricing', label: 'Pricing' },
    { id: 'roi', label: 'Your ROI' },
    { id: 'free-trial', label: 'Free Trial' },
];

function Reveal({ children, active, delay = 0 }: { children: React.ReactNode; active: boolean; delay?: number }) {
    return (
        <div
            className={`transition-all duration-700 ease-out ${active ? 'translate-y-0 opacity-100' : 'translate-y-6 opacity-0'}`}
            style={{ transitionDelay: active ? `${delay}ms` : '0ms' }}
        >
            {children}
        </div>
    );
}

export default function PitchDeck() {
    const { auth } = usePage<SharedData>().props;
    const [current, setCurrent] = useState(0);
    const [navOpen, setNavOpen] = useState(false);
    const isAnimating = useRef(false);

    const goTo = useCallback((idx: number) => {
        if (idx < 0 || idx >= SLIDES.length || isAnimating.current) return;
        isAnimating.current = true;
        setCurrent(idx);
        setNavOpen(false);
        setTimeout(() => { isAnimating.current = false; }, 600);
    }, []);

    const next = useCallback(() => goTo(current + 1), [current, goTo]);
    const prev = useCallback(() => goTo(current - 1), [current, goTo]);

    // Keyboard navigation
    useEffect(() => {
        const handler = (e: KeyboardEvent) => {
            if (e.key === 'ArrowRight' || e.key === 'ArrowDown' || e.key === ' ') { e.preventDefault(); next(); }
            if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') { e.preventDefault(); prev(); }
            if (e.key === 'Home') { e.preventDefault(); goTo(0); }
            if (e.key === 'End') { e.preventDefault(); goTo(SLIDES.length - 1); }
            if (e.key === 'Escape') setNavOpen(false);
        };
        window.addEventListener('keydown', handler);
        return () => window.removeEventListener('keydown', handler);
    }, [next, prev, goTo]);

    // Wheel navigation
    useEffect(() => {
        let cooldown = false;
        const handler = (e: WheelEvent) => {
            if (cooldown) return;
            if (Math.abs(e.deltaY) < 30) return;
            cooldown = true;
            if (e.deltaY > 0) next(); else prev();
            setTimeout(() => { cooldown = false; }, 800);
        };
        window.addEventListener('wheel', handler, { passive: true });
        return () => window.removeEventListener('wheel', handler);
    }, [next, prev]);

    // Touch swipe
    useEffect(() => {
        let startY = 0;
        const onStart = (e: TouchEvent) => { startY = e.touches[0].clientY; };
        const onEnd = (e: TouchEvent) => {
            const diff = startY - e.changedTouches[0].clientY;
            if (Math.abs(diff) > 50) { if (diff > 0) next(); else prev(); }
        };
        window.addEventListener('touchstart', onStart, { passive: true });
        window.addEventListener('touchend', onEnd, { passive: true });
        return () => { window.removeEventListener('touchstart', onStart); window.removeEventListener('touchend', onEnd); };
    }, [next, prev]);

    const a = current; // active slide index for Reveal

    return (
        <>
            <Head title="Artemis — Pitch Deck"><meta name="robots" content="noindex, nofollow" /></Head>

            <div className="fixed inset-0 overflow-hidden bg-white dark:bg-zinc-950">
                {/* Grid bg */}
                <div className="pointer-events-none absolute inset-0 bg-[linear-gradient(rgba(0,0,0,0.015)_1px,transparent_1px),linear-gradient(90deg,rgba(0,0,0,0.015)_1px,transparent_1px)] bg-[size:60px_60px] dark:bg-[linear-gradient(rgba(255,255,255,0.01)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,0.01)_1px,transparent_1px)]" />

                {/* Top bar */}
                <div className="absolute top-0 z-50 flex w-full items-center justify-between border-b border-gray-200/60 bg-white/70 px-5 py-2.5 backdrop-blur-2xl dark:border-white/5 dark:bg-zinc-950/70 md:px-8">
                    <div className="flex items-center gap-3">
                        <Link href="/" className="flex items-center gap-2">
                            <img src="/img/logo/artemis.png" alt="Artemis" className="h-6 w-6 object-contain" />
                            <span className="text-[14px] font-semibold tracking-tight text-gray-900 dark:text-gray-100">Artemis</span>
                        </Link>
                        <span className="rounded-full border border-brand-200/60 bg-brand-50/80 px-2 py-0.5 font-mono text-[8px] font-semibold uppercase tracking-[0.2em] text-brand-700 dark:border-brand-500/20 dark:bg-brand-500/10 dark:text-brand-400">Pitch Deck</span>
                    </div>
                    <div className="flex items-center gap-3">
                        <span className="hidden font-mono text-[10px] uppercase tracking-wider text-gray-400 dark:text-gray-500 sm:block">
                            {String(current + 1).padStart(2, '0')} / {SLIDES.length}
                        </span>
                        <button onClick={() => setNavOpen(!navOpen)} className="inline-flex h-7 items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-2.5 font-mono text-[9px] uppercase tracking-wider text-gray-500 transition-all hover:border-brand-300 hover:text-brand-600 dark:border-white/8 dark:bg-zinc-900 dark:text-gray-400">
                            <svg className="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}><path strokeLinecap="round" strokeLinejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" /></svg>
                        </button>
                        <AppearanceToggleDropdown />
                    </div>
                </div>

                {/* Slide navigator overlay */}
                {navOpen && (
                    <div className="absolute inset-0 z-40 flex items-center justify-center bg-white/95 backdrop-blur-xl dark:bg-zinc-950/95" onClick={() => setNavOpen(false)}>
                        <div className="grid w-full max-w-3xl grid-cols-3 gap-3 px-6 sm:grid-cols-4" onClick={(e) => e.stopPropagation()}>
                            {SLIDES.map((s, i) => (
                                <button
                                    key={s.id}
                                    onClick={() => goTo(i)}
                                    className={`flex flex-col items-center gap-2 rounded-2xl border p-5 transition-all hover:-translate-y-0.5 ${i === current ? 'border-brand-500 bg-brand-50/80 shadow-lg shadow-brand-500/10 dark:border-brand-500/60 dark:bg-brand-500/8' : 'border-gray-200/80 bg-white hover:border-brand-300 dark:border-white/6 dark:bg-zinc-900/80'}`}
                                >
                                    <span className={`font-mono text-2xl font-bold ${i === current ? 'text-brand-500' : 'text-gray-200 dark:text-gray-700'}`}>{String(i + 1).padStart(2, '0')}</span>
                                    <span className={`text-[10px] font-semibold uppercase tracking-wider ${i === current ? 'text-brand-600 dark:text-brand-400' : 'text-gray-400 dark:text-gray-500'}`}>{s.label}</span>
                                </button>
                            ))}
                        </div>
                    </div>
                )}

                {/* Slide container */}
                <div className="relative h-full w-full text-gray-900 dark:text-gray-100">
                    {/* All slides stacked, only current visible */}
                    {SLIDES.map((slide, i) => (
                        <div
                            key={slide.id}
                            className={`absolute inset-0 flex items-center justify-center overflow-y-auto px-6 pt-14 pb-16 transition-all duration-500 ease-out md:px-16 lg:px-24 ${i === current ? 'pointer-events-auto z-10 opacity-100' : 'pointer-events-none z-0 opacity-0'}`}
                        >
                            <div className="w-full max-w-5xl py-8">
                                {/* Slide number watermark */}
                                <div className="pointer-events-none absolute right-6 top-16 select-none font-mono text-[100px] font-bold leading-none tracking-tighter text-gray-100/40 sm:right-12 sm:text-[160px] lg:right-20 lg:text-[200px] dark:text-white/[0.02]">
                                    {String(i + 1).padStart(2, '0')}
                                </div>

                                {/* ── SLIDE CONTENT ── */}
                                {i === 0 && (
                                    <div className="relative text-center">
                                        <div className="pointer-events-none absolute -top-40 left-1/2 h-[500px] w-[800px] -translate-x-1/2 bg-[radial-gradient(ellipse,rgba(16,211,161,0.12),transparent_65%)]" />
                                        <Reveal active={a === 0}>
                                            <div className="mb-8 inline-flex items-center gap-2.5 rounded-full border border-brand-200/60 bg-brand-50/80 px-5 py-2.5 dark:border-brand-500/15 dark:bg-brand-500/8">
                                                <span className="h-2 w-2 animate-pulse rounded-full bg-brand-500 shadow-[0_0_8px_var(--color-brand-500)]" />
                                                <span className="font-mono text-[10px] font-semibold uppercase tracking-[0.25em] text-brand-700 dark:text-brand-400">Client Presentation · 2026</span>
                                            </div>
                                        </Reveal>
                                        <Reveal active={a === 0} delay={150}>
                                            <div className="mb-8 flex justify-center"><img src="/img/logo/artemis.png" alt="Artemis" className="h-24 w-24 object-contain drop-shadow-xl sm:h-28 sm:w-28" /></div>
                                        </Reveal>
                                        <Reveal active={a === 0} delay={300}>
                                            <h1 className="mb-8 text-[clamp(2.5rem,7vw,5.5rem)]! font-bold leading-[0.92] tracking-tight">Hunt down{' '}<span className="bg-gradient-to-br from-brand-500 to-brand-700 bg-clip-text italic text-transparent">RTS.</span><br />Protect your margin.</h1>
                                        </Reveal>
                                        <Reveal active={a === 0} delay={450}>
                                            <p className="mx-auto mb-10 max-w-lg text-lg leading-relaxed text-gray-500 sm:text-xl dark:text-gray-400">The <span className="font-semibold text-brand-500">1st</span> analytics & automation platform for Philippine COD e-commerce.</p>
                                        </Reveal>
                                        <Reveal active={a === 0} delay={600}>
                                            <div className="mx-auto flex max-w-md flex-wrap items-center justify-center gap-x-8 gap-y-3 font-mono text-[10px] uppercase tracking-[0.2em] text-gray-400 dark:text-gray-500">
                                                {['Analytics', 'Parcel Journey', 'RTS Reduction', 'Pancake POS'].map((t, ti) => (<span key={t}>{ti > 0 && <span className="mr-8 inline-block h-3 w-px bg-gray-200 align-middle dark:bg-white/10" />}{t}</span>))}
                                            </div>
                                        </Reveal>
                                    </div>
                                )}

                                {i === 1 && (
                                    <>
                                        <Reveal active={a === 1}><p className="mb-5 font-mono text-[10px] font-semibold uppercase tracking-[0.25em] text-brand-500">The Problem</p></Reveal>
                                        <Reveal active={a === 1} delay={100}><h2 className="mb-6 max-w-3xl text-[clamp(1.75rem,4.5vw,3.25rem)] font-bold leading-[1.08] tracking-tight">Philippine COD e-commerce is massive — but <span className="text-brand-500 italic">operationally broken.</span></h2></Reveal>
                                        <Reveal active={a === 1} delay={200}><p className="mb-14 max-w-2xl text-lg leading-relaxed text-gray-500 dark:text-gray-400">Over 60% of PH online purchases are COD. But sellers are losing 20-40% of every shipment to RTS — and most don't even know their real number.</p></Reveal>
                                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                            {[{ n: '60%+', l: 'of PH orders are COD', s: 'Cash-on-delivery remains dominant' }, { n: '20-40%', l: 'Average RTS rate', s: 'Failed deliveries, returned parcels' }, { n: '₱430+', l: 'Cost per failed parcel', s: 'Ads + shipping + packaging + margin' }, { n: '0', l: 'Analytics tools for PH COD', s: 'Artemis is the first' }].map((c, ci) => (
                                                <Reveal key={c.l} active={a === 1} delay={300 + ci * 100}><div className="rounded-2xl border border-gray-200/80 bg-white p-7 dark:border-white/6 dark:bg-zinc-900/80"><div className="mb-3 text-4xl font-bold tracking-tight text-brand-500 sm:text-5xl">{c.n}</div><p className="text-[13px] font-semibold text-gray-900 dark:text-gray-100">{c.l}</p><p className="mt-1 text-[11px] text-gray-400 dark:text-gray-500">{c.s}</p></div></Reveal>
                                            ))}
                                        </div>
                                    </>
                                )}

                                {i === 2 && (
                                    <>
                                        <Reveal active={a === 2}><p className="mb-5 font-mono text-[10px] font-semibold uppercase tracking-[0.25em] text-brand-500">The Numbers</p></Reveal>
                                        <Reveal active={a === 2} delay={100}><h2 className="mb-6 max-w-3xl text-[clamp(1.75rem,4.5vw,3.25rem)] font-bold leading-[1.08] tracking-tight">Every failed parcel is <span className="text-brand-500 italic">pure loss.</span></h2></Reveal>
                                        <Reveal active={a === 2} delay={200}><p className="mb-12 max-w-2xl text-lg leading-relaxed text-gray-500 dark:text-gray-400">A mid-sized seller (3,000 orders/month) at 30% RTS bleeds <span className="font-semibold text-gray-900 dark:text-gray-100">₱387,000 every month</span>.</p></Reveal>
                                        <Reveal active={a === 2} delay={300}>
                                            <div className="overflow-hidden rounded-2xl border border-gray-200/80 bg-white shadow-sm dark:border-white/6 dark:bg-zinc-900/80">
                                                <div className="border-b border-gray-100 bg-stone-50/80 px-7 py-4 dark:border-white/5 dark:bg-white/[0.02]"><p className="font-mono text-[10px] font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">Cost anatomy — single RTS parcel</p></div>
                                                {[{ l: 'Wasted ad spend', v: '₱50', c: 'bg-rose-500' }, { l: 'Forward shipping', v: '₱120', c: 'bg-blue-light-500' }, { l: 'Return shipping', v: '₱100', c: 'bg-blue-light-400' }, { l: 'Packaging', v: '₱15', c: 'bg-gray-400' }, { l: 'Damage loss', v: '₱20', c: 'bg-orange-500' }, { l: 'Unrealized profit', v: '₱175', c: 'bg-brand-500' }].map((r) => (
                                                    <div key={r.l} className="flex items-center gap-4 border-b border-gray-100 px-7 py-3.5 last:border-0 dark:border-white/5"><span className={`h-2.5 w-2.5 shrink-0 rounded-sm ${r.c}`} /><span className="flex-1 text-[14px] text-gray-700 dark:text-gray-300">{r.l}</span><span className="font-mono text-[15px] font-bold text-gray-900 dark:text-gray-100">{r.v}</span></div>
                                                ))}
                                                <div className="flex items-center justify-between bg-brand-50/60 px-7 py-5 dark:bg-brand-500/5"><span className="text-[15px] font-bold text-gray-900 dark:text-gray-100">Total per RTS parcel</span><span className="font-mono text-2xl font-bold text-brand-600 dark:text-brand-400">₱480</span></div>
                                            </div>
                                        </Reveal>
                                    </>
                                )}

                                {i === 3 && (
                                    <>
                                        <Reveal active={a === 3}><p className="mb-5 font-mono text-[10px] font-semibold uppercase tracking-[0.25em] text-brand-500">The Solution</p></Reveal>
                                        <Reveal active={a === 3} delay={100}><h2 className="mb-6 max-w-3xl text-[clamp(1.75rem,4.5vw,3.25rem)] font-bold leading-[1.08] tracking-tight">Artemis connects to <span className="text-brand-500 italic">Pancake POS</span> and gives sellers full visibility.</h2></Reveal>
                                        <Reveal active={a === 3} delay={200}><p className="mb-12 max-w-2xl text-lg leading-relaxed text-gray-500 dark:text-gray-400">Purpose-built for Philippine COD. Not a generic dashboard — a complete analytics and automation platform.</p></Reveal>
                                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                            {[{ t: 'Sales Analytics', d: 'Revenue, orders, AOV, trends across all pages' }, { t: 'Delivery Analytics', d: 'Success rates, attempt counts, courier performance' }, { t: 'RTS Analytics', d: 'Return rates by page, product, city, time' }, { t: 'Parcel Journey', d: 'Per-order tracking + auto notifications via Chat/SMS' }, { t: 'Operations Insights', d: 'Fulfillment lead times, bottleneck detection' }, { t: 'Multi-workspace', d: 'Role-based access for teams with granular permissions' }].map((f, fi) => (
                                                <Reveal key={f.t} active={a === 3} delay={300 + fi * 80}>
                                                    <div className="rounded-2xl border border-gray-200/80 bg-white p-6 transition-all hover:-translate-y-1 hover:border-brand-300 hover:shadow-lg hover:shadow-brand-500/5 dark:border-white/6 dark:bg-zinc-900/80 dark:hover:border-brand-500/30">
                                                        <div className="mb-3 flex h-9 w-9 items-center justify-center rounded-xl border border-brand-200/60 bg-brand-50/80 dark:border-brand-500/20 dark:bg-brand-500/10"><svg className="h-4 w-4 text-brand-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}><path strokeLinecap="round" strokeLinejoin="round" d="M5 13l4 4L19 7" /></svg></div>
                                                        <h3 className="mb-1.5 text-[14px] font-bold text-gray-900 dark:text-gray-100">{f.t}</h3><p className="text-[13px] leading-relaxed text-gray-500 dark:text-gray-400">{f.d}</p>
                                                    </div>
                                                </Reveal>
                                            ))}
                                        </div>
                                    </>
                                )}

                                {i === 4 && (
                                    <>
                                        <Reveal active={a === 4}><p className="mb-5 font-mono text-[10px] font-semibold uppercase tracking-[0.25em] text-brand-500">How It Works</p></Reveal>
                                        <Reveal active={a === 4} delay={100}><h2 className="mb-14 max-w-3xl text-[clamp(1.75rem,4.5vw,3.25rem)] font-bold leading-[1.08] tracking-tight">Signup to first insight — <span className="text-brand-500 italic">under 5 minutes.</span></h2></Reveal>
                                        <div className="grid gap-8 sm:grid-cols-2 lg:grid-cols-4">
                                            {[{ n: '01', t: 'Sign up', d: 'Create workspace. Name, email, done. No credit card.' }, { n: '02', t: 'Connect Pancake', d: 'Link your Pancake POS page. We pull data automatically.' }, { n: '03', t: 'See your numbers', d: 'Real RTS rate, bleed, problem areas — within minutes.' }, { n: '04', t: 'Act on it', d: 'Enable notifications, follow insights, watch RTS drop.' }].map((s, si) => (
                                                <Reveal key={s.n} active={a === 4} delay={200 + si * 120}>
                                                    <div className="border-t-2 border-brand-500 pt-6"><p className="mb-3 font-mono text-[11px] font-bold uppercase tracking-[0.2em] text-brand-500">{s.n}</p><h4 className="mb-2 text-lg font-bold tracking-tight">{s.t}</h4><p className="text-[13px] leading-relaxed text-gray-500 dark:text-gray-400">{s.d}</p></div>
                                                </Reveal>
                                            ))}
                                        </div>
                                    </>
                                )}

                                {/* SLIDE 6: Results */}
                                {i === 5 && (
                                    <>
                                        <Reveal active={a === 5}><p className="mb-5 font-mono text-[10px] font-semibold uppercase tracking-[0.25em] text-brand-500">Real Results</p></Reveal>
                                        <Reveal active={a === 5} delay={100}><h2 className="mb-6 max-w-3xl text-[clamp(1.75rem,4.5vw,3.25rem)] font-bold leading-[1.08] tracking-tight">Sellers like you are already <span className="text-brand-500 italic">cutting RTS.</span></h2></Reveal>
                                        <Reveal active={a === 5} delay={200}><p className="mb-12 max-w-2xl text-lg leading-relaxed text-gray-500 dark:text-gray-400">Actual results from Pancake sellers using Artemis — tracked across months.</p></Reveal>
                                        <div className="grid gap-4 md:grid-cols-2">
                                            {[{ t: 'Health & Wellness Brand', p: 'Herbal spray · 10M+ parcels', b: '17.27%', af: '14.35%', sv: '-17% RTS', period: 'Over 5 months' }, { t: 'Respiratory Care Brand', p: 'Inhaler · 9M+ parcels', b: '32.95%', af: '17.21%', sv: '-48% RTS', period: 'In 3 months' }, { t: 'Supplement Brand', p: 'Capsules · 4.5M+ parcels', b: '14.12%', af: '9.72%', sv: '-31% RTS', period: '8 months steady' }, { t: 'Pain Relief Brand', p: 'Topical cream · 8.4M+ parcels', b: '18.29%', af: '12.74%', sv: '-30% RTS', period: 'Over 8 months' }].map((c, ci) => (
                                                <Reveal key={c.t} active={a === 5} delay={300 + ci * 100}>
                                                    <div className="overflow-hidden rounded-2xl border border-gray-200/80 bg-white dark:border-white/6 dark:bg-zinc-900/80">
                                                        <div className="border-b border-gray-100 bg-stone-50/80 px-6 py-4 dark:border-white/5 dark:bg-white/[0.02]"><h3 className="text-[14px] font-bold text-gray-900 dark:text-gray-100">{c.t}</h3><p className="mt-0.5 font-mono text-[9px] uppercase tracking-wider text-gray-400">{c.p}</p></div>
                                                        <div className="px-6 py-4">
                                                            <div className="mb-3 flex items-center gap-3"><div className="text-center"><p className="font-mono text-[8px] uppercase tracking-wider text-gray-400">Before</p><p className="text-lg font-bold text-gray-400 line-through">{c.b}</p></div><svg className="h-3.5 w-3.5 text-brand-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}><path strokeLinecap="round" strokeLinejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" /></svg><div className="text-center"><p className="font-mono text-[8px] uppercase tracking-wider text-brand-500">After</p><p className="text-lg font-bold text-brand-600 dark:text-brand-400">{c.af}</p></div><span className="ml-auto rounded-full border border-brand-200 bg-brand-50 px-2.5 py-1 font-mono text-[10px] font-bold text-brand-700 dark:border-brand-500/20 dark:bg-brand-500/10 dark:text-brand-400">{c.sv}</span></div>
                                                            <p className="border-t border-gray-100 pt-3 text-[11px] text-gray-400 dark:border-white/5">{c.period}</p>
                                                        </div>
                                                    </div>
                                                </Reveal>
                                            ))}
                                        </div>
                                    </>
                                )}

                                {/* SLIDE 7: Pricing */}
                                {i === 6 && (
                                    <>
                                        <Reveal active={a === 6}><p className="mb-5 font-mono text-[10px] font-semibold uppercase tracking-[0.25em] text-brand-500">Pricing</p></Reveal>
                                        <Reveal active={a === 6} delay={100}><h2 className="mb-4 max-w-3xl text-[clamp(1.5rem,4vw,2.75rem)] font-bold leading-[1.08] tracking-tight">Simple pricing. <span className="text-brand-500 italic">Start free.</span></h2></Reveal>
                                        <Reveal active={a === 6} delay={200}><p className="mb-10 max-w-2xl text-base leading-relaxed text-gray-500 dark:text-gray-400">Pick the plan that fits your order volume. Parcel Journey billed per <span className="font-semibold text-gray-700 dark:text-gray-300">delivered order only</span> — you don't pay for RTS.</p></Reveal>
                                        <Reveal active={a === 6} delay={300}>
                                            <div className="grid gap-4 md:grid-cols-4">
                                                {[{ name: 'Free Trial', price: '₱0', period: '14 days', orders: '500', ret: '1 mo', pj: 'Chat (free)', sup: 'Chat', team: '1', f: false }, { name: 'Starter', price: '₱1,499', period: '/mo', orders: '3,000', ret: '3 mo', pj: '₱1/5 del.', sup: 'Chat', team: '2', f: false }, { name: 'Growth', price: '₱3,999', period: '/mo', orders: '10,000', ret: '6 mo', pj: '₱1/5 del.', sup: 'Priority', team: '5', f: true }, { name: 'Scale', price: '₱9,999', period: '/mo', orders: '30,000', ret: '12 mo', pj: '₱1/5 del.', sup: 'Dedicated', team: '∞', f: false }].map((t) => (
                                                    <div key={t.name} className={`relative flex flex-col overflow-hidden rounded-2xl border p-5 ${t.f ? 'border-brand-500/80 bg-gradient-to-b from-brand-50/90 to-white shadow-xl shadow-brand-500/8 dark:border-brand-500/60 dark:from-brand-500/[0.06] dark:to-zinc-900' : 'border-gray-200/80 bg-white dark:border-white/6 dark:bg-zinc-900/80'}`}>
                                                        {t.f && <span className="mb-3 inline-flex self-start rounded-full bg-brand-500 px-2 py-0.5 font-mono text-[8px] font-bold uppercase tracking-[0.2em] text-white">Recommended</span>}
                                                        <p className="font-mono text-[9px] uppercase tracking-[0.2em] text-gray-400 dark:text-gray-500">{t.name}</p>
                                                        <div className="mt-1.5 mb-4 flex items-baseline gap-1"><span className="text-2xl font-bold tracking-tight">{t.price}</span><span className="text-xs text-gray-400">{t.period}</span></div>
                                                        <div className="flex-1 space-y-2 border-t border-gray-100 pt-4 text-[11px] dark:border-white/5">
                                                            {[['Orders', `${t.orders}/mo`], ['Data retention', t.ret], ['Parcel Journey', t.pj], ['Support', t.sup], ['Team', t.team]].map(([k, v]) => (<div key={k} className="flex justify-between"><span className="text-gray-400">{k}</span><span className="font-semibold text-gray-900 dark:text-gray-100">{v}</span></div>))}
                                                        </div>
                                                    </div>
                                                ))}
                                            </div>
                                        </Reveal>
                                    </>
                                )}

                                {/* SLIDE 8: Your ROI */}
                                {i === 7 && (
                                    <>
                                        <Reveal active={a === 7}><p className="mb-5 font-mono text-[10px] font-semibold uppercase tracking-[0.25em] text-brand-500">Your ROI</p></Reveal>
                                        <Reveal active={a === 7} delay={100}><h2 className="mb-6 max-w-3xl text-[clamp(1.75rem,4.5vw,3.25rem)] font-bold leading-[1.08] tracking-tight">Artemis pays for itself if you reduce RTS by <span className="text-brand-500 italic">less than 1%.</span></h2></Reveal>
                                        <Reveal active={a === 7} delay={200}><p className="mb-12 max-w-2xl text-lg leading-relaxed text-gray-500 dark:text-gray-400">Your monthly RTS bleed is hundreds of thousands of pesos. Artemis costs a fraction of that.</p></Reveal>
                                        <Reveal active={a === 7} delay={300}>
                                            <div className="grid gap-4 sm:grid-cols-3">
                                                {[{ plan: 'Starter', orders: '3,000 orders', bleed: '₱387,000', cost: '₱1,919/mo', be: '0.5%' }, { plan: 'Growth', orders: '10,000 orders', bleed: '₱1,290,000', cost: '₱5,399/mo', be: '0.4%' }, { plan: 'Scale', orders: '30,000 orders', bleed: '₱3,870,000', cost: '₱14,199/mo', be: '0.4%' }].map((r, ri) => (
                                                    <div key={r.plan} className={`overflow-hidden rounded-2xl border ${ri === 1 ? 'border-brand-500/80 shadow-lg shadow-brand-500/5 dark:border-brand-500/60' : 'border-gray-200/80 dark:border-white/6'}`}>
                                                        <div className={`px-6 py-5 ${ri === 1 ? 'bg-gradient-to-b from-brand-50/90 to-white dark:from-brand-500/[0.06] dark:to-zinc-900' : 'bg-white dark:bg-zinc-900/80'}`}>
                                                            <p className={`mb-1 font-mono text-[9px] uppercase tracking-[0.2em] ${ri === 1 ? 'text-brand-600 dark:text-brand-400' : 'text-gray-400'}`}>{r.plan}</p>
                                                            <p className="text-2xl font-bold tracking-tight">{r.cost}</p>
                                                        </div>
                                                        <div className="space-y-2 border-t border-gray-100 px-6 py-4 text-[12px] dark:border-white/5">
                                                            <div className="flex justify-between"><span className="text-gray-400">{r.orders}</span></div>
                                                            <div className="flex justify-between"><span className="text-gray-400">Your monthly bleed</span><span className="font-semibold text-gray-900 dark:text-gray-100">{r.bleed}</span></div>
                                                            <div className="flex justify-between border-t border-gray-100 pt-2 dark:border-white/5"><span className="text-gray-400">Break-even at</span><span className="font-mono font-bold text-brand-600 dark:text-brand-400">{r.be} RTS reduction</span></div>
                                                        </div>
                                                    </div>
                                                ))}
                                            </div>
                                        </Reveal>
                                    </>
                                )}

                                {/* SLIDE 9: Get Started */}
                                {i === 8 && (
                                    <div className="relative text-center">
                                        <div className="pointer-events-none absolute left-1/2 top-1/2 h-[500px] w-[700px] -translate-x-1/2 -translate-y-1/2 bg-[radial-gradient(ellipse,rgba(16,211,161,0.1),transparent_55%)]" />
                                        <Reveal active={a === 8}><p className="mb-5 font-mono text-[10px] font-semibold uppercase tracking-[0.25em] text-brand-500">Get Started</p></Reveal>
                                        <Reveal active={a === 8} delay={100}><h2 className="mb-8 text-[clamp(2rem,5vw,3.5rem)] font-bold leading-[1.08] tracking-tight">Start your <span className="text-brand-500 italic">14-day free trial.</span></h2></Reveal>
                                        <Reveal active={a === 8} delay={200}>
                                            <div className="mx-auto mb-12 grid max-w-lg gap-4 text-left sm:grid-cols-2">
                                                {[{ t: 'Connect 1 Pancake page', ic: 'M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244' }, { t: '1 month of data', ic: 'M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5' }, { t: 'Parcel Journey via chat', ic: 'M15 10.5a3 3 0 11-6 0 3 3 0 016 0z M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z' }, { t: 'No credit card required', ic: 'M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z' }].map((x) => (
                                                    <div key={x.t} className="flex items-center gap-3 rounded-2xl border border-gray-200/80 bg-white p-4 dark:border-white/6 dark:bg-zinc-900/80">
                                                        <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border border-brand-200/60 bg-brand-50/80 dark:border-brand-500/20 dark:bg-brand-500/10"><svg className="h-4 w-4 text-brand-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}><path strokeLinecap="round" strokeLinejoin="round" d={x.ic} /></svg></div>
                                                        <span className="text-[14px] font-semibold text-gray-900 dark:text-gray-100">{x.t}</span>
                                                    </div>
                                                ))}
                                            </div>
                                        </Reveal>
                                        <Reveal active={a === 8} delay={400}>
                                            <div className="mb-5 flex items-center justify-center gap-4"><div className="h-px w-16 bg-gradient-to-r from-transparent to-gray-200 dark:to-white/8" /><img src="/img/logo/artemis.png" alt="" className="h-12 w-12 object-contain" /><div className="h-px w-16 bg-gradient-to-l from-transparent to-gray-200 dark:to-white/8" /></div>
                                            <p className="mb-3 text-2xl font-bold tracking-tight sm:text-3xl">Hunt down RTS. <span className="bg-gradient-to-br from-brand-500 to-brand-700 bg-clip-text italic text-transparent">Protect your margin.</span></p>
                                            <p className="mx-auto mb-8 max-w-md text-[14px] text-gray-500 dark:text-gray-400">Setup takes under 2 minutes. See your real numbers today.</p>
                                            <div className="flex flex-col items-center justify-center gap-3 sm:flex-row">
                                                <Link href="/register" className="inline-flex h-11 w-full items-center justify-center gap-2 rounded-xl bg-brand-500! px-6 text-[14px] font-semibold text-white shadow-lg shadow-brand-500/20 transition-all hover:-translate-y-0.5 hover:bg-brand-600 sm:w-auto">Start free trial <svg width="14" height="10" viewBox="0 0 16 12" fill="none"><path d="M1 6h14m0 0L10 1m5 5l-5 5" stroke="currentColor" strokeWidth="2" strokeLinecap="round" /></svg></Link>
                                                <Link href="/rts-calculator" className="inline-flex h-11 w-full items-center justify-center rounded-xl border border-gray-200 bg-white px-6 text-[14px] font-semibold text-gray-700 transition-all hover:border-brand-300 hover:text-brand-600 sm:w-auto dark:border-white/10 dark:bg-zinc-900 dark:text-gray-300">Calculate your RTS bleed</Link>
                                            </div>
                                        </Reveal>
                                    </div>
                                )}
                            </div>
                        </div>
                    ))}
                </div>

                {/* Bottom controls */}
                <div className="absolute bottom-0 z-30 flex w-full items-center justify-between border-t border-gray-200/60 bg-white/70 px-5 py-2.5 backdrop-blur-2xl dark:border-white/5 dark:bg-zinc-950/70 md:px-8">
                    <button onClick={prev} disabled={current === 0} className="inline-flex h-8 items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 font-mono! text-[10px]! uppercase tracking-wider text-gray-500 transition-all hover:border-brand-300 hover:text-brand-600 disabled:opacity-30 dark:border-white/8 dark:bg-zinc-900 dark:text-gray-400">
                        <svg className="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}><path strokeLinecap="round" strokeLinejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" /></svg>
                        <span className="hidden sm:inline">Prev</span>
                    </button>

                    <div className="flex items-center gap-1.5">
                        {SLIDES.map((_, idx) => (
                            <button key={idx} onClick={() => goTo(idx)} className={`h-1.5 rounded-full transition-all ${idx === current ? 'w-8 bg-brand-500' : 'w-1.5 bg-gray-300/60 hover:bg-gray-400 dark:bg-white/10 dark:hover:bg-white/20'}`} />
                        ))}
                    </div>

                    <button onClick={next} disabled={current === SLIDES.length - 1} className="inline-flex h-8 items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 font-mono! text-[10px]! uppercase tracking-wider text-gray-500 transition-all hover:border-brand-300 hover:text-brand-600 disabled:opacity-30 dark:border-white/8 dark:bg-zinc-900 dark:text-gray-400">
                        <span className="hidden sm:inline">Next</span>
                        <svg className="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}><path strokeLinecap="round" strokeLinejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" /></svg>
                    </button>
                </div>
            </div>
        </>
    );
}
