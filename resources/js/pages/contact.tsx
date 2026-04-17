import AppearanceToggleDropdown from '@/components/appearance-dropdown';
import { register } from '@/routes';
import { type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { useState } from 'react';

export default function Contact() {
    const { auth } = usePage<SharedData>().props;
    const [submitted, setSubmitted] = useState(false);
    const [form, setForm] = useState({ name: '', email: '', message: '' });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setSubmitted(true);
    };

    return (
        <>
            <Head title="Contact — Artemis">
                <meta name="description" content="Get in touch with the Artemis team. Questions about the platform, partnerships, or support — we'd love to hear from you." />
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

                {/* Content */}
                <section className="relative px-5 py-16 md:px-10 md:py-24">
                    <div className="mx-auto grid max-w-[1200px] items-start gap-12 lg:grid-cols-[1fr_1fr] lg:gap-20">

                        {/* Left — Info */}
                        <div className="lg:py-8">
                            <div className="mb-6 inline-flex items-center gap-2.5 rounded-full border border-brand-200/60 bg-brand-50/80 px-4 py-2 dark:border-brand-500/15 dark:bg-brand-500/8">
                                <span className="h-1.5 w-1.5 rounded-full bg-brand-500 shadow-[0_0_6px_var(--color-brand-500)]" />
                                <span className="font-mono text-[10px] font-semibold uppercase tracking-[0.2em] text-brand-700 dark:text-brand-400">Get in touch</span>
                            </div>
                            <h1 className="mb-5 text-[clamp(2rem,5vw,3rem)] font-bold leading-tight tracking-tight">
                                We'd love to{' '}
                                <span className="bg-gradient-to-br from-brand-500 to-brand-700 bg-clip-text italic text-transparent">hear from you.</span>
                            </h1>
                            <p className="mb-10 max-w-md text-base leading-relaxed text-gray-500 sm:text-lg dark:text-gray-400">
                                Questions about Artemis, partnership inquiries, or just want to chat about COD e-commerce? Reach out — we respond within 24 hours.
                            </p>

                            {/* Contact info cards */}
                            <div className="space-y-4">
                                <div className="flex items-start gap-4 rounded-2xl border border-gray-200/80 bg-white p-5 dark:border-white/6 dark:bg-zinc-900/80">
                                    <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-brand-200/60 bg-brand-50/80 dark:border-brand-500/20 dark:bg-brand-500/10">
                                        <svg className="h-5 w-5 text-brand-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
                                            <path strokeLinecap="round" strokeLinejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
                                        </svg>
                                    </div>
                                    <div>
                                        <p className="mb-0.5 font-mono text-[10px] uppercase tracking-wider text-gray-400 dark:text-gray-500">Email</p>
                                        <a href="mailto:hello@artemis.ph" className="text-[15px] font-semibold text-gray-900 transition-colors hover:text-brand-500 dark:text-gray-100">hello@artemis.ph</a>
                                    </div>
                                </div>

                                <div className="flex items-start gap-4 rounded-2xl border border-gray-200/80 bg-white p-5 dark:border-white/6 dark:bg-zinc-900/80">
                                    <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-brand-200/60 bg-brand-50/80 dark:border-brand-500/20 dark:bg-brand-500/10">
                                        <svg className="h-5 w-5 text-brand-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
                                            <path strokeLinecap="round" strokeLinejoin="round" d="M20.25 8.511c.884.284 1.5 1.128 1.5 2.097v4.286c0 1.136-.847 2.1-1.98 2.193-.34.027-.68.052-1.02.072v3.091l-3-3c-1.354 0-2.694-.055-4.02-.163a2.115 2.115 0 01-.825-.242m9.345-8.334a2.126 2.126 0 00-.476-.095 48.64 48.64 0 00-8.048 0c-1.131.094-1.976 1.057-1.976 2.192v4.286c0 .837.46 1.58 1.155 1.951m9.345-8.334V6.637c0-1.621-1.152-3.026-2.76-3.235A48.455 48.455 0 0011.25 3c-2.115 0-4.198.137-6.24.402-1.608.209-2.76 1.614-2.76 3.235v6.226c0 1.621 1.152 3.026 2.76 3.235.577.075 1.157.14 1.74.194V21l4.155-4.155" />
                                        </svg>
                                    </div>
                                    <div>
                                        <p className="mb-0.5 font-mono text-[10px] uppercase tracking-wider text-gray-400 dark:text-gray-500">Chat support</p>
                                        <p className="text-[15px] font-semibold text-gray-900 dark:text-gray-100">Available for active users</p>
                                        <p className="mt-0.5 text-[13px] text-gray-400 dark:text-gray-500">Via Messenger during business hours</p>
                                    </div>
                                </div>

                                <div className="flex items-start gap-4 rounded-2xl border border-gray-200/80 bg-white p-5 dark:border-white/6 dark:bg-zinc-900/80">
                                    <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-brand-200/60 bg-brand-50/80 dark:border-brand-500/20 dark:bg-brand-500/10">
                                        <svg className="h-5 w-5 text-brand-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
                                            <path strokeLinecap="round" strokeLinejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path strokeLinecap="round" strokeLinejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" />
                                        </svg>
                                    </div>
                                    <div>
                                        <p className="mb-0.5 font-mono text-[10px] uppercase tracking-wider text-gray-400 dark:text-gray-500">Location</p>
                                        <p className="text-[15px] font-semibold text-gray-900 dark:text-gray-100">Philippines</p>
                                        <p className="mt-0.5 text-[13px] text-gray-400 dark:text-gray-500">Remote-first team, serving PH sellers</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {/* Right — Form */}
                        <div className="overflow-hidden rounded-2xl border border-black/6 bg-white shadow-sm dark:border-white/6 dark:bg-zinc-900/80">
                            <div className="border-b border-black/4 bg-stone-50/80 px-6 py-5 dark:border-white/5 dark:bg-white/[0.02] sm:px-8">
                                <h2 className="text-[14px] font-bold tracking-tight text-gray-900 dark:text-gray-100">Send us a message</h2>
                                <p className="mt-1 font-mono text-[10px] uppercase tracking-wider text-gray-400 dark:text-gray-500">We'll get back to you within 24 hours</p>
                            </div>

                            {submitted ? (
                                <div className="px-6 py-16 text-center sm:px-8">
                                    <div className="mx-auto mb-5 flex h-14 w-14 items-center justify-center rounded-full border border-brand-200 bg-brand-50 dark:border-brand-500/20 dark:bg-brand-500/10">
                                        <svg className="h-7 w-7 text-brand-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                            <path strokeLinecap="round" strokeLinejoin="round" d="M5 13l4 4L19 7" />
                                        </svg>
                                    </div>
                                    <h3 className="mb-2 text-lg font-bold tracking-tight">Message sent!</h3>
                                    <p className="text-[14px] text-gray-500 dark:text-gray-400">
                                        Salamat! We'll get back to you as soon as possible.
                                    </p>
                                </div>
                            ) : (
                                <form onSubmit={handleSubmit} className="space-y-5 px-6 py-6 sm:px-8">
                                    <div className="space-y-1.5">
                                        <label htmlFor="name" className="block font-mono text-[10px] font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500">
                                            Name <span className="text-red-400">*</span>
                                        </label>
                                        <input
                                            id="name"
                                            type="text"
                                            required
                                            value={form.name}
                                            onChange={(e) => setForm({ ...form, name: e.target.value })}
                                            placeholder="Your name"
                                            className="h-10 w-full rounded-[10px] border border-black/8 bg-stone-50 px-3 font-mono! text-[13px]! text-gray-800 placeholder:text-gray-300 outline-none transition-all focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-100 dark:placeholder:text-gray-600 dark:focus:border-emerald-400"
                                        />
                                    </div>

                                    <div className="space-y-1.5">
                                        <label htmlFor="email" className="block font-mono text-[10px] font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500">
                                            Email <span className="text-red-400">*</span>
                                        </label>
                                        <input
                                            id="email"
                                            type="email"
                                            required
                                            value={form.email}
                                            onChange={(e) => setForm({ ...form, email: e.target.value })}
                                            placeholder="you@company.com"
                                            className="h-10 w-full rounded-[10px] border border-black/8 bg-stone-50 px-3 font-mono! text-[13px]! text-gray-800 placeholder:text-gray-300 outline-none transition-all focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-100 dark:placeholder:text-gray-600 dark:focus:border-emerald-400"
                                        />
                                    </div>

                                    <div className="space-y-1.5">
                                        <label htmlFor="message" className="block font-mono text-[10px] font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500">
                                            Message <span className="text-red-400">*</span>
                                        </label>
                                        <textarea
                                            id="message"
                                            required
                                            rows={5}
                                            value={form.message}
                                            onChange={(e) => setForm({ ...form, message: e.target.value })}
                                            placeholder="How can we help?"
                                            className="w-full resize-none rounded-[10px] border border-black/8 bg-stone-50 px-3 py-2.5 font-mono! text-[13px]! text-gray-800 placeholder:text-gray-300 outline-none transition-all focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/8 dark:bg-zinc-800 dark:text-gray-100 dark:placeholder:text-gray-600 dark:focus:border-emerald-400"
                                        />
                                    </div>

                                    <button
                                        type="submit"
                                        className="mt-1 flex h-10 w-full items-center justify-center gap-2 rounded-[10px] bg-emerald-600 font-mono! text-[13px]! font-semibold text-white transition-all hover:bg-emerald-700"
                                    >
                                        Send message
                                    </button>

                                    <p className="text-center font-mono text-[10px] text-gray-400 dark:text-gray-500">
                                        Or email us directly at hello@artemis.ph
                                    </p>
                                </form>
                            )}
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
                                <Link href="/about" className="transition-colors hover:text-brand-500">About</Link>
                                <Link href="/blog" className="transition-colors hover:text-brand-500">Blog</Link>
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
