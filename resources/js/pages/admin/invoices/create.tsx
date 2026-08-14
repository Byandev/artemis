import PageHeader from '@/components/common/PageHeader';
import AdminSidebarLayout from '@/layouts/admin/admin-sidebar-layout';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Plus, Trash2 } from 'lucide-react';
import { useMemo } from 'react';

interface WorkspaceOption {
    id: number;
    name: string;
    owner_name: string | null;
    owner_email: string | null;
    /** Saved billing details from Workspace Settings → Billing, if any. */
    billing_name: string | null;
    billing_email: string | null;
    billing_address: string | null;
    plan: { id: number; name: string; price_php: string } | null;
}

interface Props {
    workspaces: WorkspaceOption[];
    defaults: {
        due_days: number;
        tax_rate: number;
        currency: string;
        today: string;
    };
}

interface LineItem {
    description: string;
    quantity: number | string;
    unit_price: number | string;
}

function addDays(iso: string, days: number): string {
    const d = new Date(iso + 'T00:00:00');
    d.setDate(d.getDate() + days);
    return d.toISOString().slice(0, 10);
}

export default function Create({ workspaces, defaults }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        workspace_id: '' as number | '',
        subscription_plan_id: null as number | null,
        bill_to_name: '',
        bill_to_email: '',
        bill_to_address: '',
        issue_date: defaults.today,
        due_date: addDays(defaults.today, defaults.due_days),
        currency: defaults.currency,
        tax_rate: defaults.tax_rate,
        notes: '',
        status: 'sent',
        line_items: [
            { description: '', quantity: 1, unit_price: '' },
        ] as LineItem[],
    });

    const symbol = data.currency === 'PHP' ? '₱' : '';

    function selectWorkspace(id: number | '') {
        if (id === '') {
            setData((prev) => ({ ...prev, workspace_id: '' }));
            return;
        }
        const ws = workspaces.find((w) => w.id === id);
        if (!ws) return;

        setData((prev) => ({
            ...prev,
            workspace_id: id,
            subscription_plan_id: ws.plan?.id ?? null,
            // Prefer the workspace's saved billing details, falling back to the
            // owner's account details when they haven't been filled in.
            bill_to_name:
                prev.bill_to_name ||
                ws.billing_name ||
                ws.owner_name ||
                ws.name,
            bill_to_email:
                prev.bill_to_email || ws.billing_email || ws.owner_email || '',
            bill_to_address: prev.bill_to_address || ws.billing_address || '',
            line_items: ws.plan
                ? [
                      {
                          description: `${ws.plan.name} plan — subscription`,
                          quantity: 1,
                          unit_price: ws.plan.price_php,
                      },
                  ]
                : prev.line_items,
        }));
    }

    function updateItem(index: number, patch: Partial<LineItem>) {
        setData(
            'line_items',
            data.line_items.map((it, i) =>
                i === index ? { ...it, ...patch } : it,
            ),
        );
    }

    function addItem() {
        setData('line_items', [
            ...data.line_items,
            { description: '', quantity: 1, unit_price: '' },
        ]);
    }

    function removeItem(index: number) {
        if (data.line_items.length === 1) return;
        setData(
            'line_items',
            data.line_items.filter((_, i) => i !== index),
        );
    }

    const { subtotal, taxAmount, total } = useMemo(() => {
        const sub = data.line_items.reduce(
            (acc, it) =>
                acc + (Number(it.quantity) || 0) * (Number(it.unit_price) || 0),
            0,
        );
        const tax = (sub * (Number(data.tax_rate) || 0)) / 100;
        return { subtotal: sub, taxAmount: tax, total: sub + tax };
    }, [data.line_items, data.tax_rate]);

    const money = (n: number) =>
        `${symbol}${n.toLocaleString(undefined, {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        })}`;

    function submit(e: React.FormEvent) {
        e.preventDefault();
        post('/admin/invoices');
    }

    const inputClass =
        'w-full rounded-md border border-zinc-200 bg-white px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-brand-500/20 dark:border-zinc-800 dark:bg-zinc-950';
    const labelClass =
        'mb-1.5 block text-xs font-semibold text-zinc-600 dark:text-zinc-400';

    return (
        <AdminSidebarLayout>
            <Head title="Admin | New Invoice" />

            <div className="p-4 md:p-6">
                <Link
                    href="/admin/invoices"
                    className="mb-4 inline-flex items-center gap-1.5 text-sm text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200"
                >
                    <ArrowLeft className="h-4 w-4" />
                    Back to invoices
                </Link>

                <PageHeader
                    title="New Invoice"
                    description="Pick a workspace to prefill from its subscription, then adjust as needed."
                />

                <form
                    onSubmit={submit}
                    className="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3"
                >
                    {/* Left: main form */}
                    <div className="space-y-6 lg:col-span-2">
                        {/* Workspace + bill-to */}
                        <div className="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
                            <h3 className="mb-4 text-sm font-semibold text-zinc-900 dark:text-zinc-100">
                                Client
                            </h3>
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div className="sm:col-span-2">
                                    <label className={labelClass}>
                                        Workspace
                                    </label>
                                    <select
                                        value={data.workspace_id}
                                        onChange={(e) =>
                                            selectWorkspace(
                                                e.target.value
                                                    ? Number(e.target.value)
                                                    : '',
                                            )
                                        }
                                        className={inputClass}
                                    >
                                        <option value="">
                                            Select a workspace…
                                        </option>
                                        {workspaces.map((w) => (
                                            <option key={w.id} value={w.id}>
                                                {w.name}
                                                {w.plan
                                                    ? ` — ${w.plan.name}`
                                                    : ''}
                                            </option>
                                        ))}
                                    </select>
                                    {errors.workspace_id && (
                                        <p className="mt-1 text-xs text-red-600">
                                            {errors.workspace_id}
                                        </p>
                                    )}
                                </div>
                                <div>
                                    <label className={labelClass}>
                                        Bill to (name)
                                    </label>
                                    <input
                                        type="text"
                                        value={data.bill_to_name}
                                        onChange={(e) =>
                                            setData(
                                                'bill_to_name',
                                                e.target.value,
                                            )
                                        }
                                        className={inputClass}
                                    />
                                    {errors.bill_to_name && (
                                        <p className="mt-1 text-xs text-red-600">
                                            {errors.bill_to_name}
                                        </p>
                                    )}
                                </div>
                                <div>
                                    <label className={labelClass}>Email</label>
                                    <input
                                        type="email"
                                        value={data.bill_to_email}
                                        onChange={(e) =>
                                            setData(
                                                'bill_to_email',
                                                e.target.value,
                                            )
                                        }
                                        className={inputClass}
                                    />
                                </div>
                                <div className="sm:col-span-2">
                                    <label className={labelClass}>
                                        Billing address
                                    </label>
                                    <textarea
                                        value={data.bill_to_address}
                                        onChange={(e) =>
                                            setData(
                                                'bill_to_address',
                                                e.target.value,
                                            )
                                        }
                                        rows={2}
                                        className={inputClass}
                                    />
                                </div>
                            </div>
                        </div>

                        {/* Line items */}
                        <div className="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
                            <h3 className="mb-4 text-sm font-semibold text-zinc-900 dark:text-zinc-100">
                                Line items
                            </h3>
                            <div className="space-y-3">
                                {data.line_items.map((item, i) => (
                                    <div
                                        key={i}
                                        className="grid grid-cols-12 items-start gap-2"
                                    >
                                        <div className="col-span-6">
                                            <input
                                                type="text"
                                                placeholder="Description"
                                                value={item.description}
                                                onChange={(e) =>
                                                    updateItem(i, {
                                                        description:
                                                            e.target.value,
                                                    })
                                                }
                                                className={inputClass}
                                            />
                                        </div>
                                        <div className="col-span-2">
                                            <input
                                                type="number"
                                                min="0"
                                                step="any"
                                                placeholder="Qty"
                                                value={item.quantity}
                                                onChange={(e) =>
                                                    updateItem(i, {
                                                        quantity:
                                                            e.target.value,
                                                    })
                                                }
                                                className={inputClass}
                                            />
                                        </div>
                                        <div className="col-span-3">
                                            <input
                                                type="number"
                                                min="0"
                                                step="any"
                                                placeholder="Unit price"
                                                value={item.unit_price}
                                                onChange={(e) =>
                                                    updateItem(i, {
                                                        unit_price:
                                                            e.target.value,
                                                    })
                                                }
                                                className={inputClass}
                                            />
                                        </div>
                                        <div className="col-span-1 flex justify-center pt-2">
                                            <button
                                                type="button"
                                                onClick={() => removeItem(i)}
                                                disabled={
                                                    data.line_items.length === 1
                                                }
                                                className="text-zinc-400 transition-colors hover:text-red-600 disabled:opacity-30"
                                                aria-label="Remove line item"
                                            >
                                                <Trash2 className="h-4 w-4" />
                                            </button>
                                        </div>
                                    </div>
                                ))}
                            </div>
                            {typeof errors.line_items === 'string' && (
                                <p className="mt-2 text-xs text-red-600">
                                    {errors.line_items}
                                </p>
                            )}
                            <button
                                type="button"
                                onClick={addItem}
                                className="mt-3 inline-flex items-center gap-1.5 text-sm font-medium text-brand-600 hover:text-brand-700"
                            >
                                <Plus className="h-4 w-4" />
                                Add line item
                            </button>
                        </div>

                        {/* Notes */}
                        <div className="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
                            <label className={labelClass}>
                                Notes (optional)
                            </label>
                            <textarea
                                value={data.notes}
                                onChange={(e) =>
                                    setData('notes', e.target.value)
                                }
                                rows={3}
                                placeholder="Payment terms, PO number, thank-you note…"
                                className={inputClass}
                            />
                        </div>
                    </div>

                    {/* Right: meta + totals */}
                    <div className="space-y-6">
                        <div className="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
                            <h3 className="mb-4 text-sm font-semibold text-zinc-900 dark:text-zinc-100">
                                Details
                            </h3>
                            <div className="space-y-4">
                                <div>
                                    <label className={labelClass}>
                                        Issue date
                                    </label>
                                    <input
                                        type="date"
                                        value={data.issue_date}
                                        onChange={(e) =>
                                            setData(
                                                'issue_date',
                                                e.target.value,
                                            )
                                        }
                                        className={inputClass}
                                    />
                                </div>
                                <div>
                                    <label className={labelClass}>
                                        Due date
                                    </label>
                                    <input
                                        type="date"
                                        value={data.due_date}
                                        onChange={(e) =>
                                            setData('due_date', e.target.value)
                                        }
                                        className={inputClass}
                                    />
                                    {errors.due_date && (
                                        <p className="mt-1 text-xs text-red-600">
                                            {errors.due_date}
                                        </p>
                                    )}
                                </div>
                                <div className="grid grid-cols-2 gap-3">
                                    <div>
                                        <label className={labelClass}>
                                            Currency
                                        </label>
                                        <input
                                            type="text"
                                            maxLength={3}
                                            value={data.currency}
                                            onChange={(e) =>
                                                setData(
                                                    'currency',
                                                    e.target.value.toUpperCase(),
                                                )
                                            }
                                            className={inputClass}
                                        />
                                    </div>
                                    <div>
                                        <label className={labelClass}>
                                            Tax %
                                        </label>
                                        <input
                                            type="number"
                                            min="0"
                                            max="100"
                                            step="any"
                                            value={data.tax_rate}
                                            onChange={(e) =>
                                                setData(
                                                    'tax_rate',
                                                    Number(e.target.value),
                                                )
                                            }
                                            className={inputClass}
                                        />
                                    </div>
                                </div>
                                <div>
                                    <label className={labelClass}>Status</label>
                                    <select
                                        value={data.status}
                                        onChange={(e) =>
                                            setData('status', e.target.value)
                                        }
                                        className={inputClass}
                                    >
                                        <option value="draft">Draft</option>
                                        <option value="sent">Sent</option>
                                        <option value="paid">Paid</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div className="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
                            <div className="space-y-2 text-sm">
                                <div className="flex justify-between text-zinc-600 dark:text-zinc-400">
                                    <span>Subtotal</span>
                                    <span>{money(subtotal)}</span>
                                </div>
                                {Number(data.tax_rate) > 0 && (
                                    <div className="flex justify-between text-zinc-600 dark:text-zinc-400">
                                        <span>
                                            Tax ({Number(data.tax_rate)}%)
                                        </span>
                                        <span>{money(taxAmount)}</span>
                                    </div>
                                )}
                                <div className="flex justify-between border-t border-zinc-200 pt-2 text-base font-bold text-zinc-900 dark:border-zinc-800 dark:text-zinc-100">
                                    <span>Total</span>
                                    <span>{money(total)}</span>
                                </div>
                            </div>

                            <button
                                type="submit"
                                disabled={processing || !data.workspace_id}
                                className="mt-5 w-full rounded-md bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-brand-700 disabled:opacity-50"
                            >
                                {processing ? 'Creating…' : 'Create invoice'}
                            </button>
                            <p className="mt-2 text-center text-xs text-zinc-400">
                                You can download the PDF right after.
                            </p>
                        </div>
                    </div>
                </form>
            </div>
        </AdminSidebarLayout>
    );
}
