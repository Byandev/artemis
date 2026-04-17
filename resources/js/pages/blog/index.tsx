import { Link } from '@inertiajs/react';
import BlogLayout from './blog-layout';

const posts = [
    {
        slug: 'what-is-rts-and-why-it-matters',
        title: 'What is RTS and Why It\'s Killing Your COD Business',
        description: 'Return-to-sender isn\'t just a logistics problem — it\'s the single biggest profit leak in Philippine COD e-commerce. Here\'s what every seller needs to understand.',
        category: 'Fundamentals',
        date: 'April 15, 2026',
        readTime: '5 min read',
    },
    {
        slug: 'hidden-cost-of-every-failed-delivery',
        title: 'The Hidden Cost of Every Failed Delivery',
        description: 'Most sellers think RTS only costs them the shipping fee. The real number is 3-5x higher. We break down every peso you\'re actually losing.',
        category: 'Analytics',
        date: 'April 12, 2026',
        readTime: '4 min read',
    },
    {
        slug: 'how-parcel-journey-tracking-reduces-rts',
        title: 'How Parcel Journey Tracking Reduces RTS',
        description: 'When customers know where their parcel is, they\'re more likely to be home for delivery. Here\'s how notification-based tracking cuts return rates.',
        category: 'Product',
        date: 'April 10, 2026',
        readTime: '4 min read',
    },
    {
        slug: '5-signs-your-cod-business-is-bleeding-money',
        title: '5 Signs Your COD Business is Bleeding Money',
        description: 'You might be profitable on paper but losing thousands every month. These are the red flags most PH COD sellers miss — until it\'s too late.',
        category: 'Operations',
        date: 'April 8, 2026',
        readTime: '6 min read',
    },
    {
        slug: 'why-ph-cod-sellers-need-analytics',
        title: 'Why Philippine COD Sellers Need Analytics in 2026',
        description: 'The COD market is getting more competitive. Sellers who track their numbers will survive. Sellers who don\'t will keep guessing — and losing.',
        category: 'Industry',
        date: 'April 5, 2026',
        readTime: '5 min read',
    },
];

export default function BlogIndex() {
    return (
        <BlogLayout title="Blog" description="Insights, guides, and strategies for Philippine COD e-commerce sellers.">
            <section className="px-5 py-16 md:px-10 md:py-24">
                <div className="mx-auto max-w-[1200px]">
                    {/* Hero */}
                    <div className="mb-16 text-center md:mb-20">
                        <div className="mb-6 inline-flex items-center gap-2.5 rounded-full border border-brand-200/60 bg-brand-50/80 px-4 py-2 dark:border-brand-500/15 dark:bg-brand-500/8">
                            <span className="h-1.5 w-1.5 rounded-full bg-brand-500 shadow-[0_0_6px_var(--color-brand-500)]" />
                            <span className="font-mono text-[10px] font-semibold uppercase tracking-[0.2em] text-brand-700 dark:text-brand-400">Artemis Blog</span>
                        </div>
                        <h1 className="mb-5 text-4xl font-bold tracking-tight md:text-5xl lg:text-6xl">
                            Insights for{' '}
                            <span className="bg-gradient-to-br from-brand-500 to-brand-700 bg-clip-text italic text-transparent">COD sellers.</span>
                        </h1>
                        <p className="mx-auto max-w-lg text-base leading-relaxed text-gray-500 sm:text-lg dark:text-gray-400">
                            Strategies, data, and practical guides to help you cut RTS and protect your margin.
                        </p>
                    </div>

                    {/* Featured post */}
                    <Link
                        href={`/blog/${posts[0].slug}`}
                        className="group relative mb-8 block overflow-hidden rounded-2xl border border-gray-200/80 bg-white shadow-sm transition-all hover:-translate-y-1 hover:border-brand-300 hover:shadow-lg hover:shadow-brand-500/5 dark:border-white/6 dark:bg-zinc-900/80 dark:hover:border-brand-500/30"
                    >
                        <div className="absolute left-0 right-0 top-0 h-0.5 bg-gradient-to-r from-brand-400 to-brand-600 opacity-0 transition-opacity group-hover:opacity-100" />
                        <div className="p-8 sm:p-10 md:p-12">
                            <div className="mb-5 flex flex-wrap items-center gap-3">
                                <span className="inline-flex items-center gap-1.5 rounded-full border border-brand-200/60 bg-brand-50/80 px-3 py-1 font-mono text-[10px] font-semibold uppercase tracking-[0.15em] text-brand-700 dark:border-brand-500/15 dark:bg-brand-500/8 dark:text-brand-400">
                                    <span className="h-1 w-1 rounded-full bg-brand-500" />
                                    {posts[0].category}
                                </span>
                                <span className="font-mono text-[10px] uppercase tracking-wider text-gray-400 dark:text-gray-500">{posts[0].readTime}</span>
                                <span className="font-mono text-[10px] uppercase tracking-wider text-gray-400 dark:text-gray-500">{posts[0].date}</span>
                            </div>
                            <h2 className="mb-4 max-w-2xl text-2xl font-bold tracking-tight transition-colors group-hover:text-brand-600 sm:text-3xl md:text-4xl dark:group-hover:text-brand-400">
                                {posts[0].title}
                            </h2>
                            <p className="mb-6 max-w-2xl text-[15px] leading-relaxed text-gray-500 sm:text-[16px] dark:text-gray-400">
                                {posts[0].description}
                            </p>
                            <span className="inline-flex items-center gap-2 text-[13px] font-semibold text-brand-500 transition-colors group-hover:text-brand-600">
                                Read article
                                <svg className="h-3.5 w-3.5 transition-transform group-hover:translate-x-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}><path strokeLinecap="round" strokeLinejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" /></svg>
                            </span>
                        </div>
                    </Link>

                    {/* Remaining posts */}
                    <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                        {posts.slice(1).map((post) => (
                            <Link
                                key={post.slug}
                                href={`/blog/${post.slug}`}
                                className="group relative flex flex-col overflow-hidden rounded-2xl border border-gray-200/80 bg-white p-6 shadow-sm transition-all hover:-translate-y-1 hover:border-brand-300 hover:shadow-lg hover:shadow-brand-500/5 dark:border-white/6 dark:bg-zinc-900/80 dark:hover:border-brand-500/30"
                            >
                                <div className="absolute left-0 right-0 top-0 h-0.5 bg-gradient-to-r from-brand-400 to-brand-600 opacity-0 transition-opacity group-hover:opacity-100" />
                                <div className="mb-4 flex items-center gap-2.5">
                                    <span className="inline-flex items-center rounded-full border border-brand-200/60 bg-brand-50/80 px-2.5 py-0.5 font-mono text-[9px] font-semibold uppercase tracking-[0.15em] text-brand-700 dark:border-brand-500/15 dark:bg-brand-500/8 dark:text-brand-400">
                                        {post.category}
                                    </span>
                                    <span className="font-mono text-[9px] uppercase tracking-wider text-gray-400 dark:text-gray-500">{post.readTime}</span>
                                </div>
                                <h2 className="mb-3 text-[15px] font-bold leading-snug tracking-tight text-gray-900 transition-colors group-hover:text-brand-600 sm:text-[16px] dark:text-gray-100 dark:group-hover:text-brand-400">
                                    {post.title}
                                </h2>
                                <p className="mb-5 flex-1 text-[13px] leading-relaxed text-gray-500 dark:text-gray-400">
                                    {post.description}
                                </p>
                                <div className="mt-auto flex items-center justify-between border-t border-gray-100 pt-4 dark:border-white/5">
                                    <span className="font-mono text-[9px] uppercase tracking-wider text-gray-400 dark:text-gray-500">{post.date}</span>
                                    <span className="text-[12px] font-semibold text-brand-500 transition-colors group-hover:text-brand-600">
                                        Read →
                                    </span>
                                </div>
                            </Link>
                        ))}
                    </div>
                </div>
            </section>
        </BlogLayout>
    );
}
