import ComponentCard from '@/components/common/ComponentCard';
import PageHeader from '@/components/common/PageHeader';
import AdminSidebarLayout from '@/layouts/admin/admin-sidebar-layout';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import SimForm, { Option } from './partials/sim-form';

interface Props {
    workspaces: { id: number; name: string }[];
    statuses: Option[];
    carriers: Option[];
}

export default function Create({ workspaces, statuses, carriers }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        workspace_id: '',
        phone_number: '',
        carrier: '',
        port_number: '',
        label: '',
        status: 'pending_shipment',
        notes: '',
    });

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        post('/admin/sims');
    }

    return (
        <AdminSidebarLayout>
            <Head title="Admin | Add SIM" />

            <div className="p-4 md:p-6">
                <PageHeader
                    title="Add SIM"
                    description="Provision a SIM and assign it to a workspace."
                >
                    <Link
                        href="/admin/sims"
                        className="inline-flex items-center gap-1.5 rounded-md border border-zinc-200 px-3 py-2 text-sm font-medium text-zinc-600 transition-colors hover:bg-zinc-50 dark:border-zinc-700 dark:text-zinc-400 dark:hover:bg-zinc-800"
                    >
                        <ArrowLeft className="h-4 w-4" />
                        Back
                    </Link>
                </PageHeader>

                <ComponentCard className="mt-6">
                    <form onSubmit={handleSubmit}>
                        <SimForm
                            data={data}
                            setData={setData}
                            errors={errors}
                            workspaces={workspaces}
                            statuses={statuses}
                            carriers={carriers}
                        />
                        <div className="mt-6 flex justify-end border-t border-zinc-100 pt-6 dark:border-zinc-800">
                            <button
                                type="submit"
                                disabled={processing}
                                className="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-brand-700 disabled:opacity-50"
                            >
                                Add SIM
                            </button>
                        </div>
                    </form>
                </ComponentCard>
            </div>
        </AdminSidebarLayout>
    );
}
