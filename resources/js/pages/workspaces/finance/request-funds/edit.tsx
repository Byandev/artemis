import PageHeader from '@/components/common/PageHeader';
import {
    DepartmentOption,
    FundRequestForm,
    FundRequestType,
    PaymentMethodOption,
    ProductOption,
    RequestFund,
    UserOption,
} from '@/components/finance/fund-request-form';
import AppLayout from '@/layouts/app-layout';
import { Workspace } from '@/types/models/Workspace';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';

interface Props {
    workspace: Workspace;
    requestFund: RequestFund;
    users: UserOption[];
    products: ProductOption[];
    transactionTypes: FundRequestType[];
    adSpentTypeIds: number[];
    departments: DepartmentOption[];
    paymentMethods: PaymentMethodOption[];
}

export default function RequestFundEdit({
    workspace,
    requestFund,
    users,
    products,
    transactionTypes,
    adSpentTypeIds,
    departments,
    paymentMethods,
}: Props) {
    const backTo = `/workspaces/${workspace.slug}/finance/request-funds`;

    return (
        <AppLayout>
            <Head title={`${workspace.name} - Edit Fund Request`} />
            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
                <PageHeader
                    title="Edit Fund Request"
                    description={`${requestFund.reference_no} · Update this fund request’s details.`}
                >
                    <Link
                        href={backTo}
                        className="flex h-8 items-center gap-1.5 rounded-lg border border-black/8 bg-white px-3.5 font-mono! text-[12px]! font-medium text-gray-700 hover:bg-stone-50 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-200"
                    >
                        <ArrowLeft className="h-3.5 w-3.5" /> Back
                    </Link>
                </PageHeader>

                <div className="overflow-hidden rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
                    <FundRequestForm
                        requestFund={requestFund}
                        workspaceSlug={workspace.slug}
                        users={users}
                        products={products}
                        transactionTypes={transactionTypes}
                        adSpentTypeIds={adSpentTypeIds}
                        departments={departments}
                        paymentMethods={paymentMethods}
                        onCancel={() => router.get(backTo)}
                    />
                </div>
            </div>
        </AppLayout>
    );
}
