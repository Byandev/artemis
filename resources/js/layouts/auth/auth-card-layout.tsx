import { home } from '@/routes';
import { Link } from '@inertiajs/react';
import { type PropsWithChildren } from 'react';

export default function AuthCardLayout({
    children,
    title,
    description,
}: PropsWithChildren<{
    name?: string;
    title?: string;
    description?: string;
}>) {
    return (
        <div className="flex min-h-svh">
            {/* ── Left branding panel (desktop only) ──────────────────── */}
            <div className="hidden lg:flex lg:w-[460px] xl:w-[520px] flex-col justify-between bg-zinc-950 p-12 relative overflow-hidden shrink-0">
                {/* Grid overlay */}
                <div className="pointer-events-none absolute inset-0 bg-[linear-gradient(rgba(255,255,255,0.025)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,0.025)_1px,transparent_1px)] bg-[size:48px_48px]" />
                {/* Glow accents */}
                <div className="pointer-events-none absolute -top-40 -left-40 h-80 w-80 rounded-full bg-brand-500/10 blur-3xl" />
                <div className="pointer-events-none absolute bottom-0 right-0 h-[28rem] w-[28rem] rounded-full bg-brand-500/5 blur-3xl" />

                {/* Logo */}
                <div className="relative z-10">
                    <Link href={home()} className="flex items-center gap-3 w-fit">
                        <img src="/img/logo/artemis.png" alt="Artemis" className="h-9 w-9 object-contain" />
                        <span className="text-white font-semibold text-[17px] tracking-tight">Artemis</span>
                    </Link>
                </div>

                {/* Hero copy */}
                <div className="relative z-10 space-y-8">
                    <div className="space-y-4">
                        <div className="inline-flex items-center gap-2 rounded-full border border-brand-500/20 bg-brand-500/10 px-3 py-1">
                            <span className="h-1.5 w-1.5 rounded-full bg-brand-400" />
                            <span className="font-mono text-[10px] font-semibold tracking-widest text-brand-400 uppercase">
                                Artemis
                            </span>
                        </div>
                        <h2 className="text-[32px] font-bold leading-tight tracking-tight text-white">
                            Your operations,<br />streamlined.
                        </h2>
                        <p className="text-[14px] leading-relaxed text-zinc-400">
                            Manage orders, track deliveries, and analyse<br />
                            performance — all in one unified platform.
                        </p>
                    </div>

                    <div className="space-y-3">
                        {[
                            'RTS & Delivery Management',
                            'Real-time Order Analytics',
                            'Multi-workspace Support',
                            'Role-based Access Control',
                        ].map((feature) => (
                            <div key={feature} className="flex items-center gap-3">
                                <div className="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-brand-500/15">
                                    <svg className="h-3 w-3 text-brand-400" fill="none" viewBox="0 0 12 12">
                                        <path d="M2 6l2.5 2.5L10 3" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" />
                                    </svg>
                                </div>
                                <span className="text-[13px] text-zinc-300">{feature}</span>
                            </div>
                        ))}
                    </div>
                </div>

                {/* Footer */}
                <p className="relative z-10 text-[12px] text-zinc-600">
                    © {new Date().getFullYear()} Artemis. All rights reserved.
                </p>
            </div>

            {/* ── Right form panel ────────────────────────────────────── */}
            <div className="flex flex-1 flex-col items-center justify-center bg-white dark:bg-zinc-900 px-6 py-12">
                {/* Mobile logo */}
                <div className="mb-8 flex lg:hidden flex-col items-center gap-2">
                    <Link href={home()} className="flex items-center gap-2.5">
                        <img src="/img/logo/artemis.png" alt="Artemis" className="h-10 w-10 object-contain" />
                        <span className="text-[17px] font-semibold tracking-tight text-gray-900 dark:text-white">Artemis</span>
                    </Link>
                </div>

                <div className="w-full max-w-[360px]">
                    {/* Heading */}
                    <div className="mb-8">
                        <h1 className="text-[22px] font-semibold tracking-tight text-gray-900 dark:text-gray-100">
                            {title}
                        </h1>
                        {description && (
                            <p className="mt-1.5 text-[13px] text-gray-500 dark:text-gray-400">
                                {description}
                            </p>
                        )}
                    </div>

                    {children}
                </div>
            </div>
        </div>
    );
}
