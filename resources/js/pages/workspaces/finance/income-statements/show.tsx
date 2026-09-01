import PageHeader from '@/components/common/PageHeader';
import StatementFigures, {
    OpexBreakdownRow,
    StatementFigureSet,
} from '@/components/finance/statement-figures';
import AppLayout from '@/layouts/app-layout';
import { Workspace } from '@/types/models/Workspace';
import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    Download,
    Grid2X2,
    RefreshCw,
    Save,
    ShoppingBag,
    Users,
} from 'lucide-react';
import moment from 'moment';
import { useState } from 'react';

interface Statement {
    id: number | null;
    period_month: string; // YYYY-MM-DD
    cod_fee_rate: number; // fraction (0.02)
    vat_rate: number; // fraction (0.12)
    advisory_rate: number; // fraction (0.30)
    advisory_delivered_rate: number; // fraction (0.09)
    gencys_partner: boolean;
    generated_at?: string | null;
    /**
     * A deficit typed in rather than read off last month's statement, for the
     * months that were closed somewhere else. Null = use the month before.
     */
    manual_loss_brought_forward?: number | null;
}

interface Props {
    workspace: Workspace;
    /** Preview is computed live for the month; saved reads the stored header. */
    mode: 'preview' | 'saved';
    statement: Statement;
    figures: StatementFigureSet;
    /** The OPEX split by transaction type; saved statements only. */
    opexBreakdown?: OpexBreakdownRow[];
}

const BTN =
    'flex h-8 items-center gap-1.5 rounded-lg border border-black/6 bg-stone-50 px-3 font-mono text-[12px] text-gray-600 transition-all hover:bg-stone-100 dark:border-white/6 dark:bg-zinc-800 dark:text-gray-300 dark:hover:bg-zinc-700';

export default function IncomeStatementShow({
    workspace,
    mode,
    statement,
    figures,
    opexBreakdown,
}: Props) {
    const base = `/workspaces/${workspace.slug}/finance/income-statements`;
    const isPreview = mode === 'preview';
    const month = moment(statement.period_month).format('YYYY-MM');
    const monthLabel = moment(statement.period_month).format('MMMM YYYY');

    const [saving, setSaving] = useState(false);

    // What last month left owing, when it was never closed here — the months
    // before this system was in use were worked out on a spreadsheet, so the
    // figure has to be given rather than found. Blank means "read it off the
    // month before"; a typed 0 means "that month broke even", which is a
    // different statement and is kept.
    const [lossBroughtForward, setLossBroughtForward] = useState(
        statement.manual_loss_brought_forward == null
            ? ''
            : String(statement.manual_loss_brought_forward),
    );

    // The rates come along so the saved statement keeps the ones it was struck
    // at, rather than picking up a later change to the workspace defaults.
    const save = () => {
        setSaving(true);
        router.post(
            base,
            {
                month,
                cod_rate: statement.cod_fee_rate,
                vat_rate: statement.vat_rate,
                ...(lossBroughtForward.trim() === ''
                    ? {}
                    : { loss_brought_forward: lossBroughtForward }),
            },
            { onFinish: () => setSaving(false) },
        );
    };

    const regenerate = () => {
        if (!statement.id) return;
        router.post(
            `${base}/${statement.id}/regenerate`,
            {},
            { preserveScroll: true },
        );
    };

    return (
        <AppLayout>
            <Head
                title={`${workspace.name} - Income Statement ${monthLabel}`}
            />
            <div className="w-full p-4 font-mono md:p-6">
                <PageHeader
                    title="Income Statement"
                    description={
                        isPreview
                            ? `${monthLabel} · preview — nothing saved yet`
                            : statement.generated_at
                              ? `${monthLabel} · saved ${moment(statement.generated_at).format('MMM D, YYYY h:mm A')}`
                              : `${monthLabel} · saved snapshot`
                    }
                >
                    <div className="flex items-center gap-2">
                        <Link href={base} className={BTN}>
                            <ArrowLeft className="h-3.5 w-3.5" />
                            Back
                        </Link>

                        {statement.id && (
                            <>
                                <Link
                                    href={`${base}/${statement.id}/users`}
                                    className={BTN}
                                >
                                    <Users className="h-3.5 w-3.5" />
                                    Per-user
                                </Link>
                                <Link
                                    href={`${base}/${statement.id}/products`}
                                    className={BTN}
                                >
                                    <ShoppingBag className="h-3.5 w-3.5" />
                                    Per-product
                                </Link>
                                <Link
                                    href={`${base}/${statement.id}/user-products`}
                                    className={BTN}
                                >
                                    <Grid2X2 className="h-3.5 w-3.5" />
                                    Seller × Product
                                </Link>
                            </>
                        )}

                        {isPreview && (
                            <label className="flex h-8 items-center gap-2 rounded-lg border border-black/6 bg-stone-50 px-3 dark:border-white/6 dark:bg-zinc-800">
                                <span
                                    className="font-mono text-[11px] whitespace-nowrap text-gray-500 dark:text-gray-400"
                                    title="What last month ended owing, if it was never closed here. Leave blank to read it off the previous statement."
                                >
                                    Loss b/f
                                </span>
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    value={lossBroughtForward}
                                    onChange={(e) =>
                                        setLossBroughtForward(e.target.value)
                                    }
                                    placeholder="auto"
                                    className="h-6 w-28 rounded-md border border-black/8 bg-white px-2 text-right font-mono! text-[12px]! text-gray-800 outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/8 dark:bg-zinc-900 dark:text-gray-100"
                                />
                            </label>
                        )}

                        {isPreview ? (
                            <button
                                onClick={save}
                                disabled={saving}
                                className="flex h-8 items-center gap-1.5 rounded-lg bg-emerald-600 px-3.5 text-[12px] font-medium text-white transition-all hover:bg-emerald-700 disabled:opacity-60"
                            >
                                <Save className="h-3.5 w-3.5" />
                                {statement.id ? 'Save (overwrite)' : 'Save'}
                            </button>
                        ) : (
                            <>
                                <button onClick={regenerate} className={BTN}>
                                    <RefreshCw className="h-3.5 w-3.5" />
                                    Regenerate
                                </button>
                                <a
                                    href={`${base}/${statement.id}/export`}
                                    className={BTN}
                                >
                                    <Download className="h-3.5 w-3.5" />
                                    Export
                                </a>
                            </>
                        )}
                    </div>
                </PageHeader>

                {isPreview && statement.id && (
                    <div className="mb-6 rounded-lg border border-amber-300/60 bg-amber-50 px-4 py-3 text-[12px] text-amber-800 dark:border-amber-800/60 dark:bg-amber-950/40 dark:text-amber-200">
                        A statement already exists for {monthLabel}. Saving will
                        overwrite it.
                    </div>
                )}

                <StatementFigures
                    figures={figures}
                    rates={{
                        cod: statement.cod_fee_rate,
                        vat: statement.vat_rate,
                        advisory: statement.advisory_rate,
                        advisoryDelivered: statement.advisory_delivered_rate,
                    }}
                    monthLabel={monthLabel}
                    gencysPartner={statement.gencys_partner}
                    opexBreakdown={opexBreakdown}
                />

                <p className="mt-3 text-[11px] text-gray-400">
                    The same figures cut by who sold and by what was sold are on
                    the per-user and per-product breakdowns.
                </p>
            </div>
        </AppLayout>
    );
}
