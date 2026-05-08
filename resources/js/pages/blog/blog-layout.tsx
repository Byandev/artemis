import AppearanceToggleDropdown from '@/components/appearance-dropdown';
import { register } from '@/routes';
import { type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { type PropsWithChildren } from 'react';

export default function BlogLayout({
    children,
    title,
    description,
}: PropsWithChildren<{ title: string; description?: string }>) {
    const { auth } = usePage<SharedData>().props;

    return (
        <>
            <Head title={`${title} | Artemis Blog`}>
                {description && <meta name="description" content={description} />}
            </Head>

            <div className="relative min-h-screen overflow-hidden bg-white text-gray-900 dark:bg-zinc-950 dark:text-gray-100">
                {/* Ambient background */}
                <div className="pointer-events-none absolute -top-60 left-1/2 h-[700px] w-[1100px] -translate-x-1/2 bg-[radial-gradient(ellipse,rgba(16,211,161,0.07),transparent_65%)]" />
                <div className="pointer-events-none absolute inset-0 bg-[linear-gradient(rgba(0,0,0,0.02)_1px,transparent_1px),linear-gradient(90deg,rgba(0,0,0,0.02)_1px,transparent_1px)] bg-[size:60px_60px] dark:bg-[linear-gradient(rgba(255,255,255,0.012)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,0.012)_1px,transparent_1px)]" />

                {/* Nav */}
                <nav className="sticky top-0 z-50 border-b border-gray-200/80 bg-white/80 backdrop-blur-2xl dark:border-white/6 dark:bg-zinc-950/80">
                    <div className="mx-auto flex max-w-[1200px] items-center justify-between px-5 py-4 md:px-10">
                        <Link href="/" className="flex items-center gap-3">
                            <img src="/img/logo/artemis.png" alt="Artemis" className="h-9 w-9 object-contain" />
                            <span className="text-[22px] font-semibold tracking-tight">Artemis</span>
                        </Link>
                        <div className="flex items-center gap-6 text-sm">
                            <Link href="/blog" className="hidden text-[13px] font-medium text-brand-600 transition-colors hover:text-brand-500 dark:text-brand-400 md:block">Blog</Link>
                            <Link href="/#features" className="hidden text-[13px] text-gray-500 transition-colors hover:text-brand-500 dark:text-gray-400 dark:hover:text-brand-400 md:block">Features</Link>
                            <Link href="/rts-calculator" className="hidden text-[13px] text-gray-500 transition-colors hover:text-brand-500 dark:text-gray-400 dark:hover:text-brand-400 md:block">RTS Calculator</Link>
                            <div className="hidden h-4 w-px bg-gray-200 dark:bg-white/10 md:block" />
                            <AppearanceToggleDropdown />
                            {auth.user ? (
                                <Link href="/dashboard" className="inline-flex h-9 items-center rounded-lg bg-brand-500! px-5 text-[13px] font-semibold text-white shadow-sm shadow-brand-500/20 transition-all hover:bg-brand-600 hover:shadow-md hover:shadow-brand-500/25">
                                    Dashboard
                                </Link>
                            ) : (
                                <>
                                    <Link href="/login" className="hidden text-[13px] text-gray-500 transition-colors hover:text-brand-500 dark:text-gray-400 dark:hover:text-brand-400 md:block">Log in</Link>
                                    <Link href={register()} className="inline-flex h-9 items-center rounded-lg bg-brand-500! px-5 text-[13px] font-semibold text-white shadow-sm shadow-brand-500/20 transition-all hover:bg-brand-600 hover:shadow-md hover:shadow-brand-500/25">
                                        Start free
                                    </Link>
                                </>
                            )}
                        </div>
                    </div>
                </nav>

                <div className="relative">{children}</div>

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
                                <Link href="/blog" className="transition-colors hover:text-brand-500">Blog</Link>
                                <Link href="/rts-calculator" className="transition-colors hover:text-brand-500">RTS Calculator</Link>
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

export function BlogPost({
    title,
    date,
    readTime,
    category,
    children,
    description,
}: PropsWithChildren<{
    title: string;
    date: string;
    readTime: string;
    category: string;
    description?: string;
}>) {
    return (
        <BlogLayout title={title} description={description}>
            <article className="px-5 py-16 md:px-10 md:py-24">
                <div className="mx-auto max-w-[720px]">
                    {/* Back link */}
                    <div className="mb-10">
                        <Link href="/blog" className="inline-flex items-center gap-2 font-mono text-[11px] uppercase tracking-wider text-gray-400 transition-colors hover:text-brand-500 dark:text-gray-500">
                            <svg className="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}><path strokeLinecap="round" strokeLinejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" /></svg>
                            All posts
                        </Link>
                    </div>

                    {/* Header */}
                    <header className="mb-12 border-b border-gray-200/80 pb-12 dark:border-white/6">
                        <div className="mb-5 inline-flex items-center gap-2 rounded-full border border-brand-200/60 bg-brand-50/80 px-3 py-1.5 dark:border-brand-500/15 dark:bg-brand-500/8">
                            <span className="h-1.5 w-1.5 rounded-full bg-brand-500" />
                            <span className="font-mono text-[10px] font-semibold uppercase tracking-[0.2em] text-brand-700 dark:text-brand-400">{category}</span>
                        </div>
                        <h1 className="mb-5 text-[clamp(1.75rem,5vw,2.75rem)] font-bold leading-[1.1] tracking-tight">{title}</h1>
                        {description && (
                            <p className="mb-6 max-w-xl text-[17px] leading-relaxed text-gray-500 dark:text-gray-400">{description}</p>
                        )}
                        <div className="flex items-center gap-4">
                            <div className="flex h-9 w-9 items-center justify-center rounded-full bg-brand-50 dark:bg-brand-500/10">
                                <img src="/img/logo/artemis.png" alt="Artemis" className="h-5 w-5 object-contain" />
                            </div>
                            <div>
                                <p className="text-[13px] font-semibold text-gray-900 dark:text-gray-100">Artemis Team</p>
                                <div className="flex items-center gap-2 font-mono text-[10px] uppercase tracking-wider text-gray-400 dark:text-gray-500">
                                    <span>{date}</span>
                                    <span className="text-gray-300 dark:text-gray-600">·</span>
                                    <span>{readTime}</span>
                                </div>
                            </div>
                        </div>
                    </header>

                    {/* Body */}
                    <div className="
                        [&>p]:mb-6 [&>p]:text-[16px] [&>p]:leading-[1.8] [&>p]:text-gray-600 dark:[&>p]:text-gray-400
                        [&>h2]:mb-4 [&>h2]:mt-14 [&>h2]:text-[22px] [&>h2]:font-bold [&>h2]:tracking-tight [&>h2]:text-gray-900 dark:[&>h2]:text-gray-100 sm:[&>h2]:text-[26px]
                        [&>h3]:mb-3 [&>h3]:mt-10 [&>h3]:text-[18px] [&>h3]:font-bold [&>h3]:tracking-tight [&>h3]:text-gray-900 dark:[&>h3]:text-gray-100
                        [&>ul]:mb-6 [&>ul]:space-y-3 [&>ul]:pl-0
                        [&>ul>li]:relative [&>ul>li]:pl-6 [&>ul>li]:text-[16px] [&>ul>li]:leading-[1.8] [&>ul>li]:text-gray-600 dark:[&>ul>li]:text-gray-400
                        [&>ul>li]:before:absolute [&>ul>li]:before:left-0 [&>ul>li]:before:top-[11px] [&>ul>li]:before:h-1.5 [&>ul>li]:before:w-1.5 [&>ul>li]:before:rounded-full [&>ul>li]:before:bg-brand-500
                        [&_strong]:font-semibold [&_strong]:text-gray-900 dark:[&_strong]:text-gray-200
                    ">
                        {children}
                    </div>

                    {/* Divider */}
                    <div className="my-16 flex items-center gap-4">
                        <div className="h-px flex-1 bg-gray-200/80 dark:bg-white/6" />
                        <img src="/img/logo/artemis.png" alt="" className="h-6 w-6 object-contain opacity-30" />
                        <div className="h-px flex-1 bg-gray-200/80 dark:bg-white/6" />
                    </div>

                    {/* CTA */}
                    <div className="relative overflow-hidden rounded-2xl border-2 border-brand-500/60 shadow-xl shadow-brand-500/5 dark:border-brand-500/40">
                        <div className="pointer-events-none absolute -right-16 -top-16 h-40 w-40 rounded-full bg-brand-500/10 blur-3xl" />
                        <div className="pointer-events-none absolute -bottom-10 -left-10 h-32 w-32 rounded-full bg-brand-400/8 blur-3xl" />
                        <div className="relative bg-gradient-to-b from-brand-50/90 to-white px-6 py-10 text-center dark:from-brand-500/[0.06] dark:to-zinc-900 sm:px-10 sm:py-12">
                            <div className="mb-4 inline-flex items-center gap-2 rounded-full border border-brand-300 bg-brand-50 px-3 py-1 dark:border-brand-500/30 dark:bg-brand-500/10">
                                <span className="font-mono text-[10px] font-semibold uppercase tracking-[0.2em] text-brand-600 dark:text-brand-400">14-day free trial</span>
                            </div>
                            <h3 className="mb-3 text-xl font-bold tracking-tight sm:text-2xl">Ready to see your numbers?</h3>
                            <p className="mx-auto mb-8 max-w-md text-[14px] leading-relaxed text-gray-500 dark:text-gray-400">
                                Connect 1 Pancake page, get 1 month of data. Parcel Journey tracking via chat. No credit card required.
                            </p>
                            <div className="flex flex-col items-center justify-center gap-3 sm:flex-row">
                                <Link
                                    href="/register"
                                    className="inline-flex h-11 w-full items-center justify-center gap-2 rounded-xl bg-brand-500! px-6 text-[14px] font-semibold text-white shadow-lg shadow-brand-500/20 transition-all hover:-translate-y-0.5 hover:bg-brand-600 sm:w-auto"
                                >
                                    Start free trial
                                    <svg width="14" height="10" viewBox="0 0 16 12" fill="none"><path d="M1 6h14m0 0L10 1m5 5l-5 5" stroke="currentColor" strokeWidth="2" strokeLinecap="round" /></svg>
                                </Link>
                                <Link
                                    href="/rts-calculator"
                                    className="inline-flex h-11 w-full items-center justify-center rounded-xl border border-gray-200 bg-white px-6 text-[14px] font-semibold text-gray-700 transition-all hover:border-brand-300 hover:text-brand-600 sm:w-auto dark:border-white/10 dark:bg-zinc-900 dark:text-gray-300"
                                >
                                    Calculate your RTS bleed
                                </Link>
                            </div>
                        </div>
                    </div>

                    {/* Back */}
                    <div className="mt-12 text-center">
                        <Link href="/blog" className="inline-flex items-center gap-2 text-[13px] font-medium text-gray-400 transition-colors hover:text-brand-500 dark:text-gray-500">
                            <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}><path strokeLinecap="round" strokeLinejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" /></svg>
                            Back to all posts
                        </Link>
                    </div>
                </div>
            </article>
        </BlogLayout>
    );
}
