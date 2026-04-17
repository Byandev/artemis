import AppearanceToggleDropdown from '@/components/appearance-dropdown';
import { register } from '@/routes';
import { type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';

export default function About() {
    const { auth } = usePage<SharedData>().props;

    return (
        <>
            <Head title="About — Artemis">
                <meta name="description" content="Artemis is the 1st analytics and automation platform built for Philippine COD e-commerce. Learn about our mission to help sellers cut RTS and protect their margin." />
            </Head>

            <div className="relative min-h-screen overflow-hidden bg-white text-gray-900 dark:bg-zinc-950 dark:text-gray-100">
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
                            <Link href="/#features" className="hidden text-[13px] text-gray-500 transition-colors hover:text-brand-500 dark:text-gray-400 dark:hover:text-brand-400 md:block">Features</Link>
                            <Link href="/blog" className="hidden text-[13px] text-gray-500 transition-colors hover:text-brand-500 dark:text-gray-400 dark:hover:text-brand-400 md:block">Blog</Link>
                            <Link href="/rts-calculator" className="hidden text-[13px] text-gray-500 transition-colors hover:text-brand-500 dark:text-gray-400 dark:hover:text-brand-400 md:block">RTS Calculator</Link>
                            <div className="hidden h-4 w-px bg-gray-200 dark:bg-white/10 md:block" />
                            <AppearanceToggleDropdown />
                            {auth.user ? (
                                <Link href="/dashboard" className="inline-flex h-9 items-center rounded-lg bg-brand-500! px-5 text-[13px] font-semibold text-white shadow-sm shadow-brand-500/20 transition-all hover:bg-brand-600 hover:shadow-md hover:shadow-brand-500/25">Dashboard</Link>
                            ) : (
                                <>
                                    <Link href="/login" className="hidden text-[13px] text-gray-500 transition-colors hover:text-brand-500 dark:text-gray-400 dark:hover:text-brand-400 md:block">Log in</Link>
                                    <Link href={register()} className="inline-flex h-9 items-center rounded-lg bg-brand-500! px-5 text-[13px] font-semibold text-white shadow-sm shadow-brand-500/20 transition-all hover:bg-brand-600 hover:shadow-md hover:shadow-brand-500/25">Start free</Link>
                                </>
                            )}
                        </div>
                    </div>
                </nav>

                {/* Hero */}
                <section className="relative px-5 pb-12 pt-20 md:px-10 md:pt-28">
                    <div className="mx-auto max-w-[720px] text-center">
                        <div className="mb-6 inline-flex items-center gap-2.5 rounded-full border border-brand-200/60 bg-brand-50/80 px-4 py-2 dark:border-brand-500/15 dark:bg-brand-500/8">
                            <span className="h-1.5 w-1.5 rounded-full bg-brand-500 shadow-[0_0_6px_var(--color-brand-500)]" />
                            <span className="font-mono text-[10px] font-semibold uppercase tracking-[0.2em] text-brand-700 dark:text-brand-400">About Artemis</span>
                        </div>
                        <h1 className="mb-6 text-[clamp(2rem,5vw,3.25rem)] font-bold leading-tight tracking-tight">
                            Built in the Philippines,{' '}
                            <span className="bg-gradient-to-br from-brand-500 to-brand-700 bg-clip-text italic text-transparent">for the Philippines.</span>
                        </h1>
                        <p className="mx-auto max-w-xl text-base leading-relaxed text-gray-500 sm:text-lg dark:text-gray-400">
                            We're building the tools that Philippine COD sellers need to stop guessing and start growing — with real data, real automation, and real results.
                        </p>
                    </div>
                </section>

                {/* Mission */}
                <section className="px-5 py-16 md:px-10 md:py-24">
                    <div className="mx-auto max-w-[720px]">
                        <div className="mb-16 border-b border-gray-200/80 pb-16 dark:border-white/6">
                            <p className="mb-4 font-mono text-[10px] uppercase tracking-[0.2em] text-brand-500">Our mission</p>
                            <h2 className="mb-6 text-2xl font-bold tracking-tight sm:text-3xl">
                                Every COD seller deserves to know where their money goes.
                            </h2>
                            <div className="space-y-5 text-[16px] leading-[1.8] text-gray-600 dark:text-gray-400">
                                <p>
                                    The Philippine COD e-commerce market is massive — billions of pesos flowing through Facebook pages, TikTok shops, and Shopee stores every month. But behind the revenue numbers, most sellers are losing 20-40% of their shipments to RTS.
                                </p>
                                <p>
                                    That's not a minor inefficiency. It's a structural problem — and until now, there hasn't been a platform built specifically to solve it for Filipino sellers.
                                </p>
                                <p>
                                    Artemis is the <strong className="text-gray-900 dark:text-gray-200">1st analytics and automation platform</strong> purpose-built for Philippine COD e-commerce. We connect to Pancake POS, pull your data automatically, and give you the visibility and tools to cut RTS, improve delivery success, and keep more of every peso you earn.
                                </p>
                            </div>
                        </div>

                        {/* What we believe */}
                        <div className="mb-16 border-b border-gray-200/80 pb-16 dark:border-white/6">
                            <p className="mb-4 font-mono text-[10px] uppercase tracking-[0.2em] text-brand-500">What we believe</p>
                            <div className="grid gap-8 sm:grid-cols-2">
                                {[
                                    { title: 'Data over gut feel', desc: 'Every decision should be backed by real numbers. We make data accessible — not complicated.' },
                                    { title: 'Built for COD', desc: 'We don\'t adapt general e-commerce tools to COD. We build from the ground up for how Filipino COD sellers actually operate.' },
                                    { title: 'Visibility first', desc: 'You can\'t fix what you can\'t see. Before automation, before optimization — you need to know your numbers.' },
                                    { title: 'Progress, not perfection', desc: 'Moving your RTS from 30% to 25% is worth more than chasing 0%. We help you improve steadily, month over month.' },
                                ].map((v) => (
                                    <div key={v.title}>
                                        <h3 className="mb-2 text-[15px] font-bold text-gray-900 dark:text-gray-100">{v.title}</h3>
                                        <p className="text-[14px] leading-relaxed text-gray-500 dark:text-gray-400">{v.desc}</p>
                                    </div>
                                ))}
                            </div>
                        </div>

                        {/* Why Artemis */}
                        <div>
                            <p className="mb-4 font-mono text-[10px] uppercase tracking-[0.2em] text-brand-500">Why "Artemis"?</p>
                            <h2 className="mb-6 text-2xl font-bold tracking-tight sm:text-3xl">
                                The goddess of the hunt.
                            </h2>
                            <div className="space-y-5 text-[16px] leading-[1.8] text-gray-600 dark:text-gray-400">
                                <p>
                                    In Greek mythology, Artemis was the goddess of the hunt — precise, focused, and relentless. We named our platform after her because that's exactly what we help sellers do: <strong className="text-gray-900 dark:text-gray-200">hunt down RTS</strong> with precision, and protect their margin with data.
                                </p>
                                <p>
                                    Every failed parcel is a target. Every percentage point of RTS reduced is a win. Artemis gives you the tools to find the leaks, fix them, and keep your business growing.
                                </p>
                            </div>
                        </div>
                    </div>
                </section>

                {/* CTA */}
                <section className="relative overflow-hidden py-20 md:py-28">
                    <div className="pointer-events-none absolute left-1/2 top-1/2 h-[500px] w-[700px] -translate-x-1/2 -translate-y-1/2 bg-[radial-gradient(ellipse,rgba(16,211,161,0.1),transparent_55%)]" />
                    <div className="relative mx-auto max-w-[1200px] px-5 text-center md:px-10">
                        <h2 className="mb-5 text-3xl font-bold tracking-tight sm:text-4xl">
                            Ready to see your numbers?
                        </h2>
                        <p className="mx-auto mb-8 max-w-md text-[15px] leading-relaxed text-gray-500 dark:text-gray-400">
                            14-day free trial. Connect 1 Pancake page, get 1 month of data. No credit card required.
                        </p>
                        <div className="flex flex-col items-center justify-center gap-3 sm:flex-row">
                            <Link href={auth.user ? '/dashboard' : register()} className="inline-flex h-12 w-full items-center justify-center gap-2.5 rounded-xl bg-brand-500! px-8 text-base font-semibold text-white shadow-lg shadow-brand-500/20 transition-all hover:-translate-y-0.5 hover:bg-brand-600 sm:w-auto">
                                {auth.user ? 'Go to Dashboard' : 'Start free trial'}
                                <svg width="16" height="12" viewBox="0 0 16 12" fill="none"><path d="M1 6h14m0 0L10 1m5 5l-5 5" stroke="currentColor" strokeWidth="2" strokeLinecap="round" /></svg>
                            </Link>
                            <Link href="/contact" className="inline-flex h-12 w-full items-center justify-center rounded-xl border border-gray-200 bg-white px-8 text-base font-semibold text-gray-700 transition-all hover:border-brand-300 hover:text-brand-600 sm:w-auto dark:border-white/10 dark:bg-zinc-900 dark:text-gray-300 dark:hover:border-brand-500/40 dark:hover:text-brand-400">
                                Get in touch
                            </Link>
                        </div>
                    </div>
                </section>

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
