import React, { useMemo, useState } from 'react';
import { Head, useForm, Link } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { ArrowLeft, CheckCircle2, AlertCircle } from 'lucide-react';
import { Workspace } from '@/types/models/Workspace';
import { Role } from '@/types/models/Role';

interface Props {
    workspace: Workspace;
    role?: Role;
}

export default function Edit({ workspace, role }: Props) {
    const [showSuccessModal, setShowSuccessModal] = useState(false);

    const { data, setData, patch, processing, errors } = useForm({
        description: role?.description,
        role: role?.role,
    });

    const isInvalid = useMemo(() => !data.role || !data.description, [data.role, data.description])

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        patch(`/workspaces/${workspace.slug}/roles/${role?.id}`, {
            onSuccess: () => setShowSuccessModal(true),
        });
    };

    return (
        <AppLayout>
            <Head title={`Edit Role - ${role?.display_name}`} />

            <div className="flex min-h-screen w-full flex-1 flex-col items-center justify-center bg-gray-50/50 p-6">
                <div className="w-full max-w-2xl rounded-[40px] border bg-white p-10 shadow-2xl">
                    <Link
                        href={`/workspaces/${workspace.slug}/roles`}
                        className="mb-6 flex items-center gap-2 text-sm text-gray-400 hover:text-[#2dd4bf]"
                    >
                        <ArrowLeft className="h-4 w-4" />
                        Back to Management
                    </Link>

                    <div className="mb-8 space-y-2 text-center">
                        <h1 className="text-3xl font-bold text-gray-900">
                            Edit Role Definition
                        </h1>
                    </div>

                    <form onSubmit={handleSubmit} className="space-y-6">
                        <div className="space-y-6">
                            <div className="space-y-2">
                                <label className="text-sm font-medium text-gray-700">
                                    Display Name
                                </label>
                                <input
                                    required
                                    type="text"
                                    value={data.role}
                                    onChange={(e) =>
                                        setData('role', e.target.value)
                                    }
                                    className={`w-full rounded-xl border px-4 py-3 text-sm transition-all outline-none ${errors.role ? 'border-red-500 ring-1 ring-red-500' : 'border-gray-200 focus:ring-2 focus:ring-[#2dd4bf]'}`}
                                />
                                {errors.role && (
                                    <p className="flex items-center gap-1 text-xs text-red-500">
                                        <AlertCircle className="h-3 w-3" />{' '}
                                        {errors.role}
                                    </p>
                                )}
                            </div>
                            <div className="space-y-2">
                                <label className="text-sm font-medium text-gray-700">
                                    Description
                                </label>
                                <textarea
                                    required
                                    value={data.description}
                                    onChange={(e) =>
                                        setData('description', e.target.value)
                                    }
                                    className={`w-full rounded-xl border px-4 py-3 text-sm transition-all outline-none ${errors.description ? 'border-red-500 ring-1 ring-red-500' : 'border-gray-200 focus:ring-2 focus:ring-[#2dd4bf]'}`}
                                    rows={4}
                                />
                                {errors.description && (
                                    <p className="flex items-center gap-1 text-xs text-red-500">
                                        <AlertCircle className="h-3 w-3" />{' '}
                                        {errors.description}
                                    </p>
                                )}
                            </div>
                        </div>

                        <div className="pt-6">
                            <button
                                type="submit"
                                disabled={processing || isInvalid}
                                className="w-full rounded-full bg-[#2dd4bf] px-8 py-4 text-sm font-bold text-white shadow-lg transition-all hover:bg-[#26b2a1] active:scale-95 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                {processing ? 'Saving...' : 'Update Details'}
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            {showSuccessModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-sm">
                    <div className="w-full max-w-md rounded-[40px] bg-white p-10 text-center shadow-2xl">
                        <CheckCircle2 className="mx-auto mb-6 h-16 w-16 text-[#2dd4bf]" />
                        <h2 className="mb-2 text-3xl font-bold text-gray-900">
                            Success!
                        </h2>
                        <Link
                            href={`/workspaces/${workspace.slug}/roles`}
                            className="mt-6 inline-block w-full rounded-full bg-[#2dd4bf] px-8 py-4 font-bold text-white"
                        >
                            Return to Dashboard
                        </Link>
                    </div>
                </div>
            )}
        </AppLayout>
    );
}
