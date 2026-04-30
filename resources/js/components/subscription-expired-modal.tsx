import { usePage } from '@inertiajs/react';
import { AlertTriangle, Check } from 'lucide-react';

interface SubscriptionPlan {
    id: number;
    code: string;
    name: string;
    price_php: string;
    order_limit: number | null;
    page_limit: number | null;
    data_retention_months: number;
    analytics_tier: string;
    support_tier: string;
}

interface SubscriptionExpiredData {
    workspace: { id: number; name: string; slug: string };
    plans: SubscriptionPlan[];
    current_period_end: string | null;
}

export default function SubscriptionExpiredModal() {
    const { subscriptionExpired } = usePage().props as { subscriptionExpired?: SubscriptionExpiredData };

    if (!subscriptionExpired) return null;

    const { workspace, plans, current_period_end } = subscriptionExpired;

    return (
        <div className="fixed inset-0 z-[100] flex items-center justify-center bg-black/60 backdrop-blur-sm">
            <div className="w-full max-w-4xl mx-4 max-h-[90vh] overflow-y-auto rounded-2xl bg-white p-8 shadow-2xl dark:bg-zinc-900">
                <div className="mb-6 flex items-center gap-3">
                    <div className="flex h-10 w-10 items-center justify-center rounded-full bg-yellow-100 dark:bg-yellow-500/20">
                        <AlertTriangle className="h-5 w-5 text-yellow-600 dark:text-yellow-400" />
                    </div>
                    <div>
                        <h2 className="text-xl font-bold text-zinc-900 dark:text-zinc-100">
                            Subscription Expired
                        </h2>
                        <p className="text-sm text-zinc-500">
                            Your free trial for <strong>{workspace.name}</strong> has ended. Choose a plan to continue.
                        </p>
                        {current_period_end && (
                            <p className="text-sm text-zinc-500">
                                Current period ended on{' '}
                                <strong>
                                    {new Date(current_period_end).toLocaleDateString(undefined, {
                                        year: 'numeric',
                                        month: 'long',
                                        day: 'numeric',
                                    })}
                                </strong>
                            </p>
                        )}
                    </div>
                </div>

                <div className="grid gap-5 md:grid-cols-3">
                    {plans.map((plan) => (
                        <div
                            key={plan.id}
                            className="flex flex-col rounded-xl border border-zinc-200 bg-zinc-50 p-5 dark:border-zinc-700 dark:bg-zinc-800/50"
                        >
                            <h3 className="text-lg font-semibold text-zinc-900 dark:text-zinc-100">{plan.name}</h3>
                            <div className="mt-2">
                                <span className="text-2xl font-bold text-zinc-900 dark:text-zinc-100">
                                    ₱{parseFloat(plan.price_php).toLocaleString()}
                                </span>
                                <span className="text-sm text-zinc-500">/mo</span>
                            </div>

                            <ul className="mt-4 flex-1 space-y-2 text-sm text-zinc-600 dark:text-zinc-400">
                                <li className="flex items-center gap-2">
                                    <Check className="h-3.5 w-3.5 text-green-500" />
                                    {plan.order_limit ? `${plan.order_limit.toLocaleString()} orders/mo` : 'Unlimited orders'}
                                </li>
                                <li className="flex items-center gap-2">
                                    <Check className="h-3.5 w-3.5 text-green-500" />
                                    {plan.page_limit ? `${plan.page_limit} pages` : 'Unlimited pages'}
                                </li>
                                <li className="flex items-center gap-2">
                                    <Check className="h-3.5 w-3.5 text-green-500" />
                                    {plan.data_retention_months} months retention
                                </li>
                                <li className="flex items-center gap-2">
                                    <Check className="h-3.5 w-3.5 text-green-500" />
                                    {plan.analytics_tier === 'full' ? 'All analytics' : 'Basic analytics'}
                                </li>
                                <li className="flex items-center gap-2">
                                    <Check className="h-3.5 w-3.5 text-green-500" />
                                    {plan.support_tier === 'dedicated' ? 'Dedicated support' : plan.support_tier === 'priority_chat' ? 'Priority chat' : 'Chat support'}
                                </li>
                            </ul>

                            <a
                                href="#"
                                className="mt-5 block w-full rounded-md bg-brand-600 px-4 py-2.5 text-center text-sm font-medium text-white hover:bg-brand-700 transition-colors"
                            >
                                Subscribe
                            </a>
                        </div>
                    ))}
                </div>

                <p className="mt-6 text-center text-xs text-zinc-400">
                    Payment integration coming soon. Contact us at{' '}
                    <a href="mailto:hello@artemis.ph" className="text-brand-600 hover:text-brand-700 dark:text-brand-400 underline">
                        hello@artemis.ph
                    </a>{' '}
                    to activate a plan.
                </p>
            </div>
        </div>
    );
}
