import AppearanceToggleDropdown from '@/components/appearance-dropdown';
import { register } from '@/routes';
import { type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { type PropsWithChildren } from 'react';

export default function LegalLayout({
    children,
    title,
    lastUpdated,
    description,
}: PropsWithChildren<{ title: string; lastUpdated: string; description?: string }>) {
    const { auth } = usePage<SharedData>().props;

    return (
        <>
            <Head title={`${title} — Artemis`}>
                {description && <meta name="description" content={description} />}
            </Head>

            <div className="relative min-h-screen overflow-hidden bg-white text-gray-900 dark:bg-zinc-950 dark:text-gray-100">
                <div className="pointer-events-none absolute -top-60 left-1/2 h-[700px] w-[1100px] -translate-x-1/2 bg-[radial-gradient(ellipse,rgba(16,211,161,0.05),transparent_65%)]" />
                <div className="pointer-events-none absolute inset-0 bg-[linear-gradient(rgba(0,0,0,0.02)_1px,transparent_1px),linear-gradient(90deg,rgba(0,0,0,0.02)_1px,transparent_1px)] bg-[size:60px_60px] dark:bg-[linear-gradient(rgba(255,255,255,0.012)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,0.012)_1px,transparent_1px)]" />

                {/* Nav */}
                <nav className="sticky top-0 z-50 border-b border-gray-200/80 bg-white/80 backdrop-blur-2xl dark:border-white/6 dark:bg-zinc-950/80">
                    <div className="mx-auto flex max-w-[1200px] items-center justify-between px-5 py-4 md:px-10">
                        <Link href="/" className="flex items-center gap-3">
                            <img src="/img/logo/artemis.png" alt="Artemis" className="h-9 w-9 object-contain" />
                            <span className="text-[22px] font-semibold tracking-tight">Artemis</span>
                        </Link>
                        <div className="flex items-center gap-6 text-sm">
                            <Link href="/" className="hidden text-[13px] text-gray-500 transition-colors hover:text-brand-500 dark:text-gray-400 dark:hover:text-brand-400 md:block">Home</Link>
                            <Link href="/blog" className="hidden text-[13px] text-gray-500 transition-colors hover:text-brand-500 dark:text-gray-400 dark:hover:text-brand-400 md:block">Blog</Link>
                            <div className="hidden h-4 w-px bg-gray-200 dark:bg-white/10 md:block" />
                            <AppearanceToggleDropdown />
                            {auth.user ? (
                                <Link href="/dashboard" className="inline-flex h-9 items-center rounded-lg bg-brand-500! px-5 text-[13px] font-semibold text-white shadow-sm shadow-brand-500/20 transition-all hover:bg-brand-600 hover:shadow-md hover:shadow-brand-500/25">Dashboard</Link>
                            ) : (
                                <Link href={register()} className="inline-flex h-9 items-center rounded-lg bg-brand-500! px-5 text-[13px] font-semibold text-white shadow-sm shadow-brand-500/20 transition-all hover:bg-brand-600 hover:shadow-md hover:shadow-brand-500/25">Start free</Link>
                            )}
                        </div>
                    </div>
                </nav>

                {/* Content */}
                <article className="relative px-5 py-16 md:px-10 md:py-24">
                    <div className="mx-auto max-w-[720px]">
                        <header className="mb-12 border-b border-gray-200/80 pb-12 dark:border-white/6">
                            <p className="mb-4 font-mono text-[10px] uppercase tracking-[0.2em] text-brand-500">Legal</p>
                            <h1 className="mb-4 text-3xl font-bold tracking-tight sm:text-4xl">{title}</h1>
                            <p className="font-mono text-[11px] uppercase tracking-wider text-gray-400 dark:text-gray-500">
                                Last updated: {lastUpdated}
                            </p>
                        </header>

                        <div className="
                            [&>p]:mb-5 [&>p]:text-[15px] [&>p]:leading-[1.8] [&>p]:text-gray-600 dark:[&>p]:text-gray-400
                            [&>h2]:mb-4 [&>h2]:mt-12 [&>h2]:text-xl [&>h2]:font-bold [&>h2]:tracking-tight [&>h2]:text-gray-900 dark:[&>h2]:text-gray-100 sm:[&>h2]:text-2xl
                            [&>h3]:mb-3 [&>h3]:mt-8 [&>h3]:text-lg [&>h3]:font-bold [&>h3]:tracking-tight [&>h3]:text-gray-900 dark:[&>h3]:text-gray-100
                            [&>ul]:mb-5 [&>ul]:space-y-2 [&>ul]:pl-0
                            [&>ul>li]:relative [&>ul>li]:pl-5 [&>ul>li]:text-[15px] [&>ul>li]:leading-[1.8] [&>ul>li]:text-gray-600 dark:[&>ul>li]:text-gray-400
                            [&>ul>li]:before:absolute [&>ul>li]:before:left-0 [&>ul>li]:before:top-[11px] [&>ul>li]:before:h-1.5 [&>ul>li]:before:w-1.5 [&>ul>li]:before:rounded-full [&>ul>li]:before:bg-gray-300 dark:[&>ul>li]:before:bg-gray-600
                            [&_strong]:font-semibold [&_strong]:text-gray-900 dark:[&_strong]:text-gray-200
                        ">
                            {children}
                        </div>

                        {/* Related legal pages */}
                        <div className="mt-16 border-t border-gray-200/80 pt-8 dark:border-white/6">
                            <p className="mb-4 font-mono text-[10px] uppercase tracking-wider text-gray-400 dark:text-gray-500">Other legal pages</p>
                            <div className="flex flex-wrap gap-3">
                                {[
                                    { href: '/privacy', label: 'Privacy Policy' },
                                    { href: '/terms', label: 'Terms of Service' },
                                    { href: '/data-policy', label: 'Data Policy' },
                                    { href: '/security', label: 'Security' },
                                ].map((link) => (
                                    <Link
                                        key={link.href}
                                        href={link.href}
                                        className="inline-flex items-center rounded-lg border border-gray-200 bg-white px-3.5 py-2 text-[13px] font-medium text-gray-600 transition-all hover:border-brand-300 hover:text-brand-600 dark:border-white/8 dark:bg-zinc-900 dark:text-gray-400 dark:hover:border-brand-500/30 dark:hover:text-brand-400"
                                    >
                                        {link.label}
                                    </Link>
                                ))}
                            </div>
                        </div>
                    </div>
                </article>

                {/* Footer */}
                <footer className="border-t border-gray-200/80 dark:border-white/6">
                    <div className="mx-auto max-w-[1200px] px-5 py-12 md:px-10">
                        <div className="flex flex-col items-center justify-between gap-5 sm:flex-row">
                            <Link href="/" className="flex items-center gap-2.5">
                                <img src="/img/logo/artemis.png" alt="Artemis" className="h-7 w-7 object-contain" />
                                <span className="text-lg font-semibold tracking-tight">Artemis</span>
                            </Link>
                            <div className="flex items-center gap-6 text-[12px] text-gray-400 dark:text-gray-500">
                                <Link href="/" className="transition-colors hover:text-brand-500">Home</Link>
                                <Link href="/privacy" className="transition-colors hover:text-brand-500">Privacy</Link>
                                <Link href="/terms" className="transition-colors hover:text-brand-500">Terms</Link>
                                <Link href="/contact" className="transition-colors hover:text-brand-500">Contact</Link>
                            </div>
                            <p className="font-mono text-[11px] tracking-[0.08em] text-gray-400 dark:text-gray-600">
                                &copy; {new Date().getFullYear()} Artemis
                            </p>
                        </div>
                    </div>
                </footer>
            </div>
        </>
    );
}
