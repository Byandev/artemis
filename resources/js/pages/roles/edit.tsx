import React, { useState } from 'react';
import { Head, useForm, Link } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { ArrowLeft, UserCog, ShieldCheck, CheckCircle2, Info, AlertCircle } from 'lucide-react';

interface Workspace {
    id: number;
    name: string;
    slug: string;
}

interface User {
    id: number;
    name: string;
    email: string;
    role: string;
}

interface Role {
    id: number;
    display_name: string;
    name: string;
    description: string;
    role: string;
}

interface Props {
    workspace: Workspace;
    user?: User;
    role?: Role;
    availableRoles?: Role[];
}

export default function Edit({ workspace, user, role, availableRoles = [] }: Props) {
    const isUserEdit = !!user;
    const [showSuccessModal, setShowSuccessModal] = useState(false);

    const { data, setData, patch, processing, errors } = useForm({
        display_name: role?.display_name || '',
        description: isUserEdit
            ? (availableRoles.find(r => r.role === user?.role)?.description || '')
            : (role?.description || ''),
        role: isUserEdit ? (user?.role || '') : (role?.role || ''),
    });

    // Validation check for the Submit button
    const isInvalid = isUserEdit
        ? !data.role || !data.description
        : !data.display_name || !data.description;

    const handleRoleChange = (newRoleSlug: string) => {
        setData('role', newRoleSlug);
        if (isUserEdit) {
            const roleDetails = availableRoles.find(r => r.role === newRoleSlug);
            if (roleDetails) {
                setData('description', roleDetails.description || '');
            }
        }
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        // Final guard before sending
        if (isInvalid) return;

        if (isUserEdit) {
            patch(`/workspaces/${workspace.slug}/users/${user.id}`, {
                onSuccess: () => setShowSuccessModal(true),
            });
        } else {
            patch(`/workspaces/${workspace.slug}/roles/${role?.id}`, {
                onSuccess: () => setShowSuccessModal(true),
            });
        }
    };

    return (
        <AppLayout>
            <Head title={isUserEdit ? `Edit User - ${user?.name}` : `Edit Role - ${role?.display_name}`} />

            <div className="flex flex-1 flex-col items-center justify-center p-6 min-h-screen w-full bg-gray-50/50">
                <div className="bg-white border rounded-[40px] shadow-2xl p-10 max-w-2xl w-full">

                    <Link
                        href={`/workspaces/${workspace.slug}/roles`}
                        className="flex items-center gap-2 text-sm text-gray-400 hover:text-[#2dd4bf] mb-6"
                    >
                        <ArrowLeft className="w-4 h-4" />
                        Back to Management
                    </Link>

                    <div className="text-center space-y-2 mb-8">
                        <h1 className="text-3xl font-bold text-gray-900">
                            {isUserEdit ? 'Change User Role' : 'Edit Role Definition'}
                        </h1>
                    </div>

                    <form onSubmit={handleSubmit} className="space-y-6">
                        {isUserEdit ? (
                            <div className="space-y-6">
                                <div className="space-y-2">
                                    <label className="text-sm font-medium text-gray-700">Assign Reach/Access</label>
                                    <select
                                        required
                                        value={data.role}
                                        onChange={(e) => handleRoleChange(e.target.value)}
                                        className={`w-full px-4 py-3 border rounded-xl text-sm outline-none transition-all ${errors.role ? 'border-red-500 ring-1 ring-red-500' : 'border-gray-200 focus:ring-2 focus:ring-[#2dd4bf]'}`}
                                    >
                                        <option value="" disabled>Select access level...</option>
                                        {availableRoles.map((r) => (
                                            <option key={r.id} value={r.role}>{r.display_name}</option>
                                        ))}
                                    </select>
                                    {errors.role && <p className="text-red-500 text-xs flex items-center gap-1"><AlertCircle className="w-3 h-3" /> {errors.role}</p>}
                                </div>

                                <div className="space-y-2">
                                    <div className="flex items-center justify-between">
                                        <label className="text-[10px] uppercase font-bold text-gray-400 tracking-widest">Role Description</label>
                                        <div className="flex items-center gap-1 text-amber-600 bg-amber-50 px-2 py-0.5 rounded text-[9px] font-bold">
                                            <Info className="w-3 h-3" /> UPDATES GLOBALLY
                                        </div>
                                    </div>
                                    <textarea
                                        required
                                        value={data.description}
                                        onChange={(e) => setData('description', e.target.value)}
                                        className={`w-full px-4 py-3 border rounded-xl text-sm outline-none transition-all bg-gray-50/30 ${errors.description ? 'border-red-500 ring-1 ring-red-500' : 'border-gray-200 focus:ring-2 focus:ring-[#2dd4bf]'}`}
                                        rows={4}
                                    />
                                    {errors.description && <p className="text-red-500 text-xs flex items-center gap-1"><AlertCircle className="w-3 h-3" /> {errors.description}</p>}
                                </div>
                            </div>
                        ) : (
                            <div className="space-y-6">
                                <div className="space-y-2">
                                    <label className="text-sm font-medium text-gray-700">Display Name</label>
                                    <input
                                        required
                                        type="text"
                                        value={data.display_name}
                                        onChange={(e) => setData('display_name', e.target.value)}
                                        className={`w-full px-4 py-3 border rounded-xl text-sm outline-none transition-all ${errors.display_name ? 'border-red-500 ring-1 ring-red-500' : 'border-gray-200 focus:ring-2 focus:ring-[#2dd4bf]'}`}
                                    />
                                    {errors.display_name && <p className="text-red-500 text-xs flex items-center gap-1"><AlertCircle className="w-3 h-3" /> {errors.display_name}</p>}
                                </div>
                                <div className="space-y-2">
                                    <label className="text-sm font-medium text-gray-700">Description</label>
                                    <textarea
                                        required
                                        value={data.description}
                                        onChange={(e) => setData('description', e.target.value)}
                                        className={`w-full px-4 py-3 border rounded-xl text-sm outline-none transition-all ${errors.description ? 'border-red-500 ring-1 ring-red-500' : 'border-gray-200 focus:ring-2 focus:ring-[#2dd4bf]'}`}
                                        rows={4}
                                    />
                                    {errors.description && <p className="text-red-500 text-xs flex items-center gap-1"><AlertCircle className="w-3 h-3" /> {errors.description}</p>}
                                </div>
                            </div>
                        )}

                        <div className="pt-6">
                            <button
                                type="submit"
                                disabled={processing || isInvalid}
                                className="w-full bg-[#2dd4bf] hover:bg-[#26b2a1] text-white px-8 py-4 rounded-full text-sm font-bold disabled:opacity-50 disabled:cursor-not-allowed transition-all shadow-lg active:scale-95"
                            >
                                {processing ? 'Saving...' : 'Update Details'}
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            {showSuccessModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
                    <div className="bg-white rounded-[40px] shadow-2xl p-10 max-w-md w-full text-center">
                        <CheckCircle2 className="w-16 h-16 text-[#2dd4bf] mx-auto mb-6" />
                        <h2 className="text-3xl font-bold text-gray-900 mb-2">Success!</h2>
                        <Link
                            href={`/workspaces/${workspace.slug}/roles`}
                            className="inline-block w-full px-8 py-4 bg-[#2dd4bf] text-white font-bold rounded-full mt-6"
                        >
                            Return to Dashboard
                        </Link>
                    </div>
                </div>
            )}
        </AppLayout>
    );
}