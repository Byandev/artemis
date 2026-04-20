import AppearanceToggleDropdown from '@/components/appearance-dropdown';
import { type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';

const SLIDES = [
    { id: 'cover', label: 'Cover' },
    { id: 'what-we-built', label: 'What We Built' },
    { id: 'traction', label: 'Traction' },
    { id: 'revenue', label: 'Revenue Model' },
    { id: 'equity', label: 'Equity' },
    { id: 'roles', label: 'Roles' },
    { id: 'costs', label: 'Costs' },
    { id: 'roadmap', label: 'Future Plans' },
    { id: 'value', label: 'What It\'s Worth' },
    { id: 'next-steps', label: 'Next Steps' },
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

export default function Partnership() {
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

    const a = current;

    return (
        <>
            <Head title="Artemis — Partnership Proposal"><meta name="robots" content="noindex, nofollow" /></Head>

            <div className="fixed inset-0 overflow-hidden bg-white dark:bg-zinc-950">
                <div className="pointer-events-none absolute inset-0 bg-[linear-gradient(rgba(0,0,0,0.015)_1px,transparent_1px),linear-gradient(90deg,rgba(0,0,0,0.015)_1px,transparent_1px)] bg-[size:60px_60px] dark:bg-[linear-gradient(rgba(255,255,255,0.01)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,0.01)_1px,transparent_1px)]" />

                {/* Top bar */}
                <div className="absolute top-0 z-50 flex w-full items-center justify-between border-b border-gray-200/60 bg-white/70 px-5 py-2.5 backdrop-blur-2xl dark:border-white/5 dark:bg-zinc-950/70 md:px-8">
                    <div className="flex items-center gap-3">
                        <Link href="/" className="flex items-center gap-2">
                            <img src="/img/logo/artemis.png" alt="Artemis" className="h-6 w-6 object-contain" />
                            <span className="text-[14px] font-semibold tracking-tight text-gray-900 dark:text-gray-100">Artemis</span>
                        </Link>
                        <span className="rounded-full border border-amber-200/60 bg-amber-50/80 px-2 py-0.5 font-mono text-[8px] font-semibold uppercase tracking-[0.2em] text-amber-700 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-400">Confidential</span>
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
                        <div className="grid w-full max-w-3xl grid-cols-2 gap-3 px-6 sm:grid-cols-3 md:grid-cols-5" onClick={(e) => e.stopPropagation()}>
                            {SLIDES.map((s, i) => (
                                <button
                                    key={s.id}
                                    onClick={() => goTo(i)}
                                    className={`flex flex-col items-center gap-2 rounded-2xl border p-4 transition-all hover:-translate-y-0.5 ${i === current ? 'border-brand-500 bg-brand-50/80 shadow-lg shadow-brand-500/10 dark:border-brand-500/60 dark:bg-brand-500/8' : 'border-gray-200/80 bg-white hover:border-brand-300 dark:border-white/6 dark:bg-zinc-900/80'}`}
                                >
                                    <span className={`font-mono text-xl font-bold ${i === current ? 'text-brand-500' : 'text-gray-200 dark:text-gray-700'}`}>{String(i + 1).padStart(2, '0')}</span>
                                    <span className={`text-[9px] font-semibold uppercase tracking-wider ${i === current ? 'text-brand-600 dark:text-brand-400' : 'text-gray-400 dark:text-gray-500'}`}>{s.label}</span>
                                </button>
                            ))}
                        </div>
                    </div>
                )}

                {/* Slide container */}
                <div className="relative h-full w-full text-gray-900 dark:text-gray-100">
                    {SLIDES.map((slide, i) => (
                        <div
                            key={slide.id}
                            className={`absolute inset-0 flex items-center justify-center overflow-y-auto px-4 pt-14 pb-16 transition-all duration-500 ease-out sm:px-6 md:px-16 lg:px-24 ${i === current ? 'pointer-events-auto z-10 opacity-100' : 'pointer-events-none z-0 opacity-0'}`}
                        >
                            <div className="w-full max-w-5xl py-8">
                                <div className="pointer-events-none absolute right-4 top-16 select-none font-mono text-[60px] font-bold leading-none tracking-tighter text-gray-100/40 sm:right-12 sm:text-[160px] lg:right-20 lg:text-[200px] dark:text-white/[0.02]">
                                    {String(i + 1).padStart(2, '0')}
                                </div>

                                {/* SLIDE 1: Cover */}
                                {i === 0 && (
                                    <div className="relative text-center">
                                        <div className="pointer-events-none absolute -top-40 left-1/2 h-[500px] w-[800px] -translate-x-1/2 bg-[radial-gradient(ellipse,rgba(16,211,161,0.12),transparent_65%)]" />
                                        <Reveal active={a === 0}>
                                            <div className="mb-8 inline-flex items-center gap-2.5 rounded-full border border-amber-200/60 bg-amber-50/80 px-5 py-2.5 dark:border-amber-500/15 dark:bg-amber-500/8">
                                                <span className="h-2 w-2 rounded-full bg-amber-500 shadow-[0_0_8px_var(--color-amber-500)]" />
                                                <span className="font-mono text-[10px] font-semibold uppercase tracking-[0.25em] text-amber-700 dark:text-amber-400">Confidential Partnership Proposal</span>
                                            </div>
                                        </Reveal>
                                        <Reveal active={a === 0} delay={150}>
                                            <div className="mb-8 flex justify-center"><img src="/img/logo/artemis.png" alt="Artemis" className="h-24 w-24 object-contain drop-shadow-xl sm:h-28 sm:w-28" /></div>
                                        </Reveal>
                                        <Reveal active={a === 0} delay={300}>
                                            <h1 className="mb-8 text-[clamp(2.5rem,7vw,5.5rem)]! font-bold leading-[0.92] tracking-tight">Building{' '}<span className="bg-gradient-to-br from-brand-500 to-brand-700 bg-clip-text italic text-transparent">Artemis</span><br />together.</h1>
                                        </Reveal>
                                        <Reveal active={a === 0} delay={450}>
                                            <p className="mx-auto mb-6 max-w-lg text-lg leading-relaxed text-gray-500 sm:mb-10 sm:text-xl dark:text-gray-400">A partnership & equity proposal for the people who helped make this real.</p>
                                        </Reveal>
                                        <Reveal active={a === 0} delay={600}>
                                            <p className="font-mono text-[11px] uppercase tracking-[0.2em] text-gray-400 dark:text-gray-500">From Bryan Mulingbayan</p>
                                        </Reveal>
                                    </div>
                                )}

                                {/* SLIDE 2: What We Built */}
                                {i === 1 && (
                                    <>
                                        <Reveal active={a === 1}><p className="mb-2 font-mono text-[9px] font-semibold uppercase tracking-[0.25em] text-brand-500 sm:mb-5 sm:text-[10px]">What We Built</p></Reveal>
                                        <Reveal active={a === 1} delay={100}><h2 className="mb-2 max-w-3xl text-[clamp(1.1rem,4.5vw,3.25rem)] font-bold leading-[1.08] tracking-tight sm:mb-6">The <span className="text-brand-500 italic">first</span> analytics platform for PH COD e-commerce.</h2></Reveal>
                                        <Reveal active={a === 1} delay={200}><p className="mb-4 max-w-2xl text-xs leading-relaxed text-gray-500 sm:mb-12 sm:text-lg dark:text-gray-400">Artemis connects to Pancake POS and gives COD sellers full visibility into their sales, deliveries, RTS rates, and parcel journeys.</p></Reveal>
                                        <div className="grid grid-cols-2 gap-2 sm:gap-4 lg:grid-cols-3">
                                            {[
                                                { t: 'Sales & RTS Analytics', d: 'Revenue, orders, AOV, return rates — broken down by page, product, city, time' },
                                                { t: 'Parcel Journey', d: 'Per-order tracking with automated customer notifications via Chat and SMS' },
                                                { t: 'Delivery Intelligence', d: 'Success rates, attempt counts, courier performance analysis' },
                                                { t: 'Multi-workspace', d: 'Role-based access for teams with granular permissions per workspace' },
                                                { t: 'Pancake POS Integration', d: 'Direct sync with the dominant PH COD platform — orders, shops, pages' },
                                                { t: 'Zero Competition', d: 'No existing tool does this for Philippine COD sellers. We are first.' },
                                            ].map((f, fi) => (
                                                <Reveal key={f.t} active={a === 1} delay={300 + fi * 80}>
                                                    <div className="rounded-2xl border border-gray-200/80 bg-white p-3 sm:p-6 dark:border-white/6 dark:bg-zinc-900/80">
                                                        <div className="mb-1.5 flex h-6 w-6 items-center justify-center rounded-lg border border-brand-200/60 bg-brand-50/80 sm:mb-3 sm:h-9 sm:w-9 sm:rounded-xl dark:border-brand-500/20 dark:bg-brand-500/10"><svg className="h-3 w-3 text-brand-500 sm:h-4 sm:w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}><path strokeLinecap="round" strokeLinejoin="round" d="M5 13l4 4L19 7" /></svg></div>
                                                        <h3 className="mb-0.5 text-[11px] font-bold sm:mb-1.5 sm:text-[14px]">{f.t}</h3>
                                                        <p className="text-[10px] leading-relaxed text-gray-500 sm:text-[13px] dark:text-gray-400">{f.d}</p>
                                                    </div>
                                                </Reveal>
                                            ))}
                                        </div>
                                    </>
                                )}

                                {/* SLIDE 3: Traction */}
                                {i === 2 && (
                                    <>
                                        <Reveal active={a === 2}><p className="mb-2 font-mono text-[9px] font-semibold uppercase tracking-[0.25em] text-brand-500 sm:mb-5 sm:text-[10px]">Traction</p></Reveal>
                                        <Reveal active={a === 2} delay={100}><h2 className="mb-2 max-w-3xl text-[clamp(1.1rem,4.5vw,3.25rem)] font-bold leading-[1.08] tracking-tight sm:mb-6">Real sellers. <span className="text-brand-500 italic">Real results.</span></h2></Reveal>
                                        <Reveal active={a === 2} delay={200}><p className="mb-4 max-w-2xl text-xs leading-relaxed text-gray-500 sm:mb-12 sm:text-lg dark:text-gray-400">Artemis is already in production with beta users. The product works, the data flows, and sellers are seeing their real RTS numbers for the first time.</p></Reveal>
                                        <div className="grid grid-cols-2 gap-2 sm:gap-4 lg:grid-cols-4">
                                            {[
                                                { n: '1st', l: 'Mover advantage', s: 'No competitors in PH COD analytics' },
                                                { n: '47', l: 'Pages tracked', s: 'Active seller pages on the platform' },
                                                { n: '268M+', l: 'Parcels tracked', s: 'Delivered + returned across all pages' },
                                                { n: '306K+', l: 'Notifications sent', s: 'SMS + Chat parcel journey alerts' },
                                            ].map((c, ci) => (
                                                <Reveal key={c.l} active={a === 2} delay={300 + ci * 100}>
                                                    <div className="rounded-2xl border border-gray-200/80 bg-white p-3 sm:p-6 dark:border-white/6 dark:bg-zinc-900/80">
                                                        <div className="mb-2 text-2xl font-bold tracking-tight text-brand-500 sm:text-4xl lg:text-5xl">{c.n}</div>
                                                        <p className="text-[11px] font-bold sm:text-[14px]">{c.l}</p>
                                                        <p className="mt-1 text-[10px] leading-relaxed text-gray-500 sm:text-[13px] dark:text-gray-400">{c.s}</p>
                                                    </div>
                                                </Reveal>
                                            ))}
                                        </div>
                                    </>
                                )}

                                {/* SLIDE 4: Revenue Model */}
                                {i === 3 && (
                                    <>
                                        <Reveal active={a === 3}><p className="mb-2 font-mono text-[9px] font-semibold uppercase tracking-[0.25em] text-brand-500 sm:mb-5 sm:text-[10px]">Revenue Model</p></Reveal>
                                        <Reveal active={a === 3} delay={100}><h2 className="mb-2 max-w-3xl text-[clamp(1.1rem,4.5vw,3.25rem)] font-bold leading-[1.08] tracking-tight sm:mb-6">Two revenue streams. <span className="text-brand-500 italic">Predictable growth.</span></h2></Reveal>
                                        <Reveal active={a === 3} delay={200}><p className="mb-4 max-w-2xl text-xs leading-relaxed text-gray-500 sm:mb-12 sm:text-lg dark:text-gray-400">SaaS subscription for analytics + usage-based Parcel Journey billing. Sellers pay more as they grow.</p></Reveal>
                                        <Reveal active={a === 3} delay={300}>
                                            <div className="grid gap-2 sm:gap-4 md:grid-cols-2">
                                                <div className="overflow-hidden rounded-2xl border border-gray-200/80 bg-white dark:border-white/6 dark:bg-zinc-900/80">
                                                    <div className="border-b border-gray-100 bg-stone-50/80 px-4 py-4 sm:px-6 dark:border-white/5 dark:bg-white/[0.02]">
                                                        <h3 className="text-[14px] font-bold">SaaS Plans</h3>
                                                        <p className="mt-0.5 font-mono text-[9px] uppercase tracking-wider text-gray-400">Monthly subscription</p>
                                                    </div>
                                                    <div className="space-y-3 px-4 py-5 sm:px-6">
                                                        {[
                                                            { plan: 'Starter', price: '₱1,499/mo', orders: '3,000 orders' },
                                                            { plan: 'Growth', price: '₱3,999/mo', orders: '10,000 orders' },
                                                            { plan: 'Scale', price: '₱9,999/mo', orders: '30,000 orders' },
                                                        ].map((p) => (
                                                            <div key={p.plan} className="flex items-center justify-between text-[13px]">
                                                                <div><span className="font-semibold">{p.plan}</span> <span className="text-gray-400">({p.orders})</span></div>
                                                                <span className="font-mono font-bold text-brand-600 dark:text-brand-400">{p.price}</span>
                                                            </div>
                                                        ))}
                                                    </div>
                                                </div>
                                                <div className="overflow-hidden rounded-2xl border border-gray-200/80 bg-white dark:border-white/6 dark:bg-zinc-900/80">
                                                    <div className="border-b border-gray-100 bg-stone-50/80 px-4 py-4 sm:px-6 dark:border-white/5 dark:bg-white/[0.02]">
                                                        <h3 className="text-[14px] font-bold">Parcel Journey</h3>
                                                        <p className="mt-0.5 font-mono text-[9px] uppercase tracking-wider text-gray-400">Per tracked delivered order</p>
                                                    </div>
                                                    <div className="space-y-3 px-4 py-5 sm:px-6">
                                                        {[
                                                            { plan: 'Starter', rate: '₱0.50/del.', cost: '₱1,050/mo' },
                                                            { plan: 'Growth', rate: '₱0.35/del.', cost: '₱2,450/mo' },
                                                            { plan: 'Scale', rate: '₱0.20/del.', cost: '₱4,200/mo' },
                                                        ].map((p) => (
                                                            <div key={p.plan} className="flex items-center justify-between text-[13px]">
                                                                <div><span className="font-semibold">{p.plan}</span> <span className="text-gray-400">({p.rate})</span></div>
                                                                <span className="font-mono font-bold text-brand-600 dark:text-brand-400">{p.cost}</span>
                                                            </div>
                                                        ))}
                                                    </div>
                                                </div>
                                            </div>
                                        </Reveal>

                                        <Reveal active={a === 3} delay={500}>
                                            <div className="mt-6 overflow-hidden rounded-2xl border border-gray-200/80 bg-white shadow-sm dark:border-white/6 dark:bg-zinc-900/80">
                                                <div className="border-b border-gray-100 bg-stone-50/80 px-4 py-4 sm:px-7 dark:border-white/5 dark:bg-white/[0.02]">
                                                    <p className="font-mono text-[10px] font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">Revenue projection assumptions</p>
                                                </div>
                                                <div className="grid grid-cols-2 gap-px bg-gray-100 lg:grid-cols-4 dark:bg-white/5">
                                                    {[
                                                        { label: 'Customer mix', value: '65 / 25 / 10%', sub: 'Starter / Growth / Scale' },
                                                        { label: 'Trial conversion', value: '15–25%', sub: 'Industry avg for B2B SaaS' },
                                                        { label: 'PJ adoption', value: '~50%', sub: 'Of paying users, 60% vol.' },
                                                        { label: 'Monthly churn', value: '8% → 5%', sub: 'Improving as product matures' },
                                                    ].map((item) => (
                                                        <div key={item.label} className="bg-white px-4 py-4 sm:px-5 dark:bg-zinc-900/80">
                                                            <p className="font-mono text-[9px] uppercase tracking-wider text-gray-400 dark:text-gray-500">{item.label}</p>
                                                            <p className="mt-1 text-[16px] font-bold tracking-tight">{item.value}</p>
                                                            <p className="mt-0.5 text-[10px] text-gray-400 dark:text-gray-500">{item.sub}</p>
                                                        </div>
                                                    ))}
                                                </div>
                                            </div>
                                        </Reveal>

                                        <Reveal active={a === 3} delay={650}>
                                            <div className="mt-4 overflow-hidden rounded-2xl border border-gray-200/80 bg-white shadow-sm dark:border-white/6 dark:bg-zinc-900/80">
                                                <div className="border-b border-gray-100 bg-stone-50/80 px-4 py-4 sm:px-7 dark:border-white/5 dark:bg-white/[0.02]">
                                                    <p className="font-mono text-[10px] font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">Growth trajectory (moderate scenario)</p>
                                                </div>
                                                {[
                                                    { phase: 'Month 1–3', label: 'Launch', cust: '5 → 25', base: '₱9.5k → ₱47.5k', pj: '₱2.3k → ₱15.7k', total: '₱11.8k → ₱63.2k', desc: 'Cold start. Free trials, early adopters, COD seller communities.' },
                                                    { phase: 'Month 4–6', label: 'Traction', cust: '40 → 80', base: '₱73k → ₱146k', pj: '₱28k → ₱69k', total: '₱101k → ₱215k', desc: 'Referrals kicking in, case studies published, word spreading.' },
                                                    { phase: 'Month 7–12', label: 'Growth', cust: '100 → 250', base: '₱182k → ₱456k', pj: '₱91k → ₱256k', total: '₱273k → ₱712k', desc: 'Product-market fit confirmed. Expanding outreach and integrations.' },
                                                ].map((r) => (
                                                    <div key={r.phase} className="border-b border-gray-100 px-4 py-4 last:border-0 sm:px-7 dark:border-white/5">
                                                        <div className="mb-2 flex items-center gap-3">
                                                            <span className="font-mono text-[11px] font-bold text-brand-600 dark:text-brand-400">{r.phase}</span>
                                                            <span className="rounded-full border border-gray-200 bg-gray-50 px-2 py-0.5 font-mono text-[8px] uppercase tracking-wider text-gray-400 dark:border-white/8 dark:bg-white/[0.03] dark:text-gray-500">{r.label}</span>
                                                        </div>
                                                        <p className="mb-3 text-[12px] text-gray-400 dark:text-gray-500">{r.desc}</p>
                                                        <div className="grid grid-cols-2 gap-2 sm:grid-cols-4">
                                                            <div><p className="font-mono text-[8px] uppercase tracking-wider text-gray-400 dark:text-gray-500">Customers</p><p className="text-[13px] font-bold">{r.cust}</p></div>
                                                            <div><p className="font-mono text-[8px] uppercase tracking-wider text-gray-400 dark:text-gray-500">Base MRR</p><p className="text-[13px] font-bold">{r.base}</p></div>
                                                            <div><p className="font-mono text-[8px] uppercase tracking-wider text-gray-400 dark:text-gray-500">PJ MRR</p><p className="text-[13px] font-bold">{r.pj}</p></div>
                                                            <div><p className="font-mono text-[8px] uppercase tracking-wider text-brand-500">Total MRR</p><p className="text-[13px] font-bold text-brand-600 dark:text-brand-400">{r.total}</p></div>
                                                        </div>
                                                    </div>
                                                ))}
                                            </div>
                                        </Reveal>

                                        <Reveal active={a === 3} delay={800}>
                                            <div className="mt-4 overflow-hidden rounded-2xl border-2 border-brand-500/80 shadow-xl shadow-brand-500/8 dark:border-brand-500/60">
                                                <div className="bg-gradient-to-r from-brand-50/90 to-white px-4 py-5 sm:px-7 sm:py-6 dark:from-brand-500/[0.06] dark:to-zinc-900">
                                                    <p className="mb-3 font-mono text-[10px] uppercase tracking-[0.2em] text-brand-600 dark:text-brand-400">Year 1 summary (moderate)</p>
                                                    <div className="flex flex-wrap items-baseline gap-x-6 gap-y-2 sm:gap-x-8 sm:gap-y-3">
                                                        <div><span className="text-2xl font-bold tracking-tight text-brand-600 sm:text-3xl dark:text-brand-400">₱712k</span><span className="ml-1.5 text-xs text-gray-400 sm:text-sm">MRR at Month 12</span></div>
                                                        <div><span className="text-xl font-bold tracking-tight sm:text-2xl">₱8.5M</span><span className="ml-1.5 text-xs text-gray-400 sm:text-sm">ARR run rate</span></div>
                                                        <div><span className="text-xl font-bold tracking-tight sm:text-2xl">₱4.5M</span><span className="ml-1.5 text-xs text-gray-400 sm:text-sm">total Year 1 revenue</span></div>
                                                        <div><span className="text-xl font-bold tracking-tight sm:text-2xl">250</span><span className="ml-1.5 text-xs text-gray-400 sm:text-sm">paying customers</span></div>
                                                    </div>
                                                </div>
                                            </div>
                                        </Reveal>
                                    </>
                                )}

                                {/* SLIDE 5: Equity Split */}
                                {i === 4 && (
                                    <>
                                        <Reveal active={a === 4}><p className="mb-2 font-mono text-[9px] font-semibold uppercase tracking-[0.25em] text-brand-500 sm:mb-5 sm:text-[10px]">Equity Structure</p></Reveal>
                                        <Reveal active={a === 4} delay={100}><h2 className="mb-2 max-w-3xl text-[clamp(1.1rem,4.5vw,3.25rem)] font-bold leading-[1.08] tracking-tight sm:mb-6">Fair equity for <span className="text-brand-500 italic">real contribution.</span></h2></Reveal>
                                        <Reveal active={a === 4} delay={200}><p className="mb-4 max-w-2xl text-xs leading-relaxed text-gray-500 sm:mb-12 sm:text-lg dark:text-gray-400">I built Artemis from zero. You helped make it better. This is how I propose we share ownership.</p></Reveal>
                                        <Reveal active={a === 4} delay={300}>
                                            <div className="grid grid-cols-2 gap-2 sm:gap-4 md:grid-cols-4">
                                                {[
                                                    { name: 'Bryan', role: 'Founder', pct: '70%', desc: 'Product vision, engineering, funding, full project ownership.', featured: true },
                                                    { name: 'Mark', role: 'Sales & Marketing', pct: '10%', desc: 'Focused on customer acquisition, outreach, and growth. Equity earned immediately.', featured: false },
                                                    { name: 'Keljash', role: 'Sales & Marketing', pct: '10%', desc: 'Focused on customer acquisition, outreach, and growth. Equity earned immediately.', featured: false },
                                                    { name: 'Beta Company', role: 'Strategic Partner', pct: '10%', desc: 'Network, coaching, guidance. Own server with free updates. Equity earned immediately.', featured: false },
                                                ].map((p) => (
                                                    <div key={p.name} className={`relative overflow-hidden rounded-2xl border p-3 sm:p-6 ${p.featured ? 'border-brand-500/80 bg-gradient-to-b from-brand-50/90 to-white shadow-xl shadow-brand-500/8 dark:border-brand-500/60 dark:from-brand-500/[0.06] dark:to-zinc-900' : 'border-gray-200/80 bg-white dark:border-white/6 dark:bg-zinc-900/80'}`}>
                                                        {p.featured && <div className="pointer-events-none absolute -right-10 -top-10 h-32 w-32 rounded-full bg-brand-500/10 blur-2xl" />}
                                                        <p className={`font-mono text-[9px] uppercase tracking-[0.2em] ${p.featured ? 'text-brand-600 dark:text-brand-400' : 'text-gray-400 dark:text-gray-500'}`}>{p.role}</p>
                                                        <div className="mt-2 mb-1 text-2xl font-bold tracking-tight sm:text-4xl lg:text-5xl">{p.pct}</div>
                                                        <h3 className="mb-2 text-[11px] font-bold tracking-tight sm:text-[14px]">{p.name}</h3>
                                                        <p className="text-[10px] leading-relaxed text-gray-500 sm:text-[13px] dark:text-gray-400">{p.desc}</p>
                                                    </div>
                                                ))}
                                            </div>
                                        </Reveal>
                                        <Reveal active={a === 4} delay={500}>
                                            <div className="mt-6 rounded-2xl border border-gray-200/80 bg-stone-50/80 px-4 py-4 sm:px-7 sm:py-5 dark:border-white/6 dark:bg-white/[0.02]">
                                                <p className="text-[13px] leading-relaxed text-gray-500 dark:text-gray-400">
                                                    <span className="font-semibold text-gray-700 dark:text-gray-300">Future option pool:</span> An additional 10% may be carved from Bryan's share for future hires and advisors — keeping everyone else undiluted.
                                                </p>
                                            </div>
                                        </Reveal>
                                    </>
                                )}

                                {/* SLIDE 6: Roles & Expectations */}
                                {i === 5 && (
                                    <>
                                        <Reveal active={a === 5}><p className="mb-2 font-mono text-[9px] font-semibold uppercase tracking-[0.25em] text-brand-500 sm:mb-5 sm:text-[10px]">Roles & Expectations</p></Reveal>
                                        <Reveal active={a === 5} delay={100}><h2 className="mb-2 max-w-3xl text-[clamp(1.1rem,4.5vw,3.25rem)] font-bold leading-[1.08] tracking-tight sm:mb-6">Everyone has a <span className="text-brand-500 italic">clear role.</span></h2></Reveal>
                                        <Reveal active={a === 5} delay={200}><p className="mb-4 max-w-2xl text-xs leading-relaxed text-gray-500 sm:mb-12 sm:text-lg dark:text-gray-400">Equity is tied to contribution. Here's what each partner is expected to bring to the table.</p></Reveal>

                                        {/* Mark & Keljash */}
                                        <Reveal active={a === 5} delay={300}>
                                            <div className="mb-4 overflow-hidden rounded-2xl border border-gray-200/80 bg-white dark:border-white/6 dark:bg-zinc-900/80">
                                                <div className="border-b border-gray-100 bg-stone-50/80 px-4 py-4 sm:px-7 dark:border-white/5 dark:bg-white/[0.02]">
                                                    <div className="flex items-center justify-between">
                                                        <div>
                                                            <h3 className="text-[15px] font-bold">Mark & Keljash</h3>
                                                            <p className="mt-0.5 font-mono text-[9px] uppercase tracking-wider text-gray-400 dark:text-gray-500">Sales & marketing — 10% each</p>
                                                        </div>
                                                        <span className="rounded-full border border-brand-200 bg-brand-50 px-2.5 py-0.5 font-mono text-[9px] font-bold text-brand-600 dark:border-brand-500/20 dark:bg-brand-500/10 dark:text-brand-400">Active</span>
                                                    </div>
                                                </div>
                                                <div className="grid gap-3 px-4 py-5 sm:grid-cols-3 sm:px-7">
                                                    {[
                                                        { t: 'Sales', d: 'Acquire and onboard new customers, close deals' },
                                                        { t: 'Marketing', d: 'Content, community outreach, seller group engagement' },
                                                        { t: 'Growth', d: 'Drive customer acquisition and retention strategies' },
                                                    ].map((r) => (
                                                        <div key={r.t} className="flex gap-3">
                                                            <svg className="mt-0.5 h-4 w-4 shrink-0 text-brand-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}><path strokeLinecap="round" strokeLinejoin="round" d="M5 13l4 4L19 7" /></svg>
                                                            <div><p className="text-[12px] font-semibold">{r.t}</p><p className="text-[11px] text-gray-400 dark:text-gray-500">{r.d}</p></div>
                                                        </div>
                                                    ))}
                                                </div>
                                            </div>
                                        </Reveal>

                                        {/* Beta Company */}
                                        <Reveal active={a === 5} delay={450}>
                                            <div className="overflow-hidden rounded-2xl border border-gray-200/80 bg-white dark:border-white/6 dark:bg-zinc-900/80">
                                                <div className="border-b border-gray-100 bg-stone-50/80 px-4 py-4 sm:px-7 dark:border-white/5 dark:bg-white/[0.02]">
                                                    <div className="flex items-center justify-between">
                                                        <div>
                                                            <h3 className="text-[15px] font-bold">Beta Company</h3>
                                                            <p className="mt-0.5 font-mono text-[9px] uppercase tracking-wider text-gray-400 dark:text-gray-500">Strategic partner — 10%</p>
                                                        </div>
                                                        <span className="rounded-full border border-amber-200 bg-amber-50 px-2.5 py-0.5 font-mono text-[9px] font-bold text-amber-600 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-400">Strategic</span>
                                                    </div>
                                                </div>
                                                <div className="grid gap-3 px-4 py-5 sm:grid-cols-3 sm:px-7">
                                                    {[
                                                        { t: 'Network & introductions', d: 'Open doors to COD sellers and industry contacts' },
                                                        { t: 'Coaching & guidance', d: 'Business mentoring and strategic direction' },
                                                        { t: 'Own server instance', d: 'Runs their own copy of Artemis — no monthly or annual fees' },
                                                        { t: 'Free updates forever', d: 'All future features and improvements included at no cost' },
                                                    ].map((r) => (
                                                        <div key={r.t} className="flex gap-3">
                                                            <svg className="mt-0.5 h-4 w-4 shrink-0 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}><path strokeLinecap="round" strokeLinejoin="round" d="M5 13l4 4L19 7" /></svg>
                                                            <div><p className="text-[12px] font-semibold">{r.t}</p><p className="text-[11px] text-gray-400 dark:text-gray-500">{r.d}</p></div>
                                                        </div>
                                                    ))}
                                                </div>
                                            </div>
                                        </Reveal>
                                    </>
                                )}

                                {/* SLIDE 7: Operating Costs */}
                                {i === 6 && (
                                    <>
                                        <Reveal active={a === 6}><p className="mb-2 font-mono text-[9px] font-semibold uppercase tracking-[0.25em] text-brand-500 sm:mb-5 sm:text-[10px]">Operating Costs</p></Reveal>
                                        <Reveal active={a === 6} delay={100}><h2 className="mb-2 max-w-3xl text-[clamp(1.1rem,4.5vw,3.25rem)] font-bold leading-[1.08] tracking-tight sm:mb-6">What it takes to <span className="text-brand-500 italic">run Artemis.</span></h2></Reveal>
                                        <Reveal active={a === 6} delay={200}><p className="mb-4 max-w-2xl text-xs leading-relaxed text-gray-500 sm:mb-12 sm:text-lg dark:text-gray-400">All operating costs are funded by Bryan. Partners are not expected to contribute financially.</p></Reveal>
                                        <Reveal active={a === 6} delay={300}>
                                            <div className="overflow-hidden rounded-2xl border border-gray-200/80 bg-white shadow-sm dark:border-white/6 dark:bg-zinc-900/80">
                                                <div className="border-b border-gray-100 bg-stone-50/80 px-4 py-4 sm:px-7 dark:border-white/5 dark:bg-white/[0.02]">
                                                    <p className="font-mono text-[10px] font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">Fixed monthly costs</p>
                                                </div>
                                                {[
                                                    { l: 'Server / Infrastructure', v: '₱3,000/mo', note: 'Minimum — scales proportionally with client base', c: 'bg-blue-light-500' },
                                                    { l: 'Domain', v: '₱1,500/yr', note: 'Annual renewal (~₱125/mo)', c: 'bg-gray-400' },
                                                    { l: 'Full-time Developer', v: '₱18,000/mo', note: 'Development and client support', c: 'bg-brand-500' },
                                                ].map((r) => (
                                                    <div key={r.l} className="flex items-center gap-3 border-b border-gray-100 px-4 py-4 last:border-0 sm:gap-4 sm:px-7 dark:border-white/5">
                                                        <span className={`h-2.5 w-2.5 shrink-0 rounded-sm ${r.c}`} />
                                                        <div className="min-w-0 flex-1">
                                                            <span className="text-[13px] font-semibold text-gray-700 sm:text-[14px] dark:text-gray-300">{r.l}</span>
                                                            <p className="mt-0.5 text-[11px] text-gray-400 dark:text-gray-500">{r.note}</p>
                                                        </div>
                                                        <span className="shrink-0 font-mono text-[13px] font-bold text-gray-900 sm:text-[15px] dark:text-gray-100">{r.v}</span>
                                                    </div>
                                                ))}
                                                <div className="flex items-center justify-between bg-brand-50/60 px-4 py-4 sm:px-7 sm:py-5 dark:bg-brand-500/5">
                                                    <span className="text-[13px] font-bold text-gray-900 sm:text-[15px] dark:text-gray-100">Total (minimum)</span>
                                                    <span className="font-mono text-lg font-bold text-brand-600 sm:text-2xl dark:text-brand-400">~₱21,125/mo</span>
                                                </div>
                                            </div>
                                        </Reveal>
                                        <Reveal active={a === 6} delay={500}>
                                            <div className="mt-6 grid gap-2 sm:gap-4 sm:grid-cols-2">
                                                <div className="rounded-2xl border border-gray-200/80 bg-white px-4 py-5 sm:px-6 dark:border-white/6 dark:bg-zinc-900/80">
                                                    <p className="font-mono text-[9px] uppercase tracking-wider text-gray-400 dark:text-gray-500">Cover operating costs</p>
                                                    <p className="mt-1 text-2xl font-bold tracking-tight">~10<span className="ml-1 text-sm font-normal text-gray-400">customers</span></p>
                                                    <p className="mt-1 text-[11px] text-gray-400 dark:text-gray-500">~₱23k MRR — server + domain + developer</p>
                                                </div>
                                                <div className="rounded-2xl border-2 border-brand-500/80 bg-gradient-to-b from-brand-50/90 to-white px-4 py-5 shadow-lg shadow-brand-500/5 sm:px-6 dark:border-brand-500/60 dark:from-brand-500/[0.06] dark:to-zinc-900">
                                                    <p className="font-mono text-[9px] uppercase tracking-wider text-brand-600 dark:text-brand-400">Profitable operation</p>
                                                    <p className="mt-1 text-2xl font-bold tracking-tight text-brand-600 dark:text-brand-400">~30<span className="ml-1 text-sm font-normal text-gray-400">customers</span></p>
                                                    <p className="mt-1 text-[11px] text-gray-400 dark:text-gray-500">~₱70k MRR — all costs covered, healthy margin</p>
                                                </div>
                                            </div>
                                        </Reveal>
                                        <Reveal active={a === 6} delay={700}>
                                            <div className="mt-6 rounded-2xl border border-gray-200/80 bg-stone-50/80 px-4 py-4 sm:px-7 sm:py-5 dark:border-white/6 dark:bg-white/[0.02]">
                                                <p className="text-[13px] leading-relaxed text-gray-500 dark:text-gray-400">
                                                    <span className="font-semibold text-gray-700 dark:text-gray-300">Server costs scale significantly with growth.</span> Starting at ₱3,000/mo but grows fast — ~₱10k at 80 customers, ~₱30k at 250, ~₱60k at 500. More clients means more data, more API calls, more processing power. The developer hire frees Bryan to focus on product strategy and growth.
                                                </p>
                                            </div>
                                        </Reveal>
                                    </>
                                )}

                                {/* SLIDE 8: Future Plans */}
                                {i === 7 && (
                                    <>
                                        <Reveal active={a === 7}><p className="mb-2 font-mono text-[9px] font-semibold uppercase tracking-[0.25em] text-brand-500 sm:mb-5 sm:text-[10px]">Future Plans</p></Reveal>
                                        <Reveal active={a === 7} delay={100}><h2 className="mb-2 max-w-3xl text-[clamp(1.1rem,4.5vw,3.25rem)] font-bold leading-[1.08] tracking-tight sm:mb-6">What's <span className="text-brand-500 italic">coming next.</span></h2></Reveal>
                                        <Reveal active={a === 7} delay={200}><p className="mb-4 max-w-2xl text-xs leading-relaxed text-gray-500 sm:mb-12 sm:text-lg dark:text-gray-400">Artemis is just getting started. Here's the roadmap — from features already in development to what we're researching.</p></Reveal>
                                        <Reveal active={a === 7} delay={300}>
                                            <div className="grid gap-2 sm:grid-cols-2 sm:gap-4 lg:grid-cols-3">
                                                {[
                                                    { t: 'RMO Call Statistics', d: 'Sync RMO call data directly into Artemis for unified analytics and reporting.', status: 'In Development', color: 'brand' },
                                                    { t: 'FB Ads Analytics', d: 'Full Facebook Ads analytics built into Artemis — a Super Ads replacement. Beta company currently pays ₱10k/mo for a similar tool.', status: 'Feasible', color: 'blue' },
                                                    { t: 'Auto Scale / Descale Ads', d: 'Automated ad budget scaling and descaling based on performance rules and thresholds.', status: 'Feasible', color: 'blue' },
                                                    { t: 'AUTO RMO Call', d: 'Automated RMO calls to customers — both standard scripted messages and AI-connected conversations.', status: 'In Research', color: 'amber' },
                                                    { t: 'Auto Process', d: 'End-to-end automation of order processing workflows to reduce manual work.', status: 'In Research', color: 'amber' },
                                                ].map((f, fi) => (
                                                    <Reveal key={f.t} active={a === 7} delay={400 + fi * 80}>
                                                        <div className="rounded-2xl border border-gray-200/80 bg-white p-3 sm:p-6 dark:border-white/6 dark:bg-zinc-900/80">
                                                            <div className="mb-3 flex items-center gap-2">
                                                                <span className={`rounded-full border px-2 py-0.5 font-mono text-[8px] font-bold uppercase tracking-[0.15em] ${
                                                                    f.color === 'brand' ? 'border-brand-200 bg-brand-50 text-brand-600 dark:border-brand-500/20 dark:bg-brand-500/10 dark:text-brand-400' :
                                                                    f.color === 'blue' ? 'border-blue-200 bg-blue-50 text-blue-600 dark:border-blue-500/20 dark:bg-blue-500/10 dark:text-blue-400' :
                                                                    'border-amber-200 bg-amber-50 text-amber-600 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-400'
                                                                }`}>{f.status}</span>
                                                            </div>
                                                            <h3 className="mb-2 text-[11px] font-bold sm:text-[14px]">{f.t}</h3>
                                                            <p className="text-[10px] leading-relaxed text-gray-500 sm:text-[13px] dark:text-gray-400">{f.d}</p>
                                                        </div>
                                                    </Reveal>
                                                ))}
                                            </div>
                                        </Reveal>
                                        <Reveal active={a === 7} delay={800}>
                                            <div className="mt-6 rounded-2xl border border-gray-200/80 bg-stone-50/80 px-4 py-4 sm:px-7 sm:py-5 dark:border-white/6 dark:bg-white/[0.02]">
                                                <p className="mb-2 text-[13px] font-semibold text-gray-700 dark:text-gray-300">Feature requests from partners</p>
                                                <p className="text-[13px] leading-relaxed text-gray-500 dark:text-gray-400">
                                                    The beta company may request features for Artemis. However, <span className="font-semibold text-gray-700 dark:text-gray-300">Bryan retains full discretion</span> on whether to prioritize and build them — especially if a requested feature is specific to the beta company only and not applicable to the broader customer base. The roadmap serves all Artemis customers, not just one partner.
                                                </p>
                                            </div>
                                        </Reveal>
                                    </>
                                )}

                                {/* SLIDE 9: What It's Worth */}
                                {i === 8 && (
                                    <>
                                        <Reveal active={a === 8}><p className="mb-2 font-mono text-[9px] font-semibold uppercase tracking-[0.25em] text-brand-500 sm:mb-5 sm:text-[10px]">What It's Worth</p></Reveal>
                                        <Reveal active={a === 8} delay={100}><h2 className="mb-2 max-w-3xl text-[clamp(1.1rem,4.5vw,3.25rem)] font-bold leading-[1.08] tracking-tight sm:mb-6">Equity in a working SaaS is <span className="text-brand-500 italic">not nothing.</span></h2></Reveal>
                                        <Reveal active={a === 8} delay={200}><p className="mb-4 max-w-2xl text-xs leading-relaxed text-gray-500 sm:mb-12 sm:text-lg dark:text-gray-400">Equity value is based on <span className="font-semibold text-gray-700 dark:text-gray-300">net profit after operating expenses</span> — not raw revenue. Here's what 10% could be worth.</p></Reveal>

                                        {/* Full breakdown table */}
                                        <Reveal active={a === 8} delay={300}>
                                            <div className="overflow-hidden rounded-2xl border border-gray-200/80 bg-white shadow-sm dark:border-white/6 dark:bg-zinc-900/80">
                                                <div className="border-b border-gray-100 bg-stone-50/80 px-4 py-4 sm:px-7 dark:border-white/5 dark:bg-white/[0.02]">
                                                    <p className="font-mono text-[10px] font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">Revenue vs. net profit — 10% equity value</p>
                                                </div>
                                                {[
                                                    { stage: 'Month 6', cust: '~80', mrr: '₱215k', opex: '₱28k', net: '₱187k', annual: '₱2.24M', tenPct: '₱224,000/yr' },
                                                    { stage: 'Month 12', cust: '~250', mrr: '₱712k', opex: '₱48k', net: '₱664k', annual: '₱7.97M', tenPct: '₱797,000/yr' },
                                                    { stage: 'Year 2', cust: '~500', mrr: '₱1.4M', opex: '₱78k', net: '₱1.32M', annual: '₱15.9M', tenPct: '₱1,590,000/yr' },
                                                ].map((r, ri) => (
                                                    <div key={r.stage} className="border-b border-gray-100 px-4 py-4 last:border-0 sm:px-7 dark:border-white/5">
                                                        <div className="mb-2 flex items-center gap-3">
                                                            <span className="font-mono text-[11px] font-bold text-brand-600 dark:text-brand-400">{r.stage}</span>
                                                            <span className="text-[11px] text-gray-400">({r.cust} customers)</span>
                                                        </div>
                                                        <div className="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-5">
                                                            <div><p className="font-mono text-[8px] uppercase tracking-wider text-gray-400 dark:text-gray-500">MRR</p><p className="text-[13px] font-bold">{r.mrr}</p></div>
                                                            <div><p className="font-mono text-[8px] uppercase tracking-wider text-gray-400 dark:text-gray-500">Opex/mo</p><p className="text-[13px] font-bold text-rose-500">-{r.opex}</p></div>
                                                            <div><p className="font-mono text-[8px] uppercase tracking-wider text-gray-400 dark:text-gray-500">Net/mo</p><p className="text-[13px] font-bold">{r.net}</p></div>
                                                            <div><p className="font-mono text-[8px] uppercase tracking-wider text-gray-400 dark:text-gray-500">Net/yr</p><p className="text-[13px] font-bold">{r.annual}</p></div>
                                                            <div><p className="font-mono text-[8px] uppercase tracking-wider text-brand-500">10% share</p><p className="text-[13px] font-bold text-brand-600 dark:text-brand-400">{r.tenPct}</p></div>
                                                        </div>
                                                    </div>
                                                ))}
                                            </div>
                                        </Reveal>

                                        <Reveal active={a === 8} delay={500}>
                                            <div className="mt-6 rounded-2xl border border-gray-200/80 bg-stone-50/80 px-4 py-4 sm:px-7 sm:py-5 dark:border-white/6 dark:bg-white/[0.02]">
                                                <p className="text-[13px] leading-relaxed text-gray-500 dark:text-gray-400">
                                                    <span className="font-semibold text-gray-700 dark:text-gray-300">Opex assumptions:</span> Server starts at ₱3k/mo minimum, scaling significantly with client base — ~₱10k at 80 customers, ~₱30k at 250, ~₱60k at 500 (more data, API calls, processing). Developer at ₱18k/mo. Domain ₱125/mo. All funded by Bryan.
                                                </p>
                                            </div>
                                        </Reveal>
                                    </>
                                )}

                                {/* SLIDE 10: Next Steps */}
                                {i === 9 && (
                                    <div className="relative text-center">
                                        <div className="pointer-events-none absolute left-1/2 top-1/2 h-[500px] w-[700px] -translate-x-1/2 -translate-y-1/2 bg-[radial-gradient(ellipse,rgba(16,211,161,0.1),transparent_55%)]" />
                                        <Reveal active={a === 9}><p className="mb-2 font-mono text-[9px] font-semibold uppercase tracking-[0.25em] text-brand-500 sm:mb-5 sm:text-[10px]">Next Steps</p></Reveal>
                                        <Reveal active={a === 9} delay={100}><h2 className="mb-2 text-[clamp(1.1rem,4.5vw,3.25rem)] font-bold leading-[1.08] tracking-tight sm:mb-6">Let's make this <span className="text-brand-500 italic">official.</span></h2></Reveal>
                                        <Reveal active={a === 9} delay={200}>
                                            <div className="mx-auto grid max-w-2xl gap-2 text-left sm:gap-4 sm:grid-cols-2">
                                                {[
                                                    { n: '01', t: 'Align on terms', d: 'Review this proposal. Discuss equity percentages and conditions.' },
                                                    { n: '02', t: 'Agree on roles', d: 'Define what continued contribution looks like for each partner going forward.' },
                                                    { n: '03', t: 'Legal paperwork', d: 'Engage a lawyer to draft a shareholders\' agreement and IP assignment.' },
                                                    { n: '04', t: 'Build together', d: 'Ship the product. Grow the business. Execute the roadmap.' },
                                                ].map((s, si) => (
                                                    <Reveal key={s.n} active={a === 9} delay={300 + si * 100}>
                                                        <div className="rounded-2xl border border-gray-200/80 bg-white p-3 sm:p-6 dark:border-white/6 dark:bg-zinc-900/80">
                                                            <p className="mb-2 font-mono text-[11px] font-bold uppercase tracking-[0.2em] text-brand-500">{s.n}</p>
                                                            <h4 className="mb-1.5 text-[11px] font-bold tracking-tight sm:text-[14px]">{s.t}</h4>
                                                            <p className="text-[10px] leading-relaxed text-gray-500 sm:text-[13px] dark:text-gray-400">{s.d}</p>
                                                        </div>
                                                    </Reveal>
                                                ))}
                                            </div>
                                        </Reveal>
                                        <Reveal active={a === 9} delay={700}>
                                            <div className="mt-8 mb-5 flex items-center justify-center gap-4 sm:mt-12">
                                                <div className="h-px w-16 bg-gradient-to-r from-transparent to-gray-200 dark:to-white/8" />
                                                <img src="/img/logo/artemis.png" alt="" className="h-12 w-12 object-contain" />
                                                <div className="h-px w-16 bg-gradient-to-l from-transparent to-gray-200 dark:to-white/8" />
                                            </div>
                                            <p className="mb-3 text-2xl font-bold tracking-tight sm:text-3xl">We built something <span className="bg-gradient-to-br from-brand-500 to-brand-700 bg-clip-text italic text-transparent">real.</span></p>
                                            <p className="mx-auto max-w-md text-[14px] text-gray-500 dark:text-gray-400">Let's own it together — fairly, transparently, and with commitment.</p>
                                            <p className="mt-6 font-mono text-[11px] uppercase tracking-[0.2em] text-gray-400 dark:text-gray-500">— Bryan</p>
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
