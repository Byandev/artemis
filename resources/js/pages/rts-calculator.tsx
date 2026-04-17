import AppearanceToggleDropdown from '@/components/appearance-dropdown';
import { register } from '@/routes';
import { type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { useCallback, useEffect, useState } from 'react';

const fmt = (n: number) => n.toLocaleString('en-PH', { maximumFractionDigits: 0 });
const fmtPeso = (n: number) => `₱${fmt(n)}`;

function useDebounced<T>(value: T, ms = 150): T {
    const [debounced, setDebounced] = useState(value);
    useEffect(() => {
        const t = setTimeout(() => setDebounced(value), ms);
        return () => clearTimeout(t);
    }, [value, ms]);
    return debounced;
}

function clamp(v: number, min: number, max: number) {
    return Math.min(max, Math.max(min, v));
}

interface Inputs {
    orders: number;
    aov: number;
    rtsPct: number;
    marginPct: number;
    forward: number;
    returnFee: number;
    packaging: number;
    damagePct: number;
    cogs: number;
}

const DEFAULTS: Inputs = {
    orders: 1000,
    aov: 500,
    rtsPct: 30,
    marginPct: 35,
    forward: 120,
    returnFee: 100,
    packaging: 15,
    damagePct: 10,
    cogs: 200,
};

function compute(i: Inputs) {
    const orders = Math.max(0, i.orders || 0);
    const aov = Math.max(0, i.aov || 0);
    const rtsPct = clamp(i.rtsPct || 0, 0, 100);
    const marginPct = clamp(i.marginPct || 0, 0, 100);
    const forward = Math.max(0, i.forward || 0);
    const returnFee = Math.max(0, i.returnFee || 0);
    const packaging = Math.max(0, i.packaging || 0);
    const damagePct = clamp(i.damagePct || 0, 0, 100);
    const cogs = Math.max(0, i.cogs || 0);

    const failedOrders = orders * (rtsPct / 100);
    const profitPerOrder = aov * (marginPct / 100);
    const damageLoss = (damagePct / 100) * cogs;
    const costPerRts = forward + returnFee + packaging + damageLoss + profitPerOrder;

    const conservativeMonthly = failedOrders * profitPerOrder;
    const conservativeYearly = conservativeMonthly * 12;

    const fullMonthly = failedOrders * costPerRts;
    const fullYearly = fullMonthly * 12;

    const reducedRtsPct = rtsPct * 0.9;
    const reducedFailed = orders * (reducedRtsPct / 100);
    const reducedBleed = reducedFailed * costPerRts;
    const monthlySavings = fullMonthly - reducedBleed;
    const yearlySavings = monthlySavings * 12;

    const rentMonths = Math.floor(fullYearly / 25000);
    const employees = Math.floor(fullYearly / (18000 * 12));
    const fbAdMonths = Math.floor(fullYearly / 50000);
    const inventoryBatches = Math.floor(fullYearly / 100000);

    return {
        failedOrders,
        profitPerOrder,
        damageLoss,
        costPerRts,
        conservativeMonthly,
        conservativeYearly,
        fullMonthly,
        fullYearly,
        monthlySavings,
        yearlySavings,
        forward,
        returnFee,
        packaging,
        rentMonths,
        employees,
        fbAdMonths,
        inventoryBatches,
    };
}

function NumberInput({
    id,
    label,
    value,
    onChange,
    prefix,
    suffix,
    tooltip,
    min = 0,
    max,
}: {
    id: string;
    label: string;
    value: number;
    onChange: (v: number) => void;
    prefix?: string;
    suffix?: string;
    tooltip?: string;
    min?: number;
    max?: number;
}) {
    const [tooltipOpen, setTooltipOpen] = useState(false);

    return (
        <div className="space-y-1.5">
            <div className="flex items-center gap-2">
                <label htmlFor={id} className="block font-mono text-[10px] font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500">
                    {label}
                </label>
                {tooltip && (
                    <div className="relative">
                        <button
                            type="button"
                            onClick={() => setTooltipOpen(!tooltipOpen)}
                            onBlur={() => setTooltipOpen(false)}
                            className="flex h-[15px] w-[15px] items-center justify-center rounded-full border border-gray-300 text-[8px] font-bold text-gray-400 transition-all hover:border-emerald-400 hover:bg-emerald-50 hover:text-emerald-600 dark:border-white/15 dark:text-gray-500 dark:hover:border-emerald-500/40 dark:hover:bg-emerald-500/10 dark:hover:text-emerald-400"
                            aria-label={`Info: ${label}`}
                        >
                            ?
                        </button>
                        {tooltipOpen && (
                            <div className="absolute bottom-full left-1/2 z-10 mb-2 w-56 -translate-x-1/2 rounded-[10px] border border-black/6 bg-white p-3 text-[11px] leading-relaxed text-gray-500 shadow-xl dark:border-white/8 dark:bg-zinc-800 dark:text-gray-400">
                                {tooltip}
                                <div className="absolute -bottom-[5px] left-1/2 h-2.5 w-2.5 -translate-x-1/2 rotate-45 border-b border-r border-black/6 bg-white dark:border-white/8 dark:bg-zinc-800" />
                            </div>
                        )}
                    </div>
                )}
            </div>
            <div className="flex items-center overflow-hidden rounded-[10px] border border-black/8 bg-stone-50 transition-all focus-within:border-emerald-500 focus-within:ring-2 focus-within:ring-emerald-500/15 dark:border-white/8 dark:bg-zinc-800 dark:focus-within:border-emerald-400">
                {prefix && (
                    <span className="flex h-10 items-center border-r border-black/6 bg-stone-100/80 px-3 font-mono text-[12px] text-gray-400 dark:border-white/6 dark:bg-white/5 dark:text-gray-500">
                        {prefix}
                    </span>
                )}
                <input
                    id={id}
                    type="number"
                    value={value || ''}
                    onChange={(e) => {
                        const raw = e.target.value;
                        if (raw === '') { onChange(0); return; }
                        let v = parseFloat(raw);
                        if (isNaN(v)) v = 0;
                        if (max !== undefined) v = Math.min(v, max);
                        if (min !== undefined) v = Math.max(v, min);
                        onChange(v);
                    }}
                    min={min}
                    max={max}
                    className="h-10 w-full flex-1 bg-transparent px-3 font-mono! text-[13px]! font-medium text-gray-800 outline-none [appearance:textfield] placeholder:text-gray-300 dark:text-gray-100 dark:placeholder:text-gray-600 [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none"
                />
                {suffix && (
                    <span className="flex h-10 items-center border-l border-black/6 bg-stone-100/80 px-3 font-mono text-[12px] text-gray-400 dark:border-white/6 dark:bg-white/5 dark:text-gray-500">
                        {suffix}
                    </span>
                )}
            </div>
        </div>
    );
}

const BAR_SEGMENTS_META = [
    { key: 'forward', label: 'Forward shipping', color: '#0BA5EC' },
    { key: 'return', label: 'Return shipping', color: '#36BFFA' },
    { key: 'packaging', label: 'Packaging', color: '#98A2B3' },
    { key: 'damage', label: 'Damage loss', color: '#F97316' },
    { key: 'profit', label: 'Unrealized profit', color: '#10D3A1' },
];

export default function RtsCalculator() {
    const { auth } = usePage<SharedData>().props;
    const [inputs, setInputs] = useState<Inputs>(DEFAULTS);
    const [showAdvanced, setShowAdvanced] = useState(false);
    const [copied, setCopied] = useState(false);

    const debounced = useDebounced(inputs);
    const r = compute(debounced);

    const set = useCallback(<K extends keyof Inputs>(key: K, val: Inputs[K]) => {
        setInputs((prev) => ({ ...prev, [key]: val }));
    }, []);

    const barValues = [r.forward, r.returnFee, r.packaging, r.damageLoss, r.profitPerOrder];
    const barTotal = barValues.reduce((s, v) => s + v, 0);

    const handleCopy = async () => {
        const text = `I'm losing ${fmtPeso(r.fullMonthly)}/month to RTS on my PH COD business.\nThat's ${fmtPeso(r.fullYearly)}/year.\n\nBreakdown per failed parcel:\n- Unrealized profit: ${fmtPeso(r.profitPerOrder)}\n- Forward shipping: ${fmtPeso(r.forward)}\n- Return shipping: ${fmtPeso(r.returnFee)}\n- Packaging: ${fmtPeso(r.packaging)}\n- Damage loss: ${fmtPeso(r.damageLoss)}\n\nCalculated with Artemis — artemis.com/rts-calculator`;
        try {
            await navigator.clipboard.writeText(text);
            setCopied(true);
            setTimeout(() => setCopied(false), 2500);
        } catch { /* noop */ }
    };

    const ctaHref = auth.user ? '/dashboard' : register();

    return (
        <>
            <Head title="RTS Calculator — See how much failed deliveries cost you | Artemis">
                <meta name="description" content="Free tool for Philippine COD sellers. Calculate your real RTS bleed in pesos per month and year. See where the money goes." />
                <meta property="og:title" content="RTS Calculator — See how much failed deliveries cost you | Artemis" />
                <meta property="og:description" content="Free tool for Philippine COD sellers. Calculate your real RTS bleed in pesos per month and year." />
                <meta property="og:type" content="website" />
                <script type="application/ld+json">
                    {JSON.stringify({
                        '@context': 'https://schema.org',
                        '@type': 'WebApplication',
                        name: 'RTS Bleed Calculator',
                        description: 'Calculate how much return-to-sender is costing your Philippine COD e-commerce business.',
                        applicationCategory: 'BusinessApplication',
                        operatingSystem: 'All',
                        offers: { '@type': 'Offer', price: '0', priceCurrency: 'PHP' },
                    })}
                </script>
            </Head>

            <div className="relative min-h-screen overflow-hidden bg-white text-gray-900 dark:bg-zinc-950 dark:text-gray-100">
                {/* Ambient background */}
                <div className="pointer-events-none absolute -top-60 left-1/2 h-[700px] w-[1100px] -translate-x-1/2 bg-[radial-gradient(ellipse,rgba(16,211,161,0.1),transparent_65%)]" />
                <div className="pointer-events-none absolute bottom-0 right-0 h-[500px] w-[500px] bg-[radial-gradient(circle,rgba(16,211,161,0.06),transparent_60%)]" />
                <div className="pointer-events-none absolute inset-0 bg-[linear-gradient(rgba(0,0,0,0.025)_1px,transparent_1px),linear-gradient(90deg,rgba(0,0,0,0.025)_1px,transparent_1px)] dark:bg-[linear-gradient(rgba(255,255,255,0.012)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,0.012)_1px,transparent_1px)] bg-[size:60px_60px]" />

                {/* Nav */}
                <nav className="sticky top-0 z-50 border-b border-gray-200/80 bg-white/80 backdrop-blur-2xl dark:border-white/6 dark:bg-zinc-950/80">
                    <div className="mx-auto flex max-w-[1200px] items-center justify-between px-5 py-4 md:px-10">
                        <Link href="/" className="flex items-center gap-3">
                            <img src="/img/logo/artemis.png" alt="Artemis" className="h-9 w-9 object-contain" />
                            <span className="text-[22px] font-semibold tracking-tight">Artemis</span>
                        </Link>
                        <div className="flex items-center gap-6 text-sm">
                            <Link href="/#features" className="hidden text-[13px] text-gray-500 transition-colors hover:text-brand-500 dark:text-gray-400 dark:hover:text-brand-400 md:block">
                                Features
                            </Link>
                            <Link href="/#pricing" className="hidden text-[13px] text-gray-500 transition-colors hover:text-brand-500 dark:text-gray-400 dark:hover:text-brand-400 md:block">
                                Pricing
                            </Link>
                            <Link href="/#how" className="hidden text-[13px] text-gray-500 transition-colors hover:text-brand-500 dark:text-gray-400 dark:hover:text-brand-400 md:block">
                                How it works
                            </Link>
                            <Link href="/rts-calculator" className="hidden text-[13px] font-medium text-brand-600 transition-colors hover:text-brand-500 dark:text-brand-400 dark:hover:text-brand-300 md:block">
                                RTS Calculator
                            </Link>
                            <div className="hidden h-4 w-px bg-gray-200 dark:bg-white/10 md:block" />
                            <AppearanceToggleDropdown />
                            {auth.user ? (
                                <Link href="/dashboard" className="inline-flex h-9 items-center rounded-lg bg-brand-500! px-5 text-[13px] font-semibold text-white shadow-sm shadow-brand-500/20 transition-all hover:bg-brand-600 hover:shadow-md hover:shadow-brand-500/25">
                                    Dashboard
                                </Link>
                            ) : (
                                <>
                                    <Link href="/login" className="hidden text-[13px] text-gray-500 transition-colors hover:text-brand-500 dark:text-gray-400 dark:hover:text-brand-400 md:block">
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
                <section className="relative px-5 pb-6 pt-20 md:px-10 md:pt-28 lg:pt-32">
                    <div className="relative mx-auto max-w-[1200px] text-center">
                        <div className="mb-8 inline-flex items-center gap-2.5 rounded-full border border-brand-200/60 bg-brand-50/80 px-4 py-2 dark:border-brand-500/15 dark:bg-brand-500/8">
                            <span className="h-1.5 w-1.5 rounded-full bg-brand-500 shadow-[0_0_6px_var(--color-brand-500)]" />
                            <span className="font-mono text-[10px] font-semibold uppercase tracking-[0.2em] text-brand-700 dark:text-brand-400">
                                Free tool · No signup required
                            </span>
                        </div>
                        <h1 className="mx-auto mb-6 text-[clamp(2.25rem,5.5vw,3.75rem)] font-bold! leading-[1.05]! tracking-tight">
                            See exactly how much
                            <br />
                            RTS is{' '}
                            <span className="bg-gradient-to-br from-brand-500 to-brand-700 bg-clip-text italic text-transparent">costing you.</span>
                        </h1>
                        <p className="mx-auto mb-3 max-w-lg text-[17px] leading-relaxed text-gray-500 dark:text-gray-400">
                            Enter your numbers below. We'll show you the bleed — in pesos, per month, per year. No guesswork.
                        </p>
                        <p className="mx-auto max-w-lg text-[13px] leading-relaxed text-gray-400 dark:text-gray-500">
                            Every COD seller has an RTS bleed. Most just don't know how big it is.
                        </p>
                    </div>
                </section>

                {/* Calculator */}
                <section className="relative px-5 py-14 md:px-10 md:py-20">
                    <div className="relative mx-auto grid max-w-[1200px] items-start gap-8 lg:grid-cols-[minmax(0,5fr)_minmax(0,7fr)] lg:gap-10">

                        {/* ── LEFT: Inputs ── */}
                        <div className="space-y-6 lg:sticky lg:top-28">
                            <div className="overflow-hidden rounded-2xl border border-black/6 bg-white shadow-sm dark:border-white/6 dark:bg-zinc-900/80">
                                <div className="border-b border-black/4 bg-stone-50/80 px-6 py-5 dark:border-white/5 dark:bg-white/[0.02]">
                                    <h2 className="text-[14px] font-bold tracking-tight text-gray-900 dark:text-gray-100">Your business numbers</h2>
                                    <p className="mt-1 font-mono text-[10px] uppercase tracking-wider text-gray-400 dark:text-gray-500">Adjust the values — results update instantly</p>
                                </div>
                                <div className="space-y-5 px-6 py-6">
                                    <NumberInput id="orders" label="Monthly orders" value={inputs.orders} onChange={(v) => set('orders', v)} />
                                    <NumberInput id="aov" label="Average Order Value (AOV)" value={inputs.aov} onChange={(v) => set('aov', v)} prefix="₱" />
                                    <NumberInput id="rtsPct" label="Current RTS rate" value={inputs.rtsPct} onChange={(v) => set('rtsPct', v)} suffix="%" max={100} tooltip="Don't know? PH average is 25–35%. If you're on TikTok Shop COD, it could be 30–45%." />
                                    <NumberInput id="marginPct" label="Profit margin" value={inputs.marginPct} onChange={(v) => set('marginPct', v)} suffix="%" max={100} tooltip="(Revenue − All costs) ÷ Revenue × 100. Not sure? Most COD sellers in PH sit between 25–45%." />
                                </div>
                            </div>

                            {/* Advanced toggle */}
                            <div className="overflow-hidden rounded-2xl border border-black/6 bg-white shadow-sm dark:border-white/6 dark:bg-zinc-900/80">
                                <button
                                    onClick={() => setShowAdvanced(!showAdvanced)}
                                    className="flex w-full items-center justify-between px-6 py-4 text-left transition-colors hover:bg-stone-50/80 dark:hover:bg-white/[0.02]"
                                >
                                    <div>
                                        <span className="text-[13px] font-semibold text-gray-800 dark:text-gray-200">Advanced inputs</span>
                                        <p className="mt-0.5 font-mono text-[10px] uppercase tracking-wider text-gray-400 dark:text-gray-500">Shipping, packaging, damage & COGS</p>
                                    </div>
                                    <div className={`flex h-7 w-7 items-center justify-center rounded-full border transition-all ${showAdvanced ? 'rotate-180 border-emerald-400 bg-emerald-50 dark:border-emerald-500/40 dark:bg-emerald-500/10' : 'border-gray-200 dark:border-white/10'}`}>
                                        <svg className={`h-3.5 w-3.5 ${showAdvanced ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-400'}`} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                                            <path strokeLinecap="round" strokeLinejoin="round" d="M19 9l-7 7-7-7" />
                                        </svg>
                                    </div>
                                </button>
                                {showAdvanced && (
                                    <div className="space-y-5 border-t border-black/4 px-6 py-6 dark:border-white/5">
                                        <NumberInput id="forward" label="Forward shipping cost" value={inputs.forward} onChange={(v) => set('forward', v)} prefix="₱" />
                                        <NumberInput id="returnFee" label="Return shipping cost" value={inputs.returnFee} onChange={(v) => set('returnFee', v)} prefix="₱" />
                                        <NumberInput id="packaging" label="Packaging cost per parcel" value={inputs.packaging} onChange={(v) => set('packaging', v)} prefix="₱" />
                                        <NumberInput id="damagePct" label="Damage rate on returns" value={inputs.damagePct} onChange={(v) => set('damagePct', v)} suffix="%" max={100} />
                                        <NumberInput id="cogs" label="COGS per item" value={inputs.cogs} onChange={(v) => set('cogs', v)} prefix="₱" />
                                    </div>
                                )}
                            </div>
                        </div>

                        {/* ── RIGHT: Results ── */}
                        <div className="space-y-6" aria-live="polite">

                            {/* Failed parcels — context setter */}
                            <div className="overflow-hidden rounded-2xl border border-gray-200/80 bg-white shadow-sm dark:border-white/6 dark:bg-zinc-900/80">
                                <div className="flex items-center gap-4 px-7 py-6 md:px-8">
                                    <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl border border-orange-200 bg-orange-50 dark:border-orange-500/20 dark:bg-orange-500/10">
                                        <svg className="h-5 w-5 text-orange-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.8}>
                                            <path strokeLinecap="round" strokeLinejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5m6 4.125l2.25 2.25m0 0l2.25-2.25M12 13.875V7.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </div>
                                    <div className="flex-1">
                                        <p className="font-mono text-[10px] uppercase tracking-[0.2em] text-gray-400 dark:text-gray-500">Failed parcels per month</p>
                                        <div className="mt-1 flex items-baseline gap-3">
                                            <span className="text-3xl font-bold tracking-tight text-gray-900 dark:text-gray-100">{fmt(r.failedOrders)}</span>
                                            <span className="text-sm text-gray-400">parcels</span>
                                            <span className="text-[11px] text-gray-300 dark:text-gray-600">|</span>
                                            <span className="text-sm font-medium text-gray-500 dark:text-gray-400">{fmt(r.failedOrders * 12)}/year</span>
                                        </div>
                                    </div>
                                </div>
                                <div className="border-t border-gray-100 bg-gray-50/40 px-7 py-3 dark:border-white/5 dark:bg-white/[0.015]">
                                    <p className="text-[12px] leading-relaxed text-gray-400 dark:text-gray-500">
                                        Out of {fmt(debounced.orders)} monthly orders, <span className="font-semibold text-gray-600 dark:text-gray-300">{debounced.rtsPct}% are returned</span> — that's {fmt(r.failedOrders)} parcels that cost you money with zero revenue.
                                    </p>
                                </div>
                            </div>

                            {/* Conservative estimate */}
                            <div className="overflow-hidden rounded-2xl border border-gray-200/80 bg-white shadow-sm dark:border-white/6 dark:bg-zinc-900/80">
                                <div className="px-7 py-7 md:px-8">
                                    <div className="mb-5 flex items-start justify-between">
                                        <div>
                                            <p className="font-mono text-[10px] uppercase tracking-[0.2em] text-gray-400 dark:text-gray-500">Conservative estimate</p>
                                            <h3 className="mt-1.5 text-[15px] font-bold tracking-tight text-gray-900 dark:text-gray-100">Unrealized profit</h3>
                                        </div>
                                        <div className="flex h-8 items-center rounded-full border border-gray-200 bg-gray-50 px-3 font-mono text-[10px] uppercase tracking-[0.15em] text-gray-400 dark:border-white/8 dark:bg-white/[0.03] dark:text-gray-500">
                                            Margin only
                                        </div>
                                    </div>

                                    <div className="text-[clamp(1.75rem,4vw,2.5rem)] font-bold tracking-tight text-gray-900 dark:text-gray-100">
                                        {fmtPeso(r.conservativeMonthly)}
                                        <span className="ml-1.5 text-base font-normal text-gray-400">/ month</span>
                                    </div>
                                    <div className="mt-1 text-lg text-gray-500 dark:text-gray-400">
                                        {fmtPeso(r.conservativeYearly)}
                                        <span className="ml-1 text-sm text-gray-400">/ year</span>
                                    </div>
                                </div>

                                {/* Explanation */}
                                <div className="border-t border-gray-100 bg-gray-50/40 px-7 py-5 dark:border-white/5 dark:bg-white/[0.015] md:px-8">
                                    <p className="mb-3 text-[12px] font-semibold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">What this counts</p>
                                    <p className="mb-4 text-[13px] leading-relaxed text-gray-500 dark:text-gray-400">
                                        This is the profit you <span className="font-semibold text-gray-700 dark:text-gray-300">would have earned</span> from each failed order — the sale that didn't happen. It only counts lost margin, nothing else. Think of it as the <span className="italic">minimum</span> you're losing.
                                    </p>
                                    <div className="rounded-lg border border-gray-200/80 bg-white px-4 py-3 dark:border-white/6 dark:bg-zinc-800/60">
                                        <p className="font-mono text-[11px] leading-relaxed text-gray-500 dark:text-gray-400">
                                            <span className="text-gray-400 dark:text-gray-500">{fmt(r.failedOrders)} failed orders</span>
                                            {' '}×{' '}
                                            <span className="text-gray-400 dark:text-gray-500">₱{fmt(debounced.aov)} AOV</span>
                                            {' '}×{' '}
                                            <span className="text-gray-400 dark:text-gray-500">{debounced.marginPct}% margin</span>
                                            {' '}={' '}
                                            <span className="font-bold text-gray-900 dark:text-gray-100">{fmtPeso(r.conservativeMonthly)}</span>
                                        </p>
                                    </div>
                                </div>
                            </div>

                            {/* Full RTS Bleed — FEATURED */}
                            <div className="relative overflow-hidden rounded-2xl border-2 border-brand-500/80 shadow-xl shadow-brand-500/8 dark:border-brand-500/60">
                                {/* Glow effects */}
                                <div className="pointer-events-none absolute -right-20 -top-20 h-52 w-52 rounded-full bg-brand-500/15 blur-3xl" />
                                <div className="pointer-events-none absolute -bottom-16 -left-16 h-40 w-40 rounded-full bg-brand-400/10 blur-3xl" />

                                <div className="relative bg-gradient-to-b from-brand-50/90 to-white dark:from-brand-500/[0.06] dark:to-zinc-900">
                                    <div className="px-7 py-8 md:px-8">
                                        <div className="mb-6 flex items-start justify-between">
                                            <div>
                                                <p className="font-mono text-[10px] uppercase tracking-[0.2em] text-brand-600 dark:text-brand-400">Full RTS bleed</p>
                                                <h3 className="mt-1.5 text-[15px] font-bold tracking-tight text-gray-900 dark:text-gray-100">Total cost of failed deliveries</h3>
                                            </div>
                                            <div className="flex h-8 items-center rounded-full border border-brand-300 bg-brand-50 px-3 font-mono text-[10px] uppercase tracking-[0.15em] text-brand-600 dark:border-brand-500/30 dark:bg-brand-500/10 dark:text-brand-400">
                                                All costs
                                            </div>
                                        </div>

                                        <div className="text-[clamp(2rem,5vw,3.25rem)] font-bold italic tracking-tight text-brand-600 dark:text-brand-400">
                                            {fmtPeso(r.fullMonthly)}
                                            <span className="ml-2 text-lg font-normal not-italic text-gray-400">/ month</span>
                                        </div>
                                        <div className="mt-2 text-xl font-semibold text-gray-700 dark:text-gray-300">
                                            {fmtPeso(r.fullYearly)}
                                            <span className="ml-1 text-sm font-normal text-gray-400">/ year</span>
                                        </div>

                                        {/* Difference callout */}
                                        <div className="mt-5 inline-flex items-center gap-2 rounded-lg border border-orange-200/80 bg-orange-50/80 px-3.5 py-2 dark:border-orange-500/15 dark:bg-orange-500/8">
                                            <svg className="h-3.5 w-3.5 text-orange-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                                <path strokeLinecap="round" strokeLinejoin="round" d="M4.5 10.5L12 3m0 0l7.5 7.5M12 3v18" />
                                            </svg>
                                            <span className="text-[12px] font-semibold text-orange-700 dark:text-orange-400">
                                                {fmtPeso(r.fullMonthly - r.conservativeMonthly)} more than the conservative estimate
                                            </span>
                                        </div>
                                    </div>

                                    {/* Explanation */}
                                    <div className="border-t border-brand-200/50 bg-brand-50/30 px-7 py-5 dark:border-brand-500/10 dark:bg-brand-500/[0.03] md:px-8">
                                        <p className="mb-3 text-[12px] font-semibold uppercase tracking-[0.1em] text-brand-600/70 dark:text-brand-400/70">What this counts</p>
                                        <p className="mb-4 text-[13px] leading-relaxed text-gray-600 dark:text-gray-400">
                                            The <span className="font-semibold text-gray-800 dark:text-gray-200">real cost</span> of every RTS goes far beyond lost profit. You paid to ship it there. You pay to ship it back. The packaging is wasted. Some items come back damaged. This adds up to the <span className="font-semibold text-brand-600 dark:text-brand-400">true bleed</span> — what RTS actually costs your business.
                                        </p>

                                        {/* Formula breakdown */}
                                        <div className="space-y-2">
                                            <p className="text-[11px] font-semibold uppercase tracking-[0.1em] text-gray-400 dark:text-gray-500">Cost per failed parcel = {fmtPeso(r.costPerRts)}</p>
                                            <div className="space-y-1.5 rounded-lg border border-gray-200/80 bg-white px-4 py-3 dark:border-white/6 dark:bg-zinc-800/60">
                                                {[
                                                    { label: 'Forward shipping', value: r.forward, desc: 'You paid to send it' },
                                                    { label: 'Return shipping', value: r.returnFee, desc: 'You pay to get it back' },
                                                    { label: 'Packaging', value: r.packaging, desc: 'Box, tape, filler — wasted' },
                                                    { label: 'Damage loss', value: r.damageLoss, desc: `${debounced.damagePct}% of items come back unsellable` },
                                                    { label: 'Unrealized profit', value: r.profitPerOrder, desc: 'The sale that didn\'t happen' },
                                                ].map((row) => (
                                                    <div key={row.label} className="flex items-center justify-between gap-3 text-[12px]">
                                                        <div className="flex items-center gap-2">
                                                            <span className="text-gray-600 dark:text-gray-300">{row.label}</span>
                                                            <span className="hidden text-gray-300 dark:text-gray-600 sm:inline">— {row.desc}</span>
                                                        </div>
                                                        <span className="shrink-0 font-mono font-semibold text-gray-900 dark:text-gray-100">{fmtPeso(row.value)}</span>
                                                    </div>
                                                ))}
                                                <div className="mt-2 flex items-center justify-between border-t border-dashed border-gray-200 pt-2 dark:border-white/8">
                                                    <span className="text-[12px] font-bold text-gray-900 dark:text-gray-100">Total per RTS parcel</span>
                                                    <span className="font-mono text-[13px] font-bold text-brand-600 dark:text-brand-400">{fmtPeso(r.costPerRts)}</span>
                                                </div>
                                            </div>
                                            <p className="mt-1 font-mono text-[11px] text-gray-400 dark:text-gray-500">
                                                × {fmt(r.failedOrders)} failed parcels = <span className="font-bold text-gray-700 dark:text-gray-300">{fmtPeso(r.fullMonthly)}/mo</span>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                {/* ── Cost breakdown bar ── */}
                <section className="relative px-5 py-6 md:px-10 md:py-10">
                    <div className="mx-auto max-w-[1200px]">
                        <div className="overflow-hidden rounded-2xl border border-gray-200/80 bg-white shadow-sm dark:border-white/6 dark:bg-zinc-900/80">
                            <div className="px-7 py-7 md:px-8">
                                <p className="mb-2 font-mono text-[10px] uppercase tracking-[0.2em] text-brand-500">Cost anatomy</p>
                                <h3 className="mb-2 text-xl font-bold tracking-tight text-gray-900 dark:text-gray-100 md:text-2xl">
                                    Where the{' '}
                                    <span className="text-brand-500">{fmtPeso(r.costPerRts)}</span>{' '}
                                    per failed parcel goes
                                </h3>
                                <p className="mb-8 max-w-xl text-[13px] leading-relaxed text-gray-400 dark:text-gray-500">
                                    Each segment represents a real cost you absorb on every returned order. The wider the segment, the larger its share of your loss.
                                </p>

                                {/* Bar */}
                                <div className="overflow-x-auto pb-2">
                                    <div className="flex min-w-[480px] overflow-hidden rounded-xl" style={{ height: 56 }}>
                                        {BAR_SEGMENTS_META.map((seg, idx) => {
                                            const val = barValues[idx];
                                            const pct = barTotal > 0 ? (val / barTotal) * 100 : 0;
                                            if (pct <= 0) return null;
                                            return (
                                                <div
                                                    key={seg.key}
                                                    className="flex items-center justify-center text-[11px] font-bold text-white transition-all duration-500"
                                                    style={{ width: `${pct}%`, minWidth: pct > 2 ? undefined : 4, backgroundColor: seg.color }}
                                                    title={`${seg.label}: ${fmtPeso(val)}`}
                                                >
                                                    {pct > 12 && fmtPeso(val)}
                                                </div>
                                            );
                                        })}
                                    </div>
                                </div>

                                {/* Legend grid */}
                                <div className="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                                    {BAR_SEGMENTS_META.map((seg, idx) => {
                                        const val = barValues[idx];
                                        const pct = barTotal > 0 ? ((val / barTotal) * 100).toFixed(0) : '0';
                                        return (
                                            <div key={seg.key} className="flex items-center gap-2.5 rounded-lg border border-gray-100 px-3 py-2.5 dark:border-white/5">
                                                <span className="h-3 w-3 shrink-0 rounded-sm" style={{ backgroundColor: seg.color }} />
                                                <div>
                                                    <p className="text-[11px] text-gray-500 dark:text-gray-400">{seg.label}</p>
                                                    <p className="text-[13px] font-bold text-gray-900 dark:text-gray-100">{fmtPeso(val)} <span className="font-normal text-gray-400">({pct}%)</span></p>
                                                </div>
                                            </div>
                                        );
                                    })}
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                {/* ── Emotional framing ── */}
                <section className="relative overflow-hidden px-5 py-20 md:px-10 md:py-28">
                    <div className="pointer-events-none absolute left-0 top-0 h-px w-full bg-gradient-to-r from-transparent via-gray-200 to-transparent dark:via-white/8" />
                    <div className="mx-auto max-w-[1200px]">
                        <div className="mb-12 max-w-2xl">
                            <p className="mb-4 font-mono text-[10px] uppercase tracking-[0.2em] text-brand-500">What this really means</p>
                            <h2 className="mb-4 text-[clamp(1.5rem,3.5vw,2.5rem)] font-bold leading-tight tracking-tight">
                                Your annual bleed of{' '}
                                <span className="text-brand-500">{fmtPeso(r.fullYearly)}</span>
                                <br className="hidden md:block" />
                                could have paid for:
                            </h2>
                            <p className="text-[14px] leading-relaxed text-gray-400 dark:text-gray-500">
                                Real numbers, real opportunity cost. This is money leaving your business that could fund growth.
                            </p>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4" aria-live="polite">
                            {[
                                { num: r.rentMonths, unit: 'months', label: 'of office rent', sub: '₱25,000/mo', icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6' },
                                { num: r.employees, unit: '', label: 'full-time employees', sub: '₱18,000/mo salary', icon: 'M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z' },
                                { num: r.fbAdMonths, unit: 'months', label: 'of Facebook ads', sub: '₱50,000/mo budget', icon: 'M10.34 15.84c-.688-.06-1.386-.09-2.09-.09H7.5a4.5 4.5 0 110-9h.75c.704 0 1.402-.03 2.09-.09m0 9.18c.253.962.584 1.892.985 2.783.247.55.06 1.21-.463 1.511l-.657.38a.75.75 0 01-1.022-.288l-.3-.508a11.157 11.157 0 01-.793-1.878m1.25-7.72a11.17 11.17 0 00.793-1.878l.3-.508a.75.75 0 011.022-.288l.657.38c.524.3.71.96.463 1.511a12.67 12.67 0 01-.985 2.783m-1.25-7.72h3.5a4.5 4.5 0 110 9h-3.5' },
                                { num: r.inventoryBatches, unit: '', label: 'inventory restocks', sub: '₱100,000 per batch', icon: 'M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z' },
                            ].map((c, i) => (
                                <div key={i} className="group overflow-hidden rounded-2xl border border-gray-200/80 bg-white transition-all hover:-translate-y-1 hover:shadow-lg hover:shadow-gray-200/40 dark:border-white/6 dark:bg-zinc-900/80 dark:hover:shadow-black/20">
                                    <div className="px-6 py-7">
                                        <div className="mb-5 flex h-10 w-10 items-center justify-center rounded-xl border border-brand-200/60 bg-brand-50/80 dark:border-brand-500/20 dark:bg-brand-500/10">
                                            <svg className="h-5 w-5 text-brand-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
                                                <path strokeLinecap="round" strokeLinejoin="round" d={c.icon} />
                                            </svg>
                                        </div>
                                        <div className="mb-1 flex items-baseline gap-1.5">
                                            <span className="text-4xl font-bold tracking-tight text-gray-900 dark:text-gray-100">{fmt(c.num)}</span>
                                            {c.unit && <span className="text-lg font-medium text-gray-400">{c.unit}</span>}
                                        </div>
                                        <p className="text-[14px] font-semibold text-gray-700 dark:text-gray-300">{c.label}</p>
                                        <p className="mt-1 text-[12px] text-gray-400 dark:text-gray-500">at {c.sub}</p>
                                    </div>
                                </div>
                            ))}
                        </div>
                        <p className="mt-8 font-mono text-[11px] uppercase tracking-[0.15em] text-gray-400 dark:text-gray-600">
                            — at your current {debounced.rtsPct}% RTS rate
                        </p>
                    </div>
                </section>

                {/* ── Savings pitch ── */}
                <section className="relative overflow-hidden py-24 md:py-32">
                    <div className="pointer-events-none absolute inset-0 bg-gradient-to-b from-gray-50/80 via-transparent to-gray-50/80 dark:from-zinc-900/40 dark:via-transparent dark:to-zinc-900/40" />
                    <div className="pointer-events-none absolute left-1/2 top-1/2 h-[600px] w-[800px] -translate-x-1/2 -translate-y-1/2 bg-[radial-gradient(ellipse,rgba(16,211,161,0.1),transparent_55%)]" />

                    <div className="relative mx-auto max-w-[1200px] px-5 text-center md:px-10">
                        <div className="mb-6 inline-flex items-center gap-2 rounded-full border border-brand-200/60 bg-brand-50/80 px-4 py-2 dark:border-brand-500/15 dark:bg-brand-500/8">
                            <svg className="h-3.5 w-3.5 text-brand-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                <path strokeLinecap="round" strokeLinejoin="round" d="M2.25 18L9 11.25l4.306 4.307a11.95 11.95 0 015.814-5.519l2.74-1.22m0 0l-5.94-2.28m5.94 2.28l-2.28 5.941" />
                            </svg>
                            <span className="font-mono text-[10px] font-semibold uppercase tracking-[0.2em] text-brand-700 dark:text-brand-400">The opportunity</span>
                        </div>

                        <h2 className="mx-auto mb-5 max-w-2xl text-[clamp(1.75rem,4vw,3rem)] font-bold leading-tight tracking-tight">
                            Now imagine cutting
                            <br className="hidden sm:block" />
                            that by just{' '}
                            <span className="bg-gradient-to-br from-brand-500 to-brand-700 bg-clip-text italic text-transparent">10%.</span>
                        </h2>
                        <p className="mx-auto mb-4 max-w-xl text-[16px] leading-relaxed text-gray-500 dark:text-gray-400">
                            If Artemis helps you reduce RTS by just 10% — typical within 90 days — you'd save:
                        </p>

                        {/* Savings highlight */}
                        <div className="mx-auto mb-10 flex max-w-lg flex-col items-center gap-4 sm:flex-row sm:justify-center sm:gap-8">
                            <div className="text-center">
                                <div className="text-4xl font-bold tracking-tight text-brand-500 md:text-5xl">{fmtPeso(r.monthlySavings)}</div>
                                <p className="mt-1 text-sm text-gray-400">every month</p>
                            </div>
                            <div className="hidden h-12 w-px bg-gray-200 dark:bg-white/8 sm:block" />
                            <div className="text-center">
                                <div className="text-4xl font-bold tracking-tight text-gray-900 dark:text-gray-100 md:text-5xl">{fmtPeso(r.yearlySavings)}</div>
                                <p className="mt-1 text-sm text-gray-400">every year</p>
                            </div>
                        </div>

                        <div className="mb-6 flex flex-col items-center justify-center gap-3 sm:flex-row">
                            <Link
                                href={ctaHref}
                                className="inline-flex h-12 items-center gap-2.5 rounded-xl bg-brand-500! px-8 text-[15px] font-semibold text-white shadow-lg shadow-brand-500/20 transition-all hover:-translate-y-0.5 hover:bg-brand-600 hover:shadow-xl hover:shadow-brand-500/25"
                            >
                                Start free trial
                                <svg width="16" height="12" viewBox="0 0 16 12" fill="none"><path d="M1 6h14m0 0L10 1m5 5l-5 5" stroke="currentColor" strokeWidth="2" strokeLinecap="round" /></svg>
                            </Link>
                            <Link
                                href="/#how"
                                className="inline-flex h-12 items-center rounded-xl border border-gray-200 bg-white px-8 text-[15px] font-semibold text-gray-700 transition-all hover:border-brand-300 hover:text-brand-600 dark:border-white/10 dark:bg-zinc-900 dark:text-gray-300 dark:hover:border-brand-500/40 dark:hover:text-brand-400"
                            >
                                See how Artemis works
                            </Link>
                        </div>

                        <p className="mx-auto max-w-md text-[11px] leading-relaxed text-gray-400 dark:text-gray-600">
                            Projection assumes 10% relative reduction in RTS. Actual results vary by product, audience, and courier. We don't promise a specific endpoint.
                        </p>
                    </div>
                </section>

                {/* ── Share / Copy ── */}
                <section className="relative px-5 py-8 md:px-10">
                    <div className="mx-auto max-w-[1200px]">
                        <div className="flex flex-col items-center justify-between gap-5 rounded-2xl border border-gray-200/80 bg-gray-50/60 px-7 py-6 sm:flex-row dark:border-white/6 dark:bg-white/[0.02]">
                            <div>
                                <h3 className="text-[15px] font-bold text-gray-900 dark:text-gray-100">Share your results</h3>
                                <p className="mt-0.5 text-[13px] text-gray-400 dark:text-gray-500">Copy a detailed summary to share with your team or partners.</p>
                            </div>
                            <button
                                onClick={handleCopy}
                                className={`inline-flex h-10 shrink-0 items-center gap-2 rounded-lg border px-5 text-[13px] font-semibold transition-all ${
                                    copied
                                        ? 'border-brand-300 bg-brand-50 text-brand-600 dark:border-brand-500/30 dark:bg-brand-500/10 dark:text-brand-400'
                                        : 'border-gray-200 bg-white text-gray-700 hover:border-brand-300 hover:text-brand-600 dark:border-white/10 dark:bg-zinc-800 dark:text-gray-300 dark:hover:border-brand-500/40 dark:hover:text-brand-400'
                                }`}
                            >
                                {copied ? (
                                    <>
                                        <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}><path strokeLinecap="round" strokeLinejoin="round" d="M5 13l4 4L19 7" /></svg>
                                        Copied to clipboard
                                    </>
                                ) : (
                                    <>
                                        <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}><rect x="9" y="9" width="13" height="13" rx="2" /><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1" /></svg>
                                        Copy my results
                                    </>
                                )}
                            </button>
                        </div>
                    </div>
                </section>

                {/* ── Footer ── */}
                <footer className="mt-8 border-t border-gray-200/80 dark:border-white/6">
                    <div className="mx-auto max-w-[1200px] px-5 py-10 md:px-10">
                        <div className="flex flex-col items-center justify-between gap-5 sm:flex-row">
                            <Link href="/" className="flex items-center gap-2.5">
                                <img src="/img/logo/artemis.png" alt="Artemis" className="h-7 w-7 object-contain" />
                                <span className="text-lg font-semibold tracking-tight">Artemis</span>
                            </Link>
                            <div className="flex items-center gap-6 text-[12px] text-gray-400 dark:text-gray-500">
                                <Link href="/" className="transition-colors hover:text-brand-500">Home</Link>
                                <Link href="/#features" className="transition-colors hover:text-brand-500">Features</Link>
                                <Link href="/#pricing" className="transition-colors hover:text-brand-500">Pricing</Link>
                            </div>
                            <p className="font-mono text-[11px] tracking-[0.08em] text-gray-400 dark:text-gray-600">
                                &copy; {new Date().getFullYear()} Artemis
                            </p>
                        </div>
                    </div>
                </footer>
            </div>
        </>
    );
}
