import React, { useState } from 'react';
import { Head, useForm, Link } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import ComponentCard from '@/components/common/ComponentCard';
import { Button } from '@/components/ui/button';
import { ShieldPlus, Check, ChevronLeft } from 'lucide-react';

interface Workspace {
    id: number;
    name: string;
    slug: string;
}

interface Props {
    workspace: Workspace;
}

export default function AddRole({ workspace }: Props) {
    const [showSuccessModal, setShowSuccessModal] = useState(false);

    const { data, setData, post, processing, errors, reset } = useForm({
        display_name: '',
        role: '',
        description: '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(`/workspaces/${workspace.slug}/roles/add`, {
            onSuccess: () => {
                setShowSuccessModal(true);
                reset();
            },
        });
    };

    return (
        <AppLayout>
            <Head title={`Add Role - ${workspace.name}`} />

            <div className="h-[calc(100vh-64px)] flex flex-col items-center justify-center p-4">
                <div className="w-full max-w-3xl space-y-4">
                    <div className="space-y-0.5 px-2">
                        <div className="flex items-center gap-2">
                            <Link href={`/workspaces/${workspace.slug}/roles`} className="group">
                                <ChevronLeft className="w-5 h-5 text-gray-400 group-hover:text-gray-900 transition-colors" />
                            </Link>
                            <h1 className="text-2xl font-extrabold tracking-tight text-gray-900">Define New Role</h1>
                        </div>
                        <p className="text-xs text-gray-500 font-medium ml-7">Configure permissions for {workspace.name}</p>
                    </div>

                    <ComponentCard desc="Enter the details below to create a new workspace role">
                        <div className="rounded-xl border border-gray-100 bg-white shadow-sm overflow-hidden">
                            <div className="p-4 border-b border-gray-50 bg-slate-50/30">
                                <div className="flex items-center gap-3">
                                    <div className="bg-white p-1.5 rounded-lg border border-gray-100 shadow-sm">
                                        <ShieldPlus className="w-4 h-4 text-[#2dd4bf]" />
                                    </div>
                                    <span className="font-bold text-gray-800 text-xs uppercase tracking-widest">Role Configuration</span>
                                </div>
                            </div>

                            <form onSubmit={handleSubmit} className="p-6 space-y-4">
                                <div className="space-y-1.5">
                                    <label className="text-xs font-bold text-gray-700 ml-1">Display Name</label>
                                    <input
                                        type="text"
                                        value={data.display_name}
                                        onChange={(e) => setData('display_name', e.target.value)}
                                        placeholder="e.g. Moderator"
                                        className={`w-full px-4 py-2.5 border rounded-lg text-sm focus:ring-4 focus:ring-[#2dd4bf]/10 focus:border-[#2dd4bf] outline-none transition-all ${errors.display_name ? 'border-red-500' : 'border-gray-200'
                                            }`}
                                    />
                                    {errors.display_name && <p className="text-red-500 text-[10px] font-semibold">{errors.display_name}</p>}
                                </div>

                                <div className="space-y-1.5">
                                    <label className="text-xs font-bold text-gray-700 ml-1">Role Identifier</label>
                                    <input
                                        type="text"
                                        value={data.role}
                                        onChange={(e) => setData('role', e.target.value.toLowerCase().replace(/\s+/g, '-'))}
                                        placeholder="e.g. moderator"
                                        className={`w-full px-4 py-2.5 border rounded-lg text-sm focus:ring-4 focus:ring-[#2dd4bf]/10 focus:border-[#2dd4bf] outline-none transition-all ${errors.role ? 'border-red-500' : 'border-gray-200'
                                            }`}
                                    />
                                    {errors.role && <p className="text-red-500 text-[10px] font-semibold">{errors.role}</p>}
                                </div>

                                <div className="space-y-1.5">
                                    <label className="text-xs font-bold text-gray-700 ml-1">Description</label>
                                    <textarea
                                        rows={3}
                                        value={data.description}
                                        onChange={(e) => setData('description', e.target.value)}
                                        placeholder="Briefly describe the responsibilities..."
                                        className="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-4 focus:ring-[#2dd4bf]/10 focus:border-[#2dd4bf] outline-none transition-all resize-none"
                                    />
                                </div>

                                <div className="pt-4 flex flex-col items-center gap-3">
                                    <Button
                                        type="submit"
                                        disabled={processing}
                                        className="w-full sm:w-56 bg-[#2dd4bf] hover:bg-[#26b2a1] text-white font-bold py-5 rounded-lg shadow-md shadow-teal-100 transition-transform active:scale-95"
                                    >
                                        {processing ? 'Saving...' : 'Save Role'}
                                    </Button>

                                    <Link
                                        href={`/workspaces/${workspace.slug}/roles`}
                                        className="text-xs font-bold text-gray-400 hover:text-gray-800 transition-colors"
                                    >
                                        Cancel and go back
                                    </Link>
                                </div>
                            </form>
                        </div>
                    </ComponentCard>
                </div>
            </div>

            {showSuccessModal && (
                <div className="fixed inset-0 bg-slate-900/60 backdrop-blur-md flex items-center justify-center z-50 p-4">
                    <div className="bg-white rounded-[24px] p-8 max-w-sm w-full text-center shadow-2xl">
                        <div className="bg-emerald-50 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4">
                            <Check className="text-emerald-500 w-8 h-8" />
                        </div>
                        <h2 className="text-xl font-black text-gray-900 mb-1">Role Created!</h2>
                        <p className="text-xs text-gray-500 mb-6 font-medium">Successfully registered to your workspace.</p>

                        <div className="flex flex-col gap-2">
                            <Button
                                onClick={() => setShowSuccessModal(false)}
                                className="w-full bg-[#2dd4bf] text-white font-bold py-5 rounded-lg hover:bg-[#26b2a1]"
                            >
                                Create Another
                            </Button>
                            <Link
                                href={`/workspaces/${workspace.slug}/roles`}
                                className="w-full py-2 text-xs font-bold text-gray-400 hover:text-gray-600 transition-colors"
                            >
                                Back to List
                            </Link>
                        </div>
                    </div>
                </div>
            )}
        </AppLayout>
    );
}