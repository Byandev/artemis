import ComponentCard from '@/components/common/ComponentCard';
import PageHeader from '@/components/common/PageHeader';
import AdminSidebarLayout from '@/layouts/admin/admin-sidebar-layout';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import SubscriptionPlanForm from './partials/subscription-plan-form';

export default function Create() {
    const { data, setData, post, processing, errors } = useForm({
        code: '',
        name: '',
        price_php: '0',
        order_limit: '' as string,
        shop_limit: '' as string,
        data_retention_months: '12',
        analytics_tier: 'basic',
        parcel_journey_rate_php: '0',
        parcel_journey_sms_enabled: false,
        support_tier: 'chat',
        trial_days: '' as string,
        is_active: true,
        sort_order: '0',
    });

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        post('/admin/subscription-plans');
    }

    return (
        <AdminSidebarLayout>
            <Head title="Admin | Create Subscription Plan" />

            <div className="p-4 md:p-6">
                <PageHeader
                    title="Create Subscription Plan"
                    description="Add a new subscription plan for workspaces."
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
                                Create Plan
                            </button>
                        </div>
                    </form>
                </ComponentCard>
            </div>
        </AdminSidebarLayout>
    );
}
