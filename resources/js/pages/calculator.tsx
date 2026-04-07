import { Head, Link } from '@inertiajs/react';
import { useState, useMemo } from 'react';
import { home } from '@/routes';

const peso = (val: number) =>
    '₱' + val.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

interface SliderProps {
    label: string;
    value: number;
    min: number;
    max: number;
    step: number;
    onChange: (v: number) => void;
    format?: (v: number) => string;
}

function Slider({ label, value, min, max, step, onChange, format }: SliderProps) {
    const display = format ? format(value) : value.toLocaleString();
    const pct = ((value - min) / (max - min)) * 100;

    return (
        <div className="space-y-2">
            <div className="flex items-center justify-between">
                <span className="font-mono text-[11px] font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500">
                    {label}
                </span>
                <span className="font-mono text-[13px] font-semibold text-gray-800 dark:text-gray-100">
                    {display}
                </span>
            </div>
            <div className="relative">
                <input
                    type="range"
                    min={min}
                    max={max}
                    step={step}
                    value={value}
                    onChange={(e) => onChange(parseFloat(e.target.value))}
                    className="slider-input h-1.5 w-full cursor-pointer appearance-none rounded-full bg-gray-200 outline-none dark:bg-zinc-700"
                    style={{
                        background: `linear-gradient(to right, #10b981 ${pct}%, transparent ${pct}%)`,
                    }}
                />
            </div>
            <div className="flex justify-between font-mono text-[10px] text-gray-300 dark:text-gray-600">
                <span>{format ? format(min) : min.toLocaleString()}</span>
                <span>{format ? format(max) : max.toLocaleString()}</span>
            </div>
        </div>
    );
}

interface ResultRowProps {
    label: string;
    value: string;
    variant?: 'default' | 'cost' | 'profit' | 'muted' | 'highlight';
}

function ResultRow({ label, value, variant = 'default' }: ResultRowProps) {
    const rowClass = {
        default: 'border-b border-black/4 dark:border-white/4',
        cost: 'border-b border-black/4 dark:border-white/4',
        profit: 'rounded-xl bg-emerald-500/10 mt-1',
        muted: 'border-b border-black/4 dark:border-white/4 opacity-60',
        highlight: 'rounded-xl bg-blue-500/10 mt-1',
    }[variant];

    const labelClass = {
        default: 'text-gray-600 dark:text-gray-400',
        cost: 'text-red-500 dark:text-red-400',
        profit: 'text-emerald-600 dark:text-emerald-400 font-semibold',
        muted: 'text-gray-400 dark:text-gray-500',
        highlight: 'text-blue-600 dark:text-blue-400 font-semibold',
    }[variant];

    const valueClass = {
        default: 'text-gray-800 dark:text-gray-100',
        cost: 'text-red-500 dark:text-red-400',
        profit: 'text-emerald-600 dark:text-emerald-400 font-bold text-[15px]',
        muted: 'text-gray-400 dark:text-gray-500',
        highlight: 'text-blue-600 dark:text-blue-400 font-bold',
    }[variant];

    return (
        <div className={`flex items-center justify-between px-4 py-2.5 ${rowClass}`}>
            <span className={`font-mono text-[12px] ${labelClass}`}>{label}</span>
            <span className={`font-mono text-[13px] ${valueClass}`}>{value}</span>
        </div>
    );
}

export default function Calculator() {
    const [roas, setRoas] = useState(3);
    const [pricing, setPricing] = useState(399);
    const [cog, setCog] = useState(45);
    const [adSpent, setAdSpent] = useState(10000);
    const [days, setDays] = useState(30);
    const [rtsPercent, setRtsPercent] = useState(18);
    const [shippingAmount, setShippingAmount] = useState(65);
    const [codFeePercent, setCodFeePercent] = useState(2);

    const calc = useMemo(() => {
        const grossSales = adSpent * roas * days;
        const orders = grossSales / pricing;
        const deliveredOrders = orders * (1 - rtsPercent / 100);

        const shippingFee = orders * shippingAmount;
        const codFee = grossSales * (codFeePercent / 100);
        const cogTotal = orders * cog;
        const adSpentTotal = adSpent * days;
        const rtsCost = grossSales * (rtsPercent / 100);

        const totalCosts = shippingFee + codFee + cogTotal + adSpentTotal + rtsCost;
        const grossProfit = grossSales - totalCosts;

        const gencysCutProfit = grossProfit * 0.30;
        const gencysCutDelivered = deliveredOrders * pricing * 0.09;

        return {
            grossSales,
            orders,
            deliveredOrders,
            shippingFee,
            codFee,
            cogTotal,
            adSpentTotal,
            rtsCost,
            totalCosts,
            grossProfit,
            gencysCutProfit,
            gencysCutDelivered,
            margin: grossSales > 0 ? (grossProfit / grossSales) * 100 : 0,
        };
    }, [roas, pricing, cog, adSpent, days, rtsPercent, shippingAmount, codFeePercent]);

    const pct = (v: number) => v.toFixed(2) + '%';

    return (
        <>
            <Head title="Profit Calculator — Artemis" />

            <style>{`
                .slider-input::-webkit-slider-thumb {
                    -webkit-appearance: none;
                    width: 16px;
                    height: 16px;
                    border-radius: 50%;
                    background: #10b981;
                    cursor: pointer;
                    box-shadow: 0 0 0 3px rgba(16,185,129,0.2);
                }
                .slider-input::-moz-range-thumb {
                    width: 16px;
                    height: 16px;
                    border-radius: 50%;
                    background: #10b981;
                    cursor: pointer;
                    border: none;
                    box-shadow: 0 0 0 3px rgba(16,185,129,0.2);
                }
            `}</style>

            <div className="min-h-screen bg-gray-50 dark:bg-zinc-950">
                {/* Header */}
                <header className="border-b border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
                    <div className="mx-auto flex max-w-6xl items-center justify-between px-6 py-4">
                        <Link href={home().url} className="flex items-center gap-2.5">
                            <img src="/img/logo/artemis.png" alt="Artemis" className="h-7 w-7 object-contain" />
                            <span className="font-semibold tracking-tight text-gray-900 dark:text-white">Artemis</span>
                        </Link>
                        <span className="font-mono text-[11px] font-semibold uppercase tracking-widest text-gray-400 dark:text-gray-500">
                            Profit Calculator
                        </span>
                    </div>
                </header>

                <main className="mx-auto max-w-6xl px-6 py-10">
                    <div className="mb-8">
                        <h1 className="text-[28px] font-bold tracking-tight text-gray-900 dark:text-white">
                            Profit Calculator
                        </h1>
                        <p className="mt-1 font-mono text-[13px] text-gray-400 dark:text-gray-500">
                            Adjust the inputs to see your estimated profit in real time.
                        </p>
                    </div>

                    <div className="grid gap-6 lg:grid-cols-2">
                        {/* Left — Inputs */}
                        <div className="space-y-6">
                            {/* Campaign */}
                            <div className="rounded-2xl border border-black/6 bg-white p-6 dark:border-white/6 dark:bg-zinc-900">
                                <p className="mb-5 font-mono text-[10px] font-semibold uppercase tracking-widest text-gray-300 dark:text-gray-600">
                                    Campaign
                                </p>
                                <div className="space-y-6">
                                    <Slider
                                        label="ROAS"
                                        value={roas}
                                        min={1}
                                        max={15}
                                        step={0.5}
                                        onChange={setRoas}
                                        format={(v) => v.toFixed(1) + 'x'}
                                    />
                                    <Slider
                                        label="Daily Ad Spend"
                                        value={adSpent}
                                        min={1000}
                                        max={100000}
                                        step={500}
                                        onChange={setAdSpent}
                                        format={peso}
                                    />
                                    <Slider
                                        label="Days Running"
                                        value={days}
                                        min={1}
                                        max={90}
                                        step={1}
                                        onChange={setDays}
                                        format={(v) => v + ' days'}
                                    />
                                </div>
                            </div>

                            {/* Product */}
                            <div className="rounded-2xl border border-black/6 bg-white p-6 dark:border-white/6 dark:bg-zinc-900">
                                <p className="mb-5 font-mono text-[10px] font-semibold uppercase tracking-widest text-gray-300 dark:text-gray-600">
                                    Product
                                </p>
                                <div className="space-y-6">
                                    <Slider
                                        label="Selling Price"
                                        value={pricing}
                                        min={99}
                                        max={5000}
                                        step={1}
                                        onChange={setPricing}
                                        format={peso}
                                    />
                                    <Slider
                                        label="Cost of Goods (per unit)"
                                        value={cog}
                                        min={10}
                                        max={2000}
                                        step={5}
                                        onChange={setCog}
                                        format={peso}
                                    />
                                </div>
                            </div>

                            {/* Logistics */}
                            <div className="rounded-2xl border border-black/6 bg-white p-6 dark:border-white/6 dark:bg-zinc-900">
                                <p className="mb-5 font-mono text-[10px] font-semibold uppercase tracking-widest text-gray-300 dark:text-gray-600">
                                    Logistics
                                </p>
                                <div className="space-y-6">
                                    <Slider
                                        label="Shipping Fee (per order)"
                                        value={shippingAmount}
                                        min={30}
                                        max={300}
                                        step={5}
                                        onChange={setShippingAmount}
                                        format={peso}
                                    />
                                    <Slider
                                        label="RTS Rate"
                                        value={rtsPercent}
                                        min={0}
                                        max={60}
                                        step={0.5}
                                        onChange={setRtsPercent}
                                        format={(v) => v.toFixed(1) + '%'}
                                    />
                                    <Slider
                                        label="COD Fee"
                                        value={codFeePercent}
                                        min={0}
                                        max={5}
                                        step={0.1}
                                        onChange={setCodFeePercent}
                                        format={(v) => v.toFixed(1) + '%'}
                                    />
                                </div>
                            </div>
                        </div>

                        {/* Right — Results */}
                        <div className="space-y-6">
                            {/* Summary cards */}
                            <div className="grid grid-cols-2 gap-3">
                                <div className="rounded-2xl border border-black/6 bg-white p-4 dark:border-white/6 dark:bg-zinc-900">
                                    <p className="font-mono text-[10px] font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500">Gross Sales</p>
                                    <p className="mt-1 font-mono text-[20px] font-bold text-gray-900 dark:text-white">{peso(calc.grossSales)}</p>
                                </div>
                                <div className={`rounded-2xl border p-4 ${calc.grossProfit >= 0 ? 'border-emerald-500/20 bg-emerald-500/5' : 'border-red-500/20 bg-red-500/5'}`}>
                                    <p className="font-mono text-[10px] font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500">Gross Profit</p>
                                    <p className={`mt-1 font-mono text-[20px] font-bold ${calc.grossProfit >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-500'}`}>
                                        {peso(calc.grossProfit)}
                                    </p>
                                </div>
                                <div className="rounded-2xl border border-black/6 bg-white p-4 dark:border-white/6 dark:bg-zinc-900">
                                    <p className="font-mono text-[10px] font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500">Orders</p>
                                    <p className="mt-1 font-mono text-[20px] font-bold text-gray-900 dark:text-white">{Math.round(calc.orders).toLocaleString()}</p>
                                    <p className="mt-0.5 font-mono text-[10px] text-gray-400">{Math.round(calc.deliveredOrders).toLocaleString()} delivered</p>
                                </div>
                                <div className="rounded-2xl border border-black/6 bg-white p-4 dark:border-white/6 dark:bg-zinc-900">
                                    <p className="font-mono text-[10px] font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500">Profit Margin</p>
                                    <p className={`mt-1 font-mono text-[20px] font-bold ${calc.margin >= 0 ? 'text-gray-900 dark:text-white' : 'text-red-500'}`}>
                                        {calc.margin.toFixed(1)}%
                                    </p>
                                </div>
                            </div>

                            {/* Breakdown */}
                            <div className="rounded-2xl border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900 overflow-hidden">
                                <div className="border-b border-black/6 px-4 py-3 dark:border-white/6">
                                    <p className="font-mono text-[10px] font-semibold uppercase tracking-widest text-gray-300 dark:text-gray-600">
                                        Breakdown
                                    </p>
                                </div>

                                <ResultRow label="Gross Sales" value={peso(calc.grossSales)} />

                                <div className="px-4 pt-3 pb-1">
                                    <p className="font-mono text-[10px] font-semibold uppercase tracking-widest text-red-400/70">Costs</p>
                                </div>
                                <ResultRow label="Shipping Fee" value={`− ${peso(calc.shippingFee)}`} variant="cost" />
                                <ResultRow label="COD Fee" value={`− ${peso(calc.codFee)}`} variant="cost" />
                                <ResultRow label="Cost of Goods" value={`− ${peso(calc.cogTotal)}`} variant="cost" />
                                <ResultRow label={`Ad Spend (${days}d)`} value={`− ${peso(calc.adSpentTotal)}`} variant="cost" />
                                <ResultRow label={`RTS Loss (${pct(rtsPercent)})`} value={`− ${peso(calc.rtsCost)}`} variant="cost" />

                                <div className="px-4 py-3">
                                    <ResultRow label="Gross Profit" value={peso(calc.grossProfit)} variant="profit" />
                                </div>
                            </div>

                            {/* Gency's Cut */}
                            <div className="rounded-2xl border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900 overflow-hidden">
                                <div className="border-b border-black/6 px-4 py-3 dark:border-white/6">
                                    <p className="font-mono text-[10px] font-semibold uppercase tracking-widest text-gray-300 dark:text-gray-600">
                                        Gency's Cut
                                    </p>
                                </div>
                                <ResultRow
                                    label="30% of Gross Profit"
                                    value={peso(calc.gencysCutProfit)}
                                    variant="highlight"
                                />
                                <div className="px-4 py-3">
                                    <ResultRow
                                        label="9% of Delivered Revenue"
                                        value={peso(calc.gencysCutDelivered)}
                                        variant="highlight"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                </main>
            </div>
        </>
    );
}
