import PageHeader from '@/components/common/PageHeader';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { Head } from '@inertiajs/react';
import { Calculator, RotateCcw } from 'lucide-react';
import { useMemo, useState } from 'react';

interface Workspace {
    id: number;
    name: string;
    slug: string;
    is_gencys_partner: boolean;
}

interface Props {
    workspace: Workspace;
}

/**
 * Every value on this page is derived from the inputs below — the page is a
 * pure client-side calculator, there is no server-side computation.
 *
 *   adspentTotal      = adSpentPerDay × days
 *   grossSales        = roas × adspentTotal
 *   odzAmount         = grossSales × odzPct
 *   lessOdz           = grossSales − odzAmount
 *   parcel            = lessOdz ÷ pricing
 *   rtsAmount         = lessOdz × rtsPct
 *   lessRts           = lessOdz − rtsAmount
 *   shipping          = parcel × shippingAmount
 *   codFee            = lessOdz × codPct
 *   cogTotal          = parcel × cogUnit
 *   grossProfit       = lessRts − shipping − codFee − cogTotal − adspentTotal
 *   advisoryShare     = max(grossProfit, 0) × 30%   (Gencys partner workspaces)
 *   netProfit         = grossProfit − advisoryShare − opex
 *   parcelFromRts     = parcel × rtsPct
 *   cogOfRtsReturned  = parcelFromRts × cogUnit
 *   revolvingFund     = cogTotal + adspentTotal   (working capital that recycles)
 */

/**
 * Gencys partner workspaces (`workspaces.is_gencys_partner`) give up a 30%
 * advisory share off gross profit, before OPEX.
 */
const ADVISORY_SHARE_RATE = 0.3;

const DEFAULTS = {
    roas: '0',
    pricing: '0',
    cogUnit: '0',
    adSpentPerDay: '0',
    days: '0',
    rtsPct: '0',
    odzPct: '0',
    shippingAmount: '0',
    codPct: '0',
    opex: '0',
};

type Field = keyof typeof DEFAULTS;

const fmt = (v: number) =>
    Number.isFinite(v)
        ? v.toLocaleString('en-PH', {
              minimumFractionDigits: 2,
              maximumFractionDigits: 2,
          })
        : '0.00';

const num = (v: string) => {
    const n = parseFloat(v);
    return Number.isFinite(n) ? n : 0;
};

export default function ProfitabilityFormula({ workspace }: Props) {
    const [inputs, setInputs] = useState<Record<Field, string>>({
        ...DEFAULTS,
    });

    const advisoryShareApplies = workspace.is_gencys_partner;

    const set = (field: Field) => (e: React.ChangeEvent<HTMLInputElement>) =>
        setInputs((prev) => ({ ...prev, [field]: e.target.value }));

    const reset = () => setInputs({ ...DEFAULTS });

    const r = useMemo(() => {
        const roas = num(inputs.roas);
        const pricing = num(inputs.pricing);
        const cogUnit = num(inputs.cogUnit);
        const adSpentPerDay = num(inputs.adSpentPerDay);
        const days = num(inputs.days);
        const rtsPct = num(inputs.rtsPct) / 100;
        const odzPct = num(inputs.odzPct) / 100;
        const shippingAmount = num(inputs.shippingAmount);
        const codPct = num(inputs.codPct) / 100;
        const opex = num(inputs.opex);

        const adspentTotal = adSpentPerDay * days;
        const grossSales = roas * adspentTotal;
        const odzAmount = grossSales * odzPct;
        const lessOdz = grossSales - odzAmount;
        const parcel = pricing > 0 ? lessOdz / pricing : 0;
        const rtsAmount = lessOdz * rtsPct;
        const lessRts = lessOdz - rtsAmount;
        const shipping = parcel * shippingAmount;
        const codFee = lessOdz * codPct;
        const cogTotal = parcel * cogUnit;
        const grossProfit =
            lessRts - shipping - codFee - cogTotal - adspentTotal;
        // Only share the upside — a loss must not credit money back.
        const advisoryShare = advisoryShareApplies
            ? Math.max(grossProfit, 0) * ADVISORY_SHARE_RATE
            : 0;
        const netProfit = grossProfit - advisoryShare - opex;

        const parcelFromRts = parcel * rtsPct;
        const cogOfRtsReturned = parcelFromRts * cogUnit;
        const totalNetPlusCogRts = netProfit + cogOfRtsReturned;
        const revolvingFund = cogTotal + adspentTotal;
        const revolvingFundAndNetProfit = revolvingFund; // COG + Ad Spent

        return {
            adspentTotal,
            grossSales,
            odzAmount,
            lessOdz,
            parcel,
            rtsAmount,
            lessRts,
            shipping,
            codFee,
            cogTotal,
            grossProfit,
            advisoryShare,
            netProfit,
            opex,
            cogOfRtsReturned,
            totalNetPlusCogRts,
            revolvingFundAndNetProfit,
            rtsPctRaw: num(inputs.rtsPct),
            odzPctRaw: num(inputs.odzPct),
            codPctRaw: num(inputs.codPct),
            shippingAmount,
        };
    }, [inputs, advisoryShareApplies]);

    return (
        <AppLayout>
            <Head title={`${workspace.name} - Profitability Formula`} />
            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
                <PageHeader
                    title="Profitability Formula"
                    description="Model net profit and revolving funds from ROAS, ad spend and cost inputs."
                >
                    <Button variant="outline" size="sm" onClick={reset}>
                        <RotateCcw className="mr-1.5 h-3.5 w-3.5" />
                        Reset
                    </Button>
                </PageHeader>

                {/* Inputs */}
                <div className="rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
                    <div className="flex items-center gap-2 border-b border-black/6 px-5 py-3 dark:border-white/6">
                        <Calculator className="h-4 w-4 text-gray-400" />
                        <span className="font-mono text-[11px] font-medium tracking-wider text-gray-400 uppercase dark:text-gray-500">
                            Inputs
                        </span>
                    </div>
                    <div className="divide-y divide-black/6 dark:divide-white/6">
                        <InputRow
                            label="ROAS"
                            value={inputs.roas}
                            onChange={set('roas')}
                        />
                        <InputRow
                            label="Pricing"
                            value={inputs.pricing}
                            onChange={set('pricing')}
                        />
                        <InputRow
                            label="COG (per unit)"
                            value={inputs.cogUnit}
                            onChange={set('cogUnit')}
                        />
                        <InputRow
                            label="Ad Spent (per day)"
                            value={inputs.adSpentPerDay}
                            onChange={set('adSpentPerDay')}
                        />
                        <InputRow
                            label="Days"
                            value={inputs.days}
                            onChange={set('days')}
                        />
                        <InputRow
                            label="RTS Percentage"
                            value={inputs.rtsPct}
                            onChange={set('rtsPct')}
                            suffix="%"
                        />
                        <InputRow
                            label="ODZ/INC Percentage"
                            value={inputs.odzPct}
                            onChange={set('odzPct')}
                            suffix="%"
                        />
                        <InputRow
                            label="Shipping Amount (per parcel)"
                            value={inputs.shippingAmount}
                            onChange={set('shippingAmount')}
                        />
                        <InputRow
                            label="COD Fee Percentage"
                            value={inputs.codPct}
                            onChange={set('codPct')}
                            suffix="%"
                        />
                        <InputRow
                            label="OPEX"
                            value={inputs.opex}
                            onChange={set('opex')}
                        />
                    </div>
                </div>

                {/* Breakdown */}
                <div className="mt-6 overflow-hidden rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
                    <div className="border-b border-black/6 px-5 py-3 dark:border-white/6">
                        <span className="font-mono text-[11px] font-medium tracking-wider text-gray-400 uppercase dark:text-gray-500">
                            Breakdown
                        </span>
                    </div>
                    <div>
                        <Line
                            label="Parcel"
                            value={r.parcel}
                            variant="result"
                        />
                        <Line label="Gross Sales" value={r.grossSales} />
                        <Line
                            label={`ODZ/INC (${fmt(r.odzPctRaw)}%)`}
                            value={r.odzAmount}
                            variant="deduction"
                        />
                        <Line
                            label="Total (Less: ODZ/INC)"
                            value={r.lessOdz}
                            variant="subtotal"
                        />
                        <Line
                            label={`RTS ${fmt(r.rtsPctRaw)}%`}
                            value={r.rtsAmount}
                            variant="deduction"
                        />
                        <Line
                            label="Total (Less: RTS)"
                            value={r.lessRts}
                            variant="subtotal"
                        />
                        <Line
                            label={`Shipping (${fmt(r.shippingAmount)})`}
                            value={r.shipping}
                            variant="deduction"
                        />
                        <Line
                            label={`COD Fee (${fmt(r.codPctRaw)}%)`}
                            value={r.codFee}
                            variant="deduction"
                        />
                        <Line
                            label="COG"
                            value={r.cogTotal}
                            variant="deduction"
                        />
                        <Line
                            label="Ad Spent"
                            value={r.adspentTotal}
                            variant="deduction"
                        />
                        <Line
                            label="Gross Profit"
                            value={r.grossProfit}
                            variant="subtotal"
                        />
                        {advisoryShareApplies && (
                            <Line
                                label={`Advisory Share (${ADVISORY_SHARE_RATE * 100}%)`}
                                value={r.advisoryShare}
                                variant="deduction"
                            />
                        )}
                        <Line label="OPEX" value={r.opex} variant="deduction" />
                        <Line
                            label="NET Profit"
                            value={r.netProfit}
                            variant="result"
                        />
                        <Line
                            label="COG of RTS Returned"
                            value={r.cogOfRtsReturned}
                        />
                        <Line
                            label="Total NET Profit & COG of RTS Ret."
                            value={r.totalNetPlusCogRts}
                            variant="result"
                        />
                        <Line
                            label="Total Revolving Fund & NET Profit"
                            value={r.revolvingFundAndNetProfit}
                            variant="final"
                        />
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}

function InputRow({
    label,
    value,
    onChange,
    suffix,
}: {
    label: string;
    value: string;
    onChange: (e: React.ChangeEvent<HTMLInputElement>) => void;
    suffix?: string;
}) {
    return (
        <div className="flex items-center justify-between gap-4 px-5 py-2.5">
            <label className="text-[13px] text-gray-700 dark:text-gray-200">
                {label}
            </label>
            <div className="relative w-44">
                <Input
                    type="number"
                    inputMode="decimal"
                    value={value}
                    onChange={onChange}
                    className={cn(
                        'text-right font-mono text-[13px]',
                        suffix && 'pr-7',
                    )}
                />
                {suffix && (
                    <span className="pointer-events-none absolute inset-y-0 right-3 flex items-center text-[12px] text-gray-400">
                        {suffix}
                    </span>
                )}
            </div>
        </div>
    );
}

type LineVariant = 'default' | 'deduction' | 'subtotal' | 'result' | 'final';

function Line({
    label,
    value,
    variant = 'default',
}: {
    label: string;
    value: number;
    variant?: LineVariant;
}) {
    const styles: Record<LineVariant, string> = {
        default: 'bg-white text-gray-700 dark:bg-zinc-900 dark:text-gray-200',
        deduction: 'bg-white text-rose-600 dark:bg-zinc-900 dark:text-rose-400',
        subtotal:
            'bg-violet-50 font-semibold text-violet-900 dark:bg-violet-500/10 dark:text-violet-200',
        result: 'bg-amber-50 font-semibold text-amber-900 dark:bg-amber-500/10 dark:text-amber-200',
        final: 'bg-emerald-50 font-bold text-emerald-900 dark:bg-emerald-500/10 dark:text-emerald-200',
    };

    return (
        <div
            className={cn(
                'flex items-center justify-between gap-4 border-b border-black/5 px-5 py-2.5 text-[13px] last:border-b-0 dark:border-white/5',
                styles[variant],
            )}
        >
            <span>{label}</span>
            <span className="font-mono tabular-nums">{fmt(value)}</span>
        </div>
    );
}
