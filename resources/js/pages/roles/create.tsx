import AppLayout from '@/layouts/app-layout';
import { Head, useForm, Link, usePage } from '@inertiajs/react';
import { UserPlus, ArrowLeft } from 'lucide-react';
import { route } from 'ziggy-js';

interface Props {
    workspace: {
        id: number;
        name: string;
        slug: string;
    };
}

export default function Create({ workspace }: Props) {
    const { ziggy } = usePage().props as any;

    const { data, setData, post, processing, errors } = useForm({
        email: '',
        role: 'user',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        // Casting 'route' as any bypasses the 'This expression is not callable' TS error
        post((route as any)('roles.store', { workspace: workspace.slug }, true, ziggy));
    };

    return (
        <AppLayout>
            <Head title="Assign New Role" />

            <div className="flex flex-1 flex-col items-center justify-center p-6 min-h-screen w-full">
                <div className="bg-white border rounded-[40px] shadow-2xl p-10 max-w-2xl w-full transform transition-all scale-100">

                    {/* Header Section */}
                    <div className="text-center space-y-2 mb-8">
                        <h1 className="text-3xl font-bold tracking-tight text-gray-900">Invite a Member</h1>
                        <p className="text-sm text-gray-500">
                            Send an invitation to join <strong>{workspace.name}</strong>
                        </p>
                    </div>

                    {/* Visual Cue Section */}
                    <div className="flex flex-col items-center gap-2 mb-8 text-gray-700 border-b border-gray-100 pb-6">
                        <div className="bg-teal-50 p-4 rounded-full">
                            <UserPlus className="w-6 h-6 text-[#2dd4bf]" />
                        </div>
                        <h2 className="font-semibold text-lg">Invitation Details</h2>
                    </div>

                    <form onSubmit={submit} className="space-y-6">
                        {/* Email Input */}
                        <div className="space-y-2">
                            <label className="text-sm font-medium text-gray-700 ml-1">Email Address</label>
                            <input
                                type="email"
                                value={data.email}
                                onChange={e => setData('email', e.target.value)}
                                placeholder="colleague@example.com"
                                className={`w-full px-4 py-3 border rounded-xl text-sm focus:ring-2 focus:ring-[#2dd4bf] outline-none transition-all shadow-sm ${errors.email ? 'border-red-500 bg-red-50' : 'border-gray-200 bg-white'
                                    }`}
                            />
                            {errors.email && <p className="text-red-500 text-xs mt-1 ml-1 font-medium">{errors.email}</p>}
                        </div>

                        {/* Role Selection */}
                        <div className="space-y-2">
                            <label className="text-sm font-medium text-gray-700 ml-1">Workspace Role</label>
                            <select
                                value={data.role}
                                onChange={e => setData('role', e.target.value)}
                                className="w-full px-4 py-3 border border-gray-200 rounded-xl text-sm bg-white focus:ring-2 focus:ring-[#2dd4bf] outline-none transition-all shadow-sm cursor-pointer"
                            >
                                <option value="user">Member (Standard Access)</option>
                                <option value="admin">Admin (Full Management)</option>
                                <option value="super-admin">Super Admin (System Owner)</option>
                            </select>

                            {/* Dynamic Helper Text */}
                            <p className="min-h-[1.25rem] px-1 text-xs text-gray-500 italic leading-relaxed">
                                {data.role === 'user' && 'Members can view and edit shared data but cannot change workspace settings.'}
                                {data.role === 'admin' && 'Admins can manage members, billing, and all workspace settings.'}
                                {data.role === 'super-admin' && 'Super Admins have total control over system-wide configuration.'}
                            </p>

                            {errors.role && <p className="text-red-500 text-xs mt-1 ml-1 font-medium">{errors.role}</p>}
                        </div>

                        {/* Action Buttons */}
                        <div className="pt-6 space-y-4">
                            <button
                                type="submit"
                                disabled={processing}
                                className="w-full inline-flex items-center justify-center gap-2 px-8 py-4 bg-[#2dd4bf] hover:bg-[#26b2a1] text-white text-sm font-bold rounded-full transition-all shadow-lg shadow-teal-100 disabled:opacity-50 active:scale-95"
                            >
                                {processing ? 'Sending...' : 'Send Invitation'}
                            </button>

                            <div className="text-center">
                                <Link
                                    href={`/workspaces/${workspace.slug}/roles`}
                                    className="text-xs text-gray-400 hover:text-gray-600 font-medium transition-colors underline underline-offset-4"
                                >
                                    Cancel and return to list
                                </Link>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </AppLayout>
    );
}