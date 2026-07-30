import PageHeader from '@/components/common/PageHeader';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { Head } from '@inertiajs/react';
import { Calculator, Columns2, RotateCcw, X } from 'lucide-react';
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
type Scenario = Record<Field, string>;

const FIELDS: Array<{ field: Field; label: string; suffix?: string }> = [
    { field: 'roas', label: 'ROAS' },
    { field: 'pricing', label: 'Pricing' },
    { field: 'cogUnit', label: 'COG (per unit)' },
    { field: 'adSpentPerDay', label: 'Ad Spent (per day)' },
    { field: 'days', label: 'Days' },
    { field: 'rtsPct', label: 'RTS Percentage', suffix: '%' },
    { field: 'odzPct', label: 'ODZ/INC Percentage', suffix: '%' },
    { field: 'shippingAmount', label: 'Shipping Amount (per parcel)' },
    { field: 'codPct', label: 'COD Fee Percentage', suffix: '%' },
    { field: 'opex', label: 'OPEX' },
];

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

function calculate(inputs: Scenario, advisoryShareApplies: boolean) {
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
    const grossProfit = lessRts - shipping - codFee - cogTotal - adspentTotal;
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
}

type Results = ReturnType<typeof calculate>;

type LineVariant = 'default' | 'deduction' | 'subtotal' | 'result' | 'final';

/**
 * Built as data rather than JSX so both scenarios render the same rows in the
 * same order — which is what lets the comparison line up row-for-row.
 */
function breakdownLines(
    r: Results,
    advisoryShareApplies: boolean,
): Array<{ key: string; label: string; value: number; variant?: LineVariant }> {
    return [
        { key: 'parcel', label: 'Parcel', value: r.parcel, variant: 'result' },
        { key: 'grossSales', label: 'Gross Sales', value: r.grossSales },
        {
            key: 'odz',
            label: `ODZ/INC (${fmt(r.odzPctRaw)}%)`,
            value: r.odzAmount,
            variant: 'deduction',
        },
        {
            key: 'lessOdz',
            label: 'Total (Less: ODZ/INC)',
            value: r.lessOdz,
            variant: 'subtotal',
        },
        {
            key: 'rts',
            label: `RTS ${fmt(r.rtsPctRaw)}%`,
            value: r.rtsAmount,
            variant: 'deduction',
        },
        {
            key: 'lessRts',
            label: 'Total (Less: RTS)',
            value: r.lessRts,
            variant: 'subtotal',
        },
        {
            key: 'shipping',
            label: `Shipping (${fmt(r.shippingAmount)})`,
            value: r.shipping,
            variant: 'deduction',
        },
        {
            key: 'codFee',
            label: `COD Fee (${fmt(r.codPctRaw)}%)`,
            value: r.codFee,
            variant: 'deduction',
        },
        { key: 'cog', label: 'COG', value: r.cogTotal, variant: 'deduction' },
        {
            key: 'adSpent',
            label: 'Ad Spent',
            value: r.adspentTotal,
            variant: 'deduction',
        },
        {
            key: 'grossProfit',
            label: 'Gross Profit',
            value: r.grossProfit,
            variant: 'subtotal',
        },
        ...(advisoryShareApplies
            ? [
                  {
                      key: 'advisoryShare',
                      label: `Advisory Share (${ADVISORY_SHARE_RATE * 100}%)`,
                      value: r.advisoryShare,
                      variant: 'deduction' as const,
                  },
              ]
            : []),
        { key: 'opex', label: 'OPEX', value: r.opex, variant: 'deduction' },
        {
            key: 'netProfit',
            label: 'NET Profit',
            value: r.netProfit,
            variant: 'result',
        },
        {
            key: 'cogOfRtsReturned',
            label: 'COG of RTS Returned',
            value: r.cogOfRtsReturned,
        },
        {
            key: 'totalNetPlusCogRts',
            label: 'Total NET Profit & COG of RTS Ret.',
            value: r.totalNetPlusCogRts,
            variant: 'result',
        },
        {
            key: 'revolvingFund',
            label: 'Total Revolving Fund & NET Profit',
            value: r.revolvingFundAndNetProfit,
            variant: 'final',
        },
    ];
}

export default function ProfitabilityFormula({ workspace }: Props) {
    const advisoryShareApplies = workspace.is_gencys_partner;

    const [comparing, setComparing] = useState(false);
    const [scenarioA, setScenarioA] = useState<Scenario>({ ...DEFAULTS });
    const [scenarioB, setScenarioB] = useState<Scenario>({ ...DEFAULTS });

    const resultsA = useMemo(
        () => calculate(scenarioA, advisoryShareApplies),
        [scenarioA, advisoryShareApplies],
    );
    const resultsB = useMemo(
        () => calculate(scenarioB, advisoryShareApplies),
        [scenarioB, advisoryShareApplies],
    );

    const toggleCompare = () => {
        // Seed B from A on open, so the only difference is what you then change.
        if (!comparing) {
            setScenarioB({ ...scenarioA });
        }
        setComparing(!comparing);
    };

    const reset = () => {
        setScenarioA({ ...DEFAULTS });
        setScenarioB({ ...DEFAULTS });
    };

    return (
        <AppLayout>
            <Head title={`${workspace.name} - Profitability Formula`} />
            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
                <PageHeader
                    title="Profitability Formula"
                    description="Model net profit and revolving funds from ROAS, ad spend and cost inputs."
                >
                    <Button
                        variant={comparing ? 'default' : 'outline'}
                        size="sm"
                        onClick={toggleCompare}
                    >
                        {comparing ? (
                            <X className="mr-1.5 h-3.5 w-3.5" />
                        ) : (
                            <Columns2 className="mr-1.5 h-3.5 w-3.5" />
                        )}
                        {comparing ? 'Close comparison' : 'Compare'}
                    </Button>
                    <Button variant="outline" size="sm" onClick={reset}>
                        <RotateCcw className="mr-1.5 h-3.5 w-3.5" />
                        Reset
                    </Button>
                </PageHeader>

                <div
                    className={cn(
                        'grid grid-cols-1 gap-6',
                        comparing && 'lg:grid-cols-2',
                    )}
                >
                    <ScenarioColumn
                        title={comparing ? 'Scenario A' : undefined}
                        inputs={scenarioA}
                        onFieldChange={(field, value) =>
                            setScenarioA((prev) => ({
                                ...prev,
                                [field]: value,
                            }))
                        }
                        results={resultsA}
                        advisoryShareApplies={advisoryShareApplies}
                    />

                    {comparing && (
                        <ScenarioColumn
                            title="Scenario B"
                            inputs={scenarioB}
                            onFieldChange={(field, value) =>
                                setScenarioB((prev) => ({
                                    ...prev,
                                    [field]: value,
                                }))
                            }
                            results={resultsB}
                            advisoryShareApplies={advisoryShareApplies}
                            compareTo={resultsA}
                        />
                    )}
                </div>
            </div>
        </AppLayout>
    );
}

/**
 * One full scenario: its inputs and its breakdown. When `compareTo` is given
 * every breakdown row also shows its delta against that baseline.
 */
function ScenarioColumn({
    title,
    inputs,
    onFieldChange,
    results,
    advisoryShareApplies,
    compareTo,
}: {
    title?: string;
    inputs: Scenario;
    onFieldChange: (field: Field, value: string) => void;
    results: Results;
    advisoryShareApplies: boolean;
    compareTo?: Results;
}) {
    const lines = breakdownLines(results, advisoryShareApplies);
    const baseline = compareTo
        ? new Map(
              breakdownLines(compareTo, advisoryShareApplies).map((l) => [
                  l.key,
                  l.value,
              ]),
          )
        : null;

    return (
        <div className="space-y-6">
            {title && (
                <div className="font-mono text-[11px] font-medium tracking-wider text-gray-500 uppercase dark:text-gray-400">
                    {title}
                </div>
            )}

            {/* Inputs */}
            <div className="rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
                <div className="flex items-center gap-2 border-b border-black/6 px-5 py-3 dark:border-white/6">
                    <Calculator className="h-4 w-4 text-gray-400" />
                    <span className="font-mono text-[11px] font-medium tracking-wider text-gray-400 uppercase dark:text-gray-500">
                        Inputs
                    </span>
                </div>
                <div className="divide-y divide-black/6 dark:divide-white/6">
                    {FIELDS.map(({ field, label, suffix }) => (
                        <InputRow
                            key={field}
                            label={label}
                            value={inputs[field]}
                            onChange={(e) =>
                                onFieldChange(field, e.target.value)
                            }
                            suffix={suffix}
                        />
                    ))}
                </div>
            </div>

            {/* Breakdown */}
            <div className="overflow-hidden rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
                <div className="border-b border-black/6 px-5 py-3 dark:border-white/6">
                    <span className="font-mono text-[11px] font-medium tracking-wider text-gray-400 uppercase dark:text-gray-500">
                        Breakdown
                    </span>
                </div>
                <div>
                    {lines.map((line) => (
                        <Line
                            key={line.key}
                            label={line.label}
                            value={line.value}
                            variant={line.variant}
                            delta={
                                baseline
                                    ? line.value - (baseline.get(line.key) ?? 0)
                                    : undefined
                            }
                        />
                    ))}
                </div>
            </div>
        </div>
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

function Line({
    label,
    value,
    variant = 'default',
    delta,
}: {
    label: string;
    value: number;
    variant?: LineVariant;
    delta?: number;
}) {
    const styles: Record<LineVariant, string> = {
        default: 'bg-white text-gray-700 dark:bg-zinc-900 dark:text-gray-200',
        deduction: 'bg-white text-rose-600 dark:bg-zinc-900 dark:text-rose-400',
        subtotal:
            'bg-violet-50 font-semibold text-violet-900 dark:bg-violet-500/10 dark:text-violet-200',
        result: 'bg-amber-50 font-semibold text-amber-900 dark:bg-amber-500/10 dark:text-amber-200',
        final: 'bg-emerald-50 font-bold text-emerald-900 dark:bg-emerald-500/10 dark:text-emerald-200',
    };

    // Below half a centavo the formatted delta would read "0.00" — hide it.
    const showDelta = delta !== undefined && Math.abs(delta) >= 0.005;

    return (
        <div
            className={cn(
                'flex items-center justify-between gap-4 border-b border-black/5 px-5 py-2.5 text-[13px] last:border-b-0 dark:border-white/5',
                styles[variant],
            )}
        >
            <span>{label}</span>
            <span className="flex items-baseline gap-2.5">
                {showDelta && (
                    <span className="font-mono text-[11px] font-normal text-gray-400 tabular-nums dark:text-gray-500">
                        {delta > 0 ? '+' : '−'}
                        {fmt(Math.abs(delta))}
                    </span>
                )}
                <span className="font-mono tabular-nums">{fmt(value)}</span>
            </span>
        </div>
    );
}
