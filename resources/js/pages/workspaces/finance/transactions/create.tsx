import PageHeader from '@/components/common/PageHeader';
import {
    AccountOpt,
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
    accounts: AccountOpt[];
    transactionTypes: TransactionTypeItem[];
    users: UserOpt[];
    products: string[];
    departments: string[];
    defaultAccountId?: number | null;
    returnTo?: string | null;
}

export default function TransactionCreate({
    workspace,
    accounts,
    transactionTypes,
    users,
    products,
    departments,
    defaultAccountId,
    returnTo,
}: Props) {
    const base = `/workspaces/${workspace.slug}/finance/transactions`;
    const backTo = returnTo ?? base;

    return (
        <AppLayout>
            <Head title={`${workspace.name} - Add Transaction`} />
            <div className="mx-auto w-full max-w-2xl p-4 md:p-6">
                <PageHeader
                    title="Add Transaction"
                    description="Record a new ledger entry."
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
                        accounts={accounts}
                        transactionTypes={transactionTypes}
                        users={users}
                        products={products}
                        departments={departments}
                        defaults={
                            defaultAccountId
                                ? { account_id: defaultAccountId }
                                : undefined
                        }
                        workspaceSlug={workspace.slug}
                        returnTo={returnTo ?? undefined}
                        onCancel={() => router.get(backTo)}
                    />
                </div>
            </div>
        </AppLayout>
    );
}
