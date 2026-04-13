import AppLayout from '@/layouts/app-layout';
import PageHeader from '@/components/common/PageHeader';
import { Head, useForm, router } from '@inertiajs/react';
import { Workspace } from '@/types/models/Workspace';
import { Plus, Trash2 } from 'lucide-react';

interface InventoryItem {
    id: number;
    sku: string;
    product?: { id: number; name: string };
}

interface OrderItem {
    inventory_item_id: string;
    count: string;
    amount: string;
    total_amount: string;
}

interface PurchasedOrder {
    id: number;
    issue_date: string;
    delivery_no: string | null;
    cust_po_no: string | null;
    control_no: string | null;
    delivery_fee: string;
    total_amount: string;
    status: number;
    items: Array<{
        id: number;
        inventory_item_id: number;
        count: number;
        amount: string;
        total_amount: string;
    }>;
}

interface Props {
    workspace: Workspace;
    order: PurchasedOrder;
    items: InventoryItem[];
}

const STATUSES = [
    { value: 1, label: 'For Approval' },
    { value: 2, label: 'Approved' },
    { value: 3, label: 'To Pay' },
    { value: 4, label: 'Paid' },
    { value: 5, label: 'For Purchase' },
    { value: 6, label: 'Waiting For Delivery' },
    { value: 7, label: 'Delivered' },
    { value: 8, label: 'Cancelled' },
];

export default function Edit({ workspace, order, items }: Props) {
    const { data, setData, put, processing, errors } = useForm({
        issue_date: order.issue_date ?? '',
        delivery_no: order.delivery_no ?? '',
        cust_po_no: order.cust_po_no ?? '',
        control_no: order.control_no ?? '',
        delivery_fee: order.delivery_fee ?? '',
        total_amount: order.total_amount ?? '',
        status: String(order.status ?? 1),
        items: order.items.map((item) => ({
            inventory_item_id: String(item.inventory_item_id),
            count: String(item.count),
            amount: String(item.amount),
            total_amount: String(item.total_amount),
        })),
    });

    const inputClass = "h-10 w-full rounded-[10px] border border-black/8 bg-stone-50 px-3 font-mono! text-[13px]! text-gray-800 placeholder:text-gray-300 outline-none transition-all focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-100 dark:placeholder:text-gray-600";
    const labelClass = "block font-mono text-[10px] font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-1.5";

    const updateItem = (index: number, field: keyof OrderItem, value: string) => {
        const updated = data.items.map((item, i) => {
            if (i !== index) return item;
            const next = { ...item, [field]: value };
            if (field === 'count' || field === 'amount') {
                const count = field === 'count' ? parseFloat(value) : parseFloat(next.count);
                const amount = field === 'amount' ? parseFloat(value) : parseFloat(next.amount);
                next.total_amount = (!isNaN(count) && !isNaN(amount)) ? (count * amount).toFixed(2) : '';
            }
            return next;
        });
        setData('items', updated);
    };

    const addItem = () => setData('items', [...data.items, { inventory_item_id: '', count: '', amount: '', total_amount: '' }]);

    const removeItem = (index: number) =>
        setData('items', data.items.filter((_, i) => i !== index));

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        put(`/workspaces/${workspace.slug}/inventory/purchased-orders/${order.id}`);
    };

    return (
        <AppLayout>
            <Head title={`${workspace.name} - Edit Purchased Order`} />
            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
                <PageHeader
                    title="Edit Purchased Order"
                    description="Update the purchased order details."
                >
                    <button
                        type="button"
                        onClick={() => router.get(`/workspaces/${workspace.slug}/inventory/purchased-orders`)}
                        className="flex h-8 items-center rounded-lg border border-black/8 bg-white px-3.5 font-mono! text-[12px]! font-medium text-gray-600 transition-all hover:bg-stone-100 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-300"
                    >
                        Cancel
                    </button>
                </PageHeader>

                <form onSubmit={handleSubmit} className="space-y-6">
                    {/* Order Details */}
                    <div className="rounded-[14px] border border-black/6 bg-white p-5 dark:border-white/6 dark:bg-zinc-900">
                        <h3 className="mb-4 font-mono text-[11px] font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500">Order Details</h3>
                        <div className="grid grid-cols-2 gap-4 sm:grid-cols-3">
                            <div>
                                <label className={labelClass}>Status <span className="text-red-400">*</span></label>
                                <select value={data.status} onChange={(e) => setData('status', e.target.value)} className={inputClass}>
                                    {STATUSES.map((s) => (
                                        <option key={s.value} value={s.value}>{s.label}</option>
                                    ))}
                                </select>
                                {errors.status && <p className="mt-1 font-mono text-[11px] text-red-500">{errors.status}</p>}
                            </div>
                            <div>
                                <label className={labelClass}>Issue Date <span className="text-red-400">*</span></label>
                                <input type="date" value={data.issue_date} onChange={(e) => setData('issue_date', e.target.value)} className={inputClass} />
                                {errors.issue_date && <p className="mt-1 font-mono text-[11px] text-red-500">{errors.issue_date}</p>}
                            </div>
                            <div>
                                <label className={labelClass}>Delivery No.</label>
                                <input type="text" value={data.delivery_no} onChange={(e) => setData('delivery_no', e.target.value)} placeholder="DR-001" className={inputClass} />
                                {errors.delivery_no && <p className="mt-1 font-mono text-[11px] text-red-500">{errors.delivery_no}</p>}
                            </div>
                            <div>
                                <label className={labelClass}>Cust PO No.</label>
                                <input type="text" value={data.cust_po_no} onChange={(e) => setData('cust_po_no', e.target.value)} placeholder="PO-001" className={inputClass} />
                                {errors.cust_po_no && <p className="mt-1 font-mono text-[11px] text-red-500">{errors.cust_po_no}</p>}
                            </div>
                            <div>
                                <label className={labelClass}>Control No.</label>
                                <input type="text" value={data.control_no} onChange={(e) => setData('control_no', e.target.value)} placeholder="CN-001" className={inputClass} />
                                {errors.control_no && <p className="mt-1 font-mono text-[11px] text-red-500">{errors.control_no}</p>}
                            </div>
                            <div>
                                <label className={labelClass}>Delivery Fee <span className="text-red-400">*</span></label>
                                <input type="number" step="0.01" min="0" value={data.delivery_fee} onChange={(e) => setData('delivery_fee', e.target.value)} placeholder="0.00" className={inputClass} />
                                {errors.delivery_fee && <p className="mt-1 font-mono text-[11px] text-red-500">{errors.delivery_fee}</p>}
                            </div>
                            <div>
                                <label className={labelClass}>Total Amount <span className="text-red-400">*</span></label>
                                <input type="number" step="0.01" min="0" value={data.total_amount} onChange={(e) => setData('total_amount', e.target.value)} placeholder="0.00" className={inputClass} />
                                {errors.total_amount && <p className="mt-1 font-mono text-[11px] text-red-500">{errors.total_amount}</p>}
                            </div>
                        </div>
                    </div>

                    {/* Order Items */}
                    <div className="rounded-[14px] border border-black/6 bg-white p-5 dark:border-white/6 dark:bg-zinc-900">
                        <div className="mb-4 flex items-center justify-between">
                            <h3 className="font-mono text-[11px] font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500">Order Items</h3>
                            <button
                                type="button"
                                onClick={addItem}
                                className="flex h-7 items-center gap-1.5 rounded-lg border border-black/8 bg-stone-50 px-3 font-mono! text-[11px]! font-medium text-gray-600 transition-all hover:bg-stone-100 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-300"
                            >
                                <Plus className="h-3 w-3" />
                                Add Item
                            </button>
                        </div>

                        <div className="space-y-3">
                            <div className="grid grid-cols-[1fr_100px_120px_120px_36px] gap-3">
                                <span className={labelClass}>Inventory Item</span>
                                <span className={labelClass}>Count</span>
                                <span className={labelClass}>Amount</span>
                                <span className={labelClass}>Total</span>
                                <span />
                            </div>

                            {data.items.map((item, i) => (
                                <div key={i} className="grid grid-cols-[1fr_100px_120px_120px_36px] items-center gap-3">
                                    <select
                                        value={item.inventory_item_id}
                                        onChange={(e) => updateItem(i, 'inventory_item_id', e.target.value)}
                                        className={inputClass}
                                    >
                                        <option value="">Select item...</option>
                                        {items.map((inv) => (
                                            <option key={inv.id} value={inv.id}>
                                                {inv.sku}{inv.product ? ` — ${inv.product.name}` : ''}
                                            </option>
                                        ))}
                                    </select>
                                    <input
                                        type="number"
                                        min="1"
                                        placeholder="0"
                                        value={item.count}
                                        onChange={(e) => updateItem(i, 'count', e.target.value)}
                                        className={inputClass}
                                    />
                                    <input
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        placeholder="0.00"
                                        value={item.amount}
                                        onChange={(e) => updateItem(i, 'amount', e.target.value)}
                                        className={inputClass}
                                    />
                                    <input
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        placeholder="0.00"
                                        value={item.total_amount}
                                        onChange={(e) => updateItem(i, 'total_amount', e.target.value)}
                                        className={inputClass}
                                    />
                                    <button
                                        type="button"
                                        onClick={() => removeItem(i)}
                                        disabled={data.items.length === 1}
                                        className="flex h-10 w-9 items-center justify-center rounded-[10px] border border-black/8 text-gray-400 transition-all hover:border-red-200 hover:bg-red-50 hover:text-red-500 disabled:cursor-not-allowed disabled:opacity-30 dark:border-white/8 dark:hover:border-red-800 dark:hover:bg-red-950 dark:hover:text-red-400"
                                    >
                                        <Trash2 className="h-3.5 w-3.5" />
                                    </button>
                                </div>
                            ))}
                        </div>

                        {errors.items && <p className="mt-2 font-mono text-[11px] text-red-500">{errors.items}</p>}
                    </div>

                    {/* Footer */}
                    <div className="flex justify-end gap-2">
                        <button
                            type="button"
                            onClick={() => router.get(`/workspaces/${workspace.slug}/inventory/purchased-orders`)}
                            className="flex h-9 items-center rounded-lg border border-black/8 bg-white px-4 font-mono! text-[12px]! font-medium text-gray-600 transition-all hover:bg-stone-100 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-300"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            disabled={processing}
                            className="flex h-9 items-center rounded-lg bg-emerald-600 px-4 font-mono! text-[12px]! font-medium text-white transition-all hover:bg-emerald-700 disabled:opacity-50"
                        >
                            {processing ? 'Saving…' : 'Save Changes'}
                        </button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
