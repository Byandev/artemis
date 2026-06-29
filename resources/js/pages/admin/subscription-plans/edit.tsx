import ComponentCard from '@/components/common/ComponentCard';
import PageHeader from '@/components/common/PageHeader';
import AdminSidebarLayout from '@/layouts/admin/admin-sidebar-layout';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import SubscriptionPlanForm from './partials/subscription-plan-form';

interface SubscriptionPlan {
    id: number;
    code: string;
    name: string;
    price_php: string;
    order_limit: number | null;
    shop_limit: number | null;
    data_retention_months: number;
    analytics_tier: string;
    parcel_journey_rate_php: string | null;
    parcel_journey_sms_enabled: boolean;
    support_tier: string;
    trial_days: number | null;
    is_active: boolean;
    sort_order: number;
}

interface Props {
    plan: SubscriptionPlan;
}

export default function Edit({ plan }: Props) {
    const { data, setData, put, processing, errors } = useForm({
        code: plan.code,
        name: plan.name,
        price_php: plan.price_php,
        order_limit: plan.order_limit?.toString() ?? '',
        shop_limit: plan.shop_limit?.toString() ?? '',
        data_retention_months: plan.data_retention_months.toString(),
        analytics_tier: plan.analytics_tier,
        parcel_journey_rate_php: plan.parcel_journey_rate_php ?? '0',
        parcel_journey_sms_enabled: plan.parcel_journey_sms_enabled,
        support_tier: plan.support_tier,
        trial_days: plan.trial_days?.toString() ?? '',
        is_active: plan.is_active,
        sort_order: plan.sort_order.toString(),
    });

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        put(`/admin/subscription-plans/${plan.id}`);
    }

    return (
        <AdminSidebarLayout>
            <Head title={`Admin | Edit ${plan.name}`} />

            <div className="p-4 md:p-6">
                <PageHeader
                    title={`Edit: ${plan.name}`}
                    description="Update subscription plan details."
                >
                    <Link
                        href="/admin/subscription-plans"
                        className="inline-flex items-center gap-1.5 rounded-md border border-zinc-200 px-3 py-2 text-sm font-medium text-zinc-600 transition-colors hover:bg-zinc-50 dark:border-zinc-700 dark:text-zinc-400 dark:hover:bg-zinc-800"
                    >
                        <ArrowLeft className="h-4 w-4" />
                        Back
                    </Link>
                </PageHeader>

                <ComponentCard className="mt-6">
                    <form onSubmit={handleSubmit}>
                        <SubscriptionPlanForm
                            data={data}
                            setData={setData}
                            errors={errors}
                        />
                        <div className="mt-6 flex justify-end border-t border-zinc-100 pt-6 dark:border-zinc-800">
                            <button
                                type="submit"
                                disabled={processing}
                                className="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-brand-700 disabled:opacity-50"
                            >
                                Update Plan
                            </button>
                        </div>
                    </form>
                </ComponentCard>
            </div>
        </AdminSidebarLayout>
    );
}
