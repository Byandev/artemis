interface FormData {
    code: string;
    name: string;
    price_php: string;
    order_limit: string;
    page_limit: string;
    data_retention_months: string;
    analytics_tier: string;
    parcel_journey_rate_php: string;
    parcel_journey_sms_enabled: boolean;
    support_tier: string;
    trial_days: string;
    is_active: boolean;
    sort_order: string;
}

interface Props {
    data: FormData;
    setData: (key: keyof FormData, value: string | boolean) => void;
    errors: Partial<Record<keyof FormData, string>>;
}

export default function SubscriptionPlanForm({ data, setData, errors }: Props) {
    return (
        <div className="grid grid-cols-1 gap-6 p-6 md:grid-cols-2">
            {/* Name */}
            <div>
                <label className="block text-sm font-medium text-zinc-700 dark:text-zinc-300">Name</label>
                <input
                    type="text"
                    value={data.name}
                    onChange={(e) => setData('name', e.target.value)}
                    className="mt-1 w-full rounded-md border border-zinc-200 bg-white px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-brand-500/20 dark:border-zinc-700 dark:bg-zinc-900"
                    placeholder="e.g. Growth Plan"
                />
                {errors.name && <p className="mt-1 text-xs text-red-500">{errors.name}</p>}
            </div>

            {/* Code */}
            <div>
                <label className="block text-sm font-medium text-zinc-700 dark:text-zinc-300">Code</label>
                <input
                    type="text"
                    value={data.code}
                    onChange={(e) => setData('code', e.target.value)}
                    className="mt-1 w-full rounded-md border border-zinc-200 bg-white px-3 py-2 text-sm font-mono outline-none focus:ring-2 focus:ring-brand-500/20 dark:border-zinc-700 dark:bg-zinc-900"
                    placeholder="e.g. growth"
                />
                {errors.code && <p className="mt-1 text-xs text-red-500">{errors.code}</p>}
            </div>

            {/* Price */}
            <div>
                <label className="block text-sm font-medium text-zinc-700 dark:text-zinc-300">Price (PHP)</label>
                <input
                    type="number"
                    step="0.01"
                    min="0"
                    value={data.price_php}
                    onChange={(e) => setData('price_php', e.target.value)}
                    className="mt-1 w-full rounded-md border border-zinc-200 bg-white px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-brand-500/20 dark:border-zinc-700 dark:bg-zinc-900"
                />
                {errors.price_php && <p className="mt-1 text-xs text-red-500">{errors.price_php}</p>}
            </div>

            {/* Order Limit */}
            <div>
                <label className="block text-sm font-medium text-zinc-700 dark:text-zinc-300">Order Limit</label>
                <input
                    type="number"
                    min="0"
                    value={data.order_limit}
                    onChange={(e) => setData('order_limit', e.target.value)}
                    className="mt-1 w-full rounded-md border border-zinc-200 bg-white px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-brand-500/20 dark:border-zinc-700 dark:bg-zinc-900"
                    placeholder="Leave empty for unlimited"
                />
                {errors.order_limit && <p className="mt-1 text-xs text-red-500">{errors.order_limit}</p>}
            </div>

            {/* Page Limit */}
            <div>
                <label className="block text-sm font-medium text-zinc-700 dark:text-zinc-300">Page Limit</label>
                <input
                    type="number"
                    min="0"
                    value={data.page_limit}
                    onChange={(e) => setData('page_limit', e.target.value)}
                    className="mt-1 w-full rounded-md border border-zinc-200 bg-white px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-brand-500/20 dark:border-zinc-700 dark:bg-zinc-900"
                    placeholder="Leave empty for unlimited"
                />
                {errors.page_limit && <p className="mt-1 text-xs text-red-500">{errors.page_limit}</p>}
            </div>

            {/* Data Retention Months */}
            <div>
                <label className="block text-sm font-medium text-zinc-700 dark:text-zinc-300">Data Retention (months)</label>
                <input
                    type="number"
                    min="1"
                    value={data.data_retention_months}
                    onChange={(e) => setData('data_retention_months', e.target.value)}
                    className="mt-1 w-full rounded-md border border-zinc-200 bg-white px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-brand-500/20 dark:border-zinc-700 dark:bg-zinc-900"
                />
                {errors.data_retention_months && <p className="mt-1 text-xs text-red-500">{errors.data_retention_months}</p>}
            </div>

            {/* Analytics Tier */}
            <div>
                <label className="block text-sm font-medium text-zinc-700 dark:text-zinc-300">Analytics Tier</label>
                <select
                    value={data.analytics_tier}
                    onChange={(e) => setData('analytics_tier', e.target.value)}
                    className="mt-1 w-full rounded-md border border-zinc-200 bg-white px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-brand-500/20 dark:border-zinc-700 dark:bg-zinc-900"
                >
                    <option value="basic">Basic</option>
                    <option value="full">Full</option>
                </select>
                {errors.analytics_tier && <p className="mt-1 text-xs text-red-500">{errors.analytics_tier}</p>}
            </div>

            {/* Support Tier */}
            <div>
                <label className="block text-sm font-medium text-zinc-700 dark:text-zinc-300">Support Tier</label>
                <select
                    value={data.support_tier}
                    onChange={(e) => setData('support_tier', e.target.value)}
                    className="mt-1 w-full rounded-md border border-zinc-200 bg-white px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-brand-500/20 dark:border-zinc-700 dark:bg-zinc-900"
                >
                    <option value="chat">Chat</option>
                    <option value="priority_chat">Priority Chat</option>
                    <option value="dedicated">Dedicated</option>
                </select>
                {errors.support_tier && <p className="mt-1 text-xs text-red-500">{errors.support_tier}</p>}
            </div>

            {/* Parcel Journey Rate */}
            <div>
                <label className="block text-sm font-medium text-zinc-700 dark:text-zinc-300">Parcel Journey Rate (PHP)</label>
                <input
                    type="number"
                    step="0.01"
                    min="0"
                    value={data.parcel_journey_rate_php}
                    onChange={(e) => setData('parcel_journey_rate_php', e.target.value)}
                    className="mt-1 w-full rounded-md border border-zinc-200 bg-white px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-brand-500/20 dark:border-zinc-700 dark:bg-zinc-900"
                />
                {errors.parcel_journey_rate_php && <p className="mt-1 text-xs text-red-500">{errors.parcel_journey_rate_php}</p>}
            </div>

            {/* Trial Days */}
            <div>
                <label className="block text-sm font-medium text-zinc-700 dark:text-zinc-300">Trial Days</label>
                <input
                    type="number"
                    min="0"
                    value={data.trial_days}
                    onChange={(e) => setData('trial_days', e.target.value)}
                    className="mt-1 w-full rounded-md border border-zinc-200 bg-white px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-brand-500/20 dark:border-zinc-700 dark:bg-zinc-900"
                    placeholder="Leave empty for no trial"
                />
                {errors.trial_days && <p className="mt-1 text-xs text-red-500">{errors.trial_days}</p>}
            </div>

            {/* Sort Order */}
            <div>
                <label className="block text-sm font-medium text-zinc-700 dark:text-zinc-300">Sort Order</label>
                <input
                    type="number"
                    min="0"
                    value={data.sort_order}
                    onChange={(e) => setData('sort_order', e.target.value)}
                    className="mt-1 w-full rounded-md border border-zinc-200 bg-white px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-brand-500/20 dark:border-zinc-700 dark:bg-zinc-900"
                />
                {errors.sort_order && <p className="mt-1 text-xs text-red-500">{errors.sort_order}</p>}
            </div>

            {/* Toggles */}
            <div className="space-y-4">
                <div className="flex items-center gap-3">
                    <input
                        type="checkbox"
                        id="is_active"
                        checked={data.is_active}
                        onChange={(e) => setData('is_active', e.target.checked)}
                        className="h-4 w-4 rounded border-zinc-300 text-brand-600 focus:ring-brand-500"
                    />
                    <label htmlFor="is_active" className="text-sm font-medium text-zinc-700 dark:text-zinc-300">Active</label>
                </div>
            </div>
        </div>
    );
}
