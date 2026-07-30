import PageHeader from '@/components/common/PageHeader';
import {
    AccountOpt,
    FinanceTransaction,
    FundRequestOption,
    TransactionForm,
    UserOpt,
} from '@/components/finance/transaction-form';
import { TransactionTypeItem } from '@/components/finance/transaction-type';
import AppLayout from '@/layouts/app-layout';
import { Workspace } from '@/types/models/Workspace';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';

interface Props {
    workspace: Workspace;
    transaction: FinanceTransaction;
    accounts: AccountOpt[];
    transactionTypes: TransactionTypeItem[];
    users: UserOpt[];
    products: string[];
    fundRequests: FundRequestOption[];
    departments: string[];
    returnTo?: string | null;
}

export default function TransactionEdit({
    workspace,
    transaction,
    accounts,
    transactionTypes,
    users,
    products,
    fundRequests,
    departments,
    returnTo,
}: Props) {
    const base = `/workspaces/${workspace.slug}/finance/transactions`;
    const backTo = returnTo ?? base;

    return (
        <AppLayout>
            <Head title={`${workspace.name} - Edit Transaction`} />
            <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
                <PageHeader
                    title="Edit Transaction"
                    description="Update this ledger entry."
                >
                    <Link
                        href={backTo}
                        className="flex h-8 items-center gap-1.5 rounded-lg border border-black/8 bg-white px-3.5 font-mono! text-[12px]! font-medium text-gray-700 hover:bg-stone-50 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-200"
                    >
                        <ArrowLeft className="h-3.5 w-3.5" /> Back
                    </Link>
                </PageHeader>

                <div className="overflow-hidden rounded-[14px] border border-black/6 bg-white dark:border-white/6 dark:bg-zinc-900">
                    <TransactionForm
                        transaction={transaction}
                        accounts={accounts}
                        transactionTypes={transactionTypes}
                        users={users}
                        products={products}
                        fundRequests={fundRequests}
                        departments={departments}
                        workspaceSlug={workspace.slug}
                        returnTo={returnTo ?? undefined}
                        onCancel={() => router.get(backTo)}
                    />
                </div>
            </div>
        </AppLayout>
    );
}
