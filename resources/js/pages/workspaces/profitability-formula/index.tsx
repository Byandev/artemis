import PageHeader from '@/components/common/PageHeader';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { Head } from '@inertiajs/react';
import {
    Calculator,
    Columns2,
    Plus,
    RotateCcw,
    Trash2,
    Users,
    X,
} from 'lucide-react';
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
 * Two revenue modes reach gross profit by different routes. Everything from
 * gross profit down is shared:
 *
 *   adspentTotal      = adSpentPerDay × days
 *   advisoryShare     = max(grossProfit, 0) × 30%   (Gencys partner workspaces)
 *   varExpenseUnits   = ceil(adSpentPerDay ÷ per)   (whole units, per expense)
 *   varExpenseTotal   = Σ (units × costPerUnit)
 *   netProfit         = grossProfit − advisoryShare − opex − varExpenseTotal
 *   rtsParcels        = parcel × rtsPct
 *   cogOfRtsReturned  = rtsParcels × cogUnit
 *   revolvingFund     = cogTotal + adspentTotal   (working capital that recycles)
 *
 * ROAS mode — revenue straight off spend, percentages off the money:
 *
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
 *
 * CPP mode — unit economics first, then a parcel funnel off ad spend:
 *
 *   profitPerParcel   = pricing − cpp − shippingAmount − cogUnit
 *                       − (pricing × codPct)
 *   purchases         = adspentTotal ÷ cpp
 *   odzParcels        = purchases × odzPct        (never ship: no shipping/COG)
 *   parcel            = purchases − odzParcels    (shipped)
 *   rtsParcels        = parcel × rtsPct
 *   parcelsDelivered  = parcel − rtsParcels
 *   grossSales        = parcelsDelivered × pricing   (only delivered collect)
 *   codFee            = grossSales × codPct
 *   shipping          = parcel × shippingAmount      (paid on all shipped)
 *   cogTotal          = parcel × cogUnit
 *   grossProfit       = grossSales − codFee − shipping − cogTotal
 *                       − adspentTotal
 *
 * An RTS parcel therefore loses its sale but still burns ad spend and shipping;
 * its COG comes back through the "COG of RTS Returned" line, as in ROAS mode.
 * The identity behind CPP mode:
 *
 *   grossProfit = profitPerParcel × parcelsDelivered
 *                 − rtsParcels × (cpp + shippingAmount + cogUnit)
 *                 − odzParcels × cpp
 */

/**
 * Gencys partner workspaces (`workspaces.is_gencys_partner`) give up a 30%
 * advisory share off gross profit, before OPEX.
 */
const ADVISORY_SHARE_RATE = 0.3;

const DEFAULTS = {
    roas: '0',
    cpp: '0',
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

/** What drives revenue: ROAS directly, or CPP through a purchase count. */
type Mode = 'roas' | 'cpp';

/** `mode` limits a field to one revenue mode; unset means it shows in both. */
const FIELDS: Array<{
    field: Field;
    label: string;
    suffix?: string;
    mode?: Mode;
}> = [
    { field: 'roas', label: 'ROAS', mode: 'roas' },
    { field: 'cpp', label: 'CPP (cost per purchase)', mode: 'cpp' },
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

/**
 * Headcount-style cost that scales with daily ad spend — e.g. 1 CSR for every
 * ₱15,000 of ad spend per day. `per` is that threshold; `costPerUnit` is what
 * one unit costs for the whole period being modelled (same basis as OPEX).
 *
 * Definitions are shared across scenarios: when comparing, both sides carry the
 * same expenses and only the unit count moves, because that is driven by each
 * scenario's own daily ad spend.
 */
interface VariableExpense {
    id: number;
    name: string;
    per: string;
    costPerUnit: string;
}

let nextExpenseId = 1;

const newExpense = (): VariableExpense => ({
    id: nextExpenseId++,
    name: '',
    per: '0',
    costPerUnit: '0',
});

const fmt = (v: number) =>
    Number.isFinite(v)
        ? v.toLocaleString('en-PH', {
              minimumFractionDigits: 2,
              maximumFractionDigits: 2,
          })
        : '0.00';

/** Unit counts are whole — showing "20.00 CSRs" would just be noise. */
const fmtUnits = (v: number) =>
    Number.isFinite(v)
        ? v.toLocaleString('en-PH', { maximumFractionDigits: 0 })
        : '0';

const num = (v: string) => {
    const n = parseFloat(v);
    return Number.isFinite(n) ? n : 0;
};

interface ChainInputs {
    adspentTotal: number;
    roas: number;
    cpp: number;
    pricing: number;
    cogUnit: number;
    shippingAmount: number;
    odzPct: number;
    rtsPct: number;
    codPct: number;
}

/**
 * Both chains return the same shape so the caller and the breakdown can read
 * one type; each fills the other mode's fields with 0.
 */
interface Chain {
    grossSales: number;
    parcel: number;
    shipping: number;
    codFee: number;
    cogTotal: number;
    grossProfit: number;
    // ROAS mode — percentages come off the money.
    odzAmount: number;
    lessOdz: number;
    rtsAmount: number;
    lessRts: number;
    // CPP mode — percentages come off the parcel funnel.
    purchases: number;
    odzParcels: number;
    parcelsDelivered: number;
    profitPerParcel: number;
}

function roasChain(p: ChainInputs): Chain {
    const grossSales = p.roas * p.adspentTotal;
    const odzAmount = grossSales * p.odzPct;
    const lessOdz = grossSales - odzAmount;
    const parcel = p.pricing > 0 ? lessOdz / p.pricing : 0;
    const rtsAmount = lessOdz * p.rtsPct;
    const lessRts = lessOdz - rtsAmount;
    const shipping = parcel * p.shippingAmount;
    const codFee = lessOdz * p.codPct;
    const cogTotal = parcel * p.cogUnit;
    const grossProfit = lessRts - shipping - codFee - cogTotal - p.adspentTotal;

    return {
        grossSales,
        parcel,
        shipping,
        codFee,
        cogTotal,
        grossProfit,
        odzAmount,
        lessOdz,
        rtsAmount,
        lessRts,
        purchases: 0,
        odzParcels: 0,
        parcelsDelivered: 0,
        profitPerParcel: 0,
    };
}

/**
 * Unit economics first, then a parcel funnel driven off ad spend. A delivered
 * parcel earns the full margin; an RTS parcel earns nothing but still burns its
 * ad spend and shipping (COG comes back via "COG of RTS Returned"); an ODZ
 * parcel never ships, so it only costs its ad spend.
 */
function cppChain(p: ChainInputs): Chain {
    const purchases = p.cpp > 0 ? p.adspentTotal / p.cpp : 0;
    const odzParcels = purchases * p.odzPct;
    const parcel = purchases - odzParcels;
    const rtsParcels = parcel * p.rtsPct;
    const parcelsDelivered = parcel - rtsParcels;

    const grossSales = parcelsDelivered * p.pricing;
    const codFee = grossSales * p.codPct;
    const shipping = parcel * p.shippingAmount;
    const cogTotal = parcel * p.cogUnit;
    const grossProfit =
        grossSales - codFee - shipping - cogTotal - p.adspentTotal;

    const profitPerParcel =
        p.pricing - p.cpp - p.shippingAmount - p.cogUnit - p.pricing * p.codPct;

    return {
        grossSales,
        parcel,
        shipping,
        codFee,
        cogTotal,
        grossProfit,
        odzAmount: 0,
        lessOdz: 0,
        rtsAmount: 0,
        lessRts: 0,
        purchases,
        odzParcels,
        parcelsDelivered,
        profitPerParcel,
    };
}

function calculate(
    inputs: Scenario,
    variableExpenses: VariableExpense[],
    advisoryShareApplies: boolean,
    mode: Mode,
) {
    const roas = num(inputs.roas);
    const cpp = num(inputs.cpp);
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

    const chainInputs: ChainInputs = {
        adspentTotal,
        roas,
        cpp,
        pricing,
        cogUnit,
        shippingAmount,
        odzPct,
        rtsPct,
        codPct,
    };
    const chain =
        mode === 'cpp' ? cppChain(chainInputs) : roasChain(chainInputs);
    const { parcel, cogTotal, grossProfit } = chain;

    // Only share the upside — a loss must not credit money back.
    const advisoryShare = advisoryShareApplies
        ? Math.max(grossProfit, 0) * ADVISORY_SHARE_RATE
        : 0;

    // Units scale off this scenario's daily ad spend and round up to whole
    // units — you can't hire 19.91 CSRs, so the cost steps at each threshold.
    const expenses = variableExpenses.map((e) => {
        const per = num(e.per);
        const costPerUnit = num(e.costPerUnit);
        const units = per > 0 ? Math.ceil(adSpentPerDay / per) : 0;

        return {
            id: e.id,
            name: e.name.trim() || 'Variable expense',
            units,
            costPerUnit,
            total: units * costPerUnit,
        };
    });
    const variableExpenseTotal = expenses.reduce((sum, e) => sum + e.total, 0);

    const netProfit = grossProfit - advisoryShare - opex - variableExpenseTotal;

    const rtsParcels = parcel * rtsPct;
    const cogOfRtsReturned = rtsParcels * cogUnit;
    const totalNetPlusCogRts = netProfit + cogOfRtsReturned;
    const revolvingFund = cogTotal + adspentTotal;
    const revolvingFundAndNetProfit = revolvingFund; // COG + Ad Spent

    // What ROAS mode's driver works out to per purchase, for its input hint.
    // There is no reverse hint in CPP mode: the modes no longer share a chain,
    // so an implied ROAS there would suggest an equivalence that doesn't hold.
    const impliedCpp = roas > 0 ? pricing / roas : 0;

    return {
        ...chain,
        adspentTotal,
        impliedCpp,
        rtsParcels,
        pricing,
        cpp,
        cogUnit,
        codFeePerParcel: pricing * codPct,
        advisoryShare,
        expenses,
        variableExpenseTotal,
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
type ComputedExpense = Results['expenses'][number];

type LineVariant = 'default' | 'deduction' | 'subtotal' | 'result' | 'final';

type BreakdownLine = {
    key: string;
    /** Rows are grouped under this heading; consecutive rows share a group. */
    section: string;
    label: string;
    value: number;
    variant?: LineVariant;
};

/** ROAS mode: percentages come off the money on the way down. */
function roasLines(r: Results): BreakdownLine[] {
    return [
        {
            section: 'Sales',
            key: 'grossSales',
            label: 'Gross Sales (ROAS × Ad Spent)',
            value: r.grossSales,
        },
        {
            section: 'Sales',
            key: 'odz',
            label: `Less: ODZ/INC (${fmt(r.odzPctRaw)}%)`,
            value: r.odzAmount,
            variant: 'deduction',
        },
        {
            section: 'Sales',
            key: 'lessOdz',
            label: 'Total (Less: ODZ/INC)',
            value: r.lessOdz,
            variant: 'subtotal',
        },
        {
            section: 'Sales',
            key: 'rts',
            label: `Less: RTS (${fmt(r.rtsPctRaw)}%)`,
            value: r.rtsAmount,
            variant: 'deduction',
        },
        {
            section: 'Sales',
            key: 'lessRts',
            label: 'Total (Less: RTS)',
            value: r.lessRts,
            variant: 'subtotal',
        },

        {
            section: 'Parcels',
            key: 'parcel',
            label: 'Parcels (Total Less ODZ ÷ Pricing)',
            value: r.parcel,
            variant: 'result',
        },
        {
            section: 'Parcels',
            key: 'rtsParcels',
            label: `Of which RTS (${fmt(r.rtsPctRaw)}%)`,
            value: r.rtsParcels,
        },

        {
            section: 'Costs',
            key: 'shipping',
            label: `Shipping (${fmt(r.shippingAmount)} × parcels)`,
            value: r.shipping,
            variant: 'deduction',
        },
        {
            section: 'Costs',
            key: 'codFee',
            label: `COD Fee (${fmt(r.codPctRaw)}% × Total Less ODZ)`,
            value: r.codFee,
            variant: 'deduction',
        },
        {
            section: 'Costs',
            key: 'cog',
            label: `COG (${fmt(r.cogUnit)} × parcels)`,
            value: r.cogTotal,
            variant: 'deduction',
        },
        {
            section: 'Costs',
            key: 'adSpent',
            label: 'Ad Spent',
            value: r.adspentTotal,
            variant: 'deduction',
        },
    ];
}

/**
 * CPP mode: the per-parcel margin, then a parcel funnel, then the money those
 * parcels actually move.
 */
function cppLines(r: Results): BreakdownLine[] {
    return [
        {
            section: 'Per Parcel',
            key: 'pricing',
            label: 'Pricing',
            value: r.pricing,
        },
        {
            section: 'Per Parcel',
            key: 'cppPerParcel',
            label: 'Less: CPP',
            value: r.cpp,
            variant: 'deduction',
        },
        {
            section: 'Per Parcel',
            key: 'shippingPerParcel',
            label: 'Less: Shipping',
            value: r.shippingAmount,
            variant: 'deduction',
        },
        {
            section: 'Per Parcel',
            key: 'cogPerParcel',
            label: 'Less: COG',
            value: r.cogUnit,
            variant: 'deduction',
        },
        {
            section: 'Per Parcel',
            key: 'codFeePerParcel',
            label: `Less: COD Fee (${fmt(r.codPctRaw)}%)`,
            value: r.codFeePerParcel,
            variant: 'deduction',
        },
        {
            section: 'Per Parcel',
            key: 'profitPerParcel',
            label: 'Profit per Parcel',
            value: r.profitPerParcel,
            variant: 'result',
        },

        {
            section: 'Parcels',
            key: 'purchases',
            label: 'Purchases (Ad Spent ÷ CPP)',
            value: r.purchases,
        },
        {
            section: 'Parcels',
            key: 'odzParcels',
            label: `Less: ODZ/INC (${fmt(r.odzPctRaw)}%)`,
            value: r.odzParcels,
            variant: 'deduction',
        },
        {
            section: 'Parcels',
            key: 'parcel',
            label: 'Parcels Shipped',
            value: r.parcel,
            variant: 'subtotal',
        },
        {
            section: 'Parcels',
            key: 'rtsParcels',
            label: `Less: RTS (${fmt(r.rtsPctRaw)}%)`,
            value: r.rtsParcels,
            variant: 'deduction',
        },
        {
            section: 'Parcels',
            key: 'parcelsDelivered',
            label: 'Parcels Delivered',
            value: r.parcelsDelivered,
            variant: 'result',
        },

        {
            section: 'Period Totals',
            key: 'grossSales',
            label: 'Gross Sales (Delivered × Pricing)',
            value: r.grossSales,
        },
        {
            section: 'Period Totals',
            key: 'codFee',
            label: `COD Fee (${fmt(r.codPctRaw)}% × delivered)`,
            value: r.codFee,
            variant: 'deduction',
        },
        {
            section: 'Period Totals',
            key: 'shipping',
            label: `Shipping (${fmt(r.shippingAmount)} × shipped)`,
            value: r.shipping,
            variant: 'deduction',
        },
        {
            section: 'Period Totals',
            key: 'cog',
            label: `COG (${fmt(r.cogUnit)} × shipped)`,
            value: r.cogTotal,
            variant: 'deduction',
        },
        {
            section: 'Period Totals',
            key: 'adSpent',
            label: 'Ad Spent',
            value: r.adspentTotal,
            variant: 'deduction',
        },
    ];
}

/**
 * Built as data rather than JSX so both scenarios render the same rows in the
 * same order — which is what lets the comparison line up row-for-row.
 */
function breakdownLines(
    r: Results,
    advisoryShareApplies: boolean,
    mode: Mode,
): BreakdownLine[] {
    const profit = 'Profit';

    return [
        ...(mode === 'cpp' ? cppLines(r) : roasLines(r)),
        {
            section: profit,
            key: 'grossProfit',
            label: 'Gross Profit',
            value: r.grossProfit,
            variant: 'subtotal',
        },
        ...(advisoryShareApplies
            ? [
                  {
                      section: profit,
                      key: 'advisoryShare',
                      label: `Less: Advisory Share (${ADVISORY_SHARE_RATE * 100}%)`,
                      value: r.advisoryShare,
                      variant: 'deduction' as const,
                  },
              ]
            : []),
        {
            section: profit,
            key: 'opex',
            label: 'Less: OPEX',
            value: r.opex,
            variant: 'deduction',
        },
        ...r.expenses.map((e) => ({
            section: profit,
            key: `expense:${e.id}`,
            label: `Less: ${e.name} (${fmtUnits(e.units)} × ${fmt(e.costPerUnit)})`,
            value: e.total,
            variant: 'deduction' as const,
        })),
        ...(r.expenses.length > 1
            ? [
                  {
                      section: profit,
                      key: 'variableExpenseTotal',
                      label: 'Total Variable Expenses',
                      value: r.variableExpenseTotal,
                      variant: 'subtotal' as const,
                  },
              ]
            : []),
        {
            section: profit,
            key: 'netProfit',
            label: 'NET Profit',
            value: r.netProfit,
            variant: 'result',
        },

        {
            section: 'Returns & Revolving Fund',
            key: 'cogOfRtsReturned',
            label: `COG of RTS Returned (${fmt(r.cogUnit)} × RTS parcels)`,
            value: r.cogOfRtsReturned,
        },
        {
            section: 'Returns & Revolving Fund',
            key: 'totalNetPlusCogRts',
            label: 'Total NET Profit & COG of RTS Ret.',
            value: r.totalNetPlusCogRts,
            variant: 'result',
        },
        {
            section: 'Returns & Revolving Fund',
            key: 'revolvingFund',
            label: 'Total Revolving Fund & NET Profit',
            value: r.revolvingFundAndNetProfit,
            variant: 'final',
        },
    ];
}

/** Collapse the flat line list into consecutive runs sharing a section. */
function groupLines(lines: BreakdownLine[]) {
    const groups: Array<{ section: string; lines: BreakdownLine[] }> = [];

    for (const line of lines) {
        const current = groups.at(-1);

        if (current?.section === line.section) {
            current.lines.push(line);
        } else {
            groups.push({ section: line.section, lines: [line] });
        }
    }

    return groups;
}

export default function ProfitabilityFormula({ workspace }: Props) {
    const advisoryShareApplies = workspace.is_gencys_partner;

    const [mode, setMode] = useState<Mode>('roas');
    const [comparing, setComparing] = useState(false);
    const [scenarioA, setScenarioA] = useState<Scenario>({ ...DEFAULTS });
    const [scenarioB, setScenarioB] = useState<Scenario>({ ...DEFAULTS });
    // Shared across scenarios — defined once, charged to both sides.
    const [expenses, setExpenses] = useState<VariableExpense[]>([]);

    const resultsA = useMemo(
        () => calculate(scenarioA, expenses, advisoryShareApplies, mode),
        [scenarioA, expenses, advisoryShareApplies, mode],
    );
    const resultsB = useMemo(
        () => calculate(scenarioB, expenses, advisoryShareApplies, mode),
        [scenarioB, expenses, advisoryShareApplies, mode],
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
        setExpenses([]);
    };

    const addExpense = () => {
        // Built outside the updater so the updater stays pure.
        const expense = newExpense();
        setExpenses((prev) => [...prev, expense]);
    };

    const updateExpense = (id: number, patch: Partial<VariableExpense>) =>
        setExpenses((prev) =>
            prev.map((e) => (e.id === id ? { ...e, ...patch } : e)),
        );

    const removeExpense = (id: number) =>
        setExpenses((prev) => prev.filter((e) => e.id !== id));

    const columns = cn('grid grid-cols-1 gap-6', comparing && 'lg:grid-cols-2');

    return (
        <AppLayout>
            <Head title={`${workspace.name} - Profitability Formula`} />
            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
                <PageHeader
                    title="Profitability Formula"
                    description="Model net profit and revolving funds from ROAS, ad spend and cost inputs."
                >
                    <ToggleGroup
                        type="single"
                        variant="outline"
                        size="sm"
                        value={mode}
                        // Radix allows deselecting in a single group — ignore
                        // the empty value so a mode is always active.
                        onValueChange={(next) => next && setMode(next as Mode)}
                    >
                        <ToggleGroupItem value="roas" className="px-3 text-xs">
                            ROAS
                        </ToggleGroupItem>
                        <ToggleGroupItem value="cpp" className="px-3 text-xs">
                            CPP
                        </ToggleGroupItem>
                    </ToggleGroup>
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

                <div className="space-y-6">
                    <div className={columns}>
                        <InputsCard
                            scenarioLabel={comparing ? 'Scenario A' : undefined}
                            inputs={scenarioA}
                            onChange={setScenarioA}
                            results={resultsA}
                            mode={mode}
                        />
                        {comparing && (
                            <InputsCard
                                scenarioLabel="Scenario B"
                                inputs={scenarioB}
                                onChange={setScenarioB}
                                results={resultsB}
                                mode={mode}
                            />
                        )}
                    </div>

                    <VariableExpensesCard
                        expenses={expenses}
                        workingsFor={(id) =>
                            comparing
                                ? [
                                      {
                                          label: 'A',
                                          computed: resultsA.expenses.find(
                                              (e) => e.id === id,
                                          ),
                                      },
                                      {
                                          label: 'B',
                                          computed: resultsB.expenses.find(
                                              (e) => e.id === id,
                                          ),
                                      },
                                  ]
                                : [
                                      {
                                          computed: resultsA.expenses.find(
                                              (e) => e.id === id,
                                          ),
                                      },
                                  ]
                        }
                        onAdd={addExpense}
                        onUpdate={updateExpense}
                        onRemove={removeExpense}
                    />

                    <div className={columns}>
                        <BreakdownCard
                            scenarioLabel={comparing ? 'Scenario A' : undefined}
                            results={resultsA}
                            advisoryShareApplies={advisoryShareApplies}
                            mode={mode}
                        />
                        {comparing && (
                            <BreakdownCard
                                scenarioLabel="Scenario B"
                                results={resultsB}
                                advisoryShareApplies={advisoryShareApplies}
                                mode={mode}
                                compareTo={resultsA}
                            />
                        )}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}

function CardHeader({
    label,
    scenarioLabel,
    icon,
    children,
}: {
    label: string;
    scenarioLabel?: string;
    icon?: React.ReactNode;
    children?: React.ReactNode;
}) {
    return (
        <div className="flex items-center justify-between gap-2 border-b border-black/6 px-5 py-2.5 dark:border-white/6">
            <div className="flex items-center gap-2">
                {icon}
                <span className="font-mono text-[11px] font-medium tracking-wider text-gray-400 uppercase dark:text-gray-500">
                    {label}
                </span>
            </div>
            <div className="flex items-center gap-2">
                {scenarioLabel && (
                    <span className="rounded bg-black/4 px-2 py-0.5 font-mono text-[10px] font-medium tracking-wider text-gray-500 uppercase dark:bg-white/6 dark:text-gray-400">
                        {scenarioLabel}
                    </span>
                )}
                {children}
            </div>
        </div>
    );
}

function InputsCard({
    scenarioLabel,
    inputs,
    onChange,
    results,
    mode,
}: {
    scenarioLabel?: string;
    inputs: Scenario;
    onChange: React.Dispatch<React.SetStateAction<Scenario>>;
    results: Results;
    mode: Mode;
}) {
    // ROAS mode only: what its driver works out to per purchase. CPP mode gets
    // no implied ROAS — the chains differ, so it would imply a false equivalence.
    const hintFor = (field: Field) =>
        field === 'roas' && results.impliedCpp > 0
            ? `≈ ${fmt(results.impliedCpp)} CPP at this pricing`
            : undefined;

    return (
        <div className="rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
            <CardHeader
                label="Inputs"
                scenarioLabel={scenarioLabel}
                icon={<Calculator className="h-4 w-4 text-gray-400" />}
            />
            <div className="divide-y divide-black/6 dark:divide-white/6">
                {FIELDS.filter((f) => !f.mode || f.mode === mode).map(
                    ({ field, label, suffix }) => (
                        <InputRow
                            key={field}
                            label={label}
                            hint={hintFor(field)}
                            value={inputs[field]}
                            onChange={(e) =>
                                onChange((prev) => ({
                                    ...prev,
                                    [field]: e.target.value,
                                }))
                            }
                            suffix={suffix}
                        />
                    ),
                )}
            </div>
        </div>
    );
}

function BreakdownCard({
    scenarioLabel,
    results,
    advisoryShareApplies,
    mode,
    compareTo,
}: {
    scenarioLabel?: string;
    results: Results;
    advisoryShareApplies: boolean;
    mode: Mode;
    compareTo?: Results;
}) {
    const lines = breakdownLines(results, advisoryShareApplies, mode);
    const baseline = compareTo
        ? new Map(
              breakdownLines(compareTo, advisoryShareApplies, mode).map((l) => [
                  l.key,
                  l.value,
              ]),
          )
        : null;

    return (
        <div className="overflow-hidden rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
            <CardHeader label="Breakdown" scenarioLabel={scenarioLabel} />
            <div>
                {groupLines(lines).map((group) => (
                    <div key={group.section}>
                        <div className="border-b border-black/5 bg-black/[0.02] px-5 py-1.5 font-mono text-[10px] font-medium tracking-wider text-gray-400 uppercase dark:border-white/5 dark:bg-white/[0.02] dark:text-gray-500">
                            {group.section}
                        </div>
                        {group.lines.map((line) => (
                            <Line
                                key={line.key}
                                label={line.label}
                                value={line.value}
                                variant={line.variant}
                                delta={
                                    baseline
                                        ? line.value -
                                          (baseline.get(line.key) ?? 0)
                                        : undefined
                                }
                            />
                        ))}
                    </div>
                ))}
            </div>
        </div>
    );
}

interface Working {
    label?: string;
    computed?: ComputedExpense;
}

function VariableExpensesCard({
    expenses,
    workingsFor,
    onAdd,
    onUpdate,
    onRemove,
}: {
    expenses: VariableExpense[];
    workingsFor: (id: number) => Working[];
    onAdd: () => void;
    onUpdate: (id: number, patch: Partial<VariableExpense>) => void;
    onRemove: (id: number) => void;
}) {
    return (
        <div className="rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
            <CardHeader
                label="Variable Expenses"
                icon={<Users className="h-4 w-4 text-gray-400" />}
            >
                <Button variant="ghost" size="sm" onClick={onAdd}>
                    <Plus className="mr-1 h-3.5 w-3.5" />
                    Add
                </Button>
            </CardHeader>

            {expenses.length === 0 ? (
                <p className="px-5 py-4 text-[12px] text-gray-400 dark:text-gray-500">
                    Costs that scale with daily ad spend — e.g. 1 CSR for every
                    15,000 of Ad Spent (per day). Applied to every scenario.
                </p>
            ) : (
                <div className="grid grid-cols-1 divide-y divide-black/6 md:grid-cols-2 md:divide-y-0 dark:divide-white/6">
                    {expenses.map((expense) => (
                        <VariableExpenseRow
                            key={expense.id}
                            expense={expense}
                            workings={workingsFor(expense.id)}
                            onChange={(patch) => onUpdate(expense.id, patch)}
                            onRemove={() => onRemove(expense.id)}
                        />
                    ))}
                </div>
            )}
        </div>
    );
}

function VariableExpenseRow({
    expense,
    workings,
    onChange,
    onRemove,
}: {
    expense: VariableExpense;
    workings: Working[];
    onChange: (patch: Partial<VariableExpense>) => void;
    onRemove: () => void;
}) {
    return (
        <div className="space-y-2 px-5 py-3">
            <div className="flex items-center gap-2">
                <Input
                    value={expense.name}
                    onChange={(e) => onChange({ name: e.target.value })}
                    placeholder="e.g. CSR"
                    className="h-8 flex-1 text-[13px]"
                />
                <Button
                    variant="ghost"
                    size="icon"
                    className="h-8 w-8 shrink-0 text-gray-400 hover:text-rose-600"
                    onClick={onRemove}
                    aria-label={`Remove ${expense.name || 'expense'}`}
                >
                    <Trash2 className="h-3.5 w-3.5" />
                </Button>
            </div>

            <div className="grid grid-cols-2 gap-2">
                <div>
                    <label className="text-[11px] text-gray-400 dark:text-gray-500">
                        Every (ad spent/day)
                    </label>
                    <Input
                        type="number"
                        inputMode="decimal"
                        value={expense.per}
                        onChange={(e) => onChange({ per: e.target.value })}
                        className="h-8 text-right font-mono text-[13px]"
                    />
                </div>
                <div>
                    <label className="text-[11px] text-gray-400 dark:text-gray-500">
                        Cost per unit
                    </label>
                    <Input
                        type="number"
                        inputMode="decimal"
                        value={expense.costPerUnit}
                        onChange={(e) =>
                            onChange({ costPerUnit: e.target.value })
                        }
                        className="h-8 text-right font-mono text-[13px]"
                    />
                </div>
            </div>

            <div className="space-y-0.5">
                {workings.map(({ label, computed }, i) =>
                    computed ? (
                        <p
                            key={label ?? i}
                            className="font-mono text-[11px] text-gray-400 tabular-nums dark:text-gray-500"
                        >
                            {label && (
                                <span className="mr-1.5 font-medium">
                                    {label}
                                </span>
                            )}
                            {fmtUnits(computed.units)} ×{' '}
                            {fmt(computed.costPerUnit)} ={' '}
                            <span className="text-gray-600 dark:text-gray-300">
                                {fmt(computed.total)}
                            </span>
                        </p>
                    ) : null,
                )}
            </div>
        </div>
    );
}

function InputRow({
    label,
    hint,
    value,
    onChange,
    suffix,
}: {
    label: string;
    hint?: string;
    value: string;
    onChange: (e: React.ChangeEvent<HTMLInputElement>) => void;
    suffix?: string;
}) {
    return (
        <div className="flex items-center justify-between gap-4 px-5 py-2.5">
            <div className="min-w-0">
                <label className="text-[13px] text-gray-700 dark:text-gray-200">
                    {label}
                </label>
                {hint && (
                    <p className="font-mono text-[11px] text-gray-400 tabular-nums dark:text-gray-500">
                        {hint}
                    </p>
                )}
            </div>
            <div className="relative w-44 shrink-0">
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
