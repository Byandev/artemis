import { Head, Link } from '@inertiajs/react';
import {
    SidebarProvider,
    SidebarInset,
    SidebarTrigger,
} from '@/components/ui/sidebar';
import { AdminSidebar } from '@/components/admin-sidebar';

import {
    ChevronLeft,
    Calendar,
    User,
    Edit3,
    FileText,
} from 'lucide-react';

interface Post {
    id: number;
    title: string;
    excerpt: string;
    content: string;
    status: string;
    slug: string;
    published_at?: string | null;
}

export default function Show({ post }: { post: Post }) {
    return (
        <SidebarProvider>
            <AdminSidebar />

            <SidebarInset>
                <Head title={`${post.title} - Blog Preview`} />

                {/* HEADER */}
                <header className="flex h-16 shrink-0 items-center gap-2 border-b px-6">
                    <SidebarTrigger />

                    <div className="h-4 w-px bg-gray-300" />

                    <nav className="flex items-center gap-2 text-sm text-gray-500">
                        <Link
                            href="/admin/posts"
                            className="hover:text-gray-900"
                        >
                            Blog Posts
                        </Link>

                        <span>/</span>

                        <span className="max-w-[260px] truncate font-medium text-gray-900">
                            {post.title}
                        </span>
                    </nav>
                </header>

                {/* MAIN */}
                <main className="bg-gray-50 px-6 py-8">
                    <div className="mx-auto max-w-5xl">
                        {/* ACTIONS */}
                        <div className="mb-6 flex items-center justify-between">
                            <Link
                                href="/admin/posts"
                                className="inline-flex items-center gap-2 rounded-lg bg-emerald-50 px-4 py-2 text-sm font-medium text-emerald-700 hover:bg-emerald-100"
                            >
                                <ChevronLeft className="h-4 w-4" />
                                Back to posts
                            </Link>

                            <Link
                                href={`/admin/posts/${post.id}/edit`}
                                className="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700"
                            >
                                <Edit3 className="h-4 w-4" />
                                Edit Post
                            </Link>
                        </div>

                        {/* ARTICLE */}
                        <article className="overflow-hidden rounded-2xl border bg-white shadow-sm">
                            {/* TOP */}
                            <div className="border-b bg-white px-8 py-8">
                                <div className="mb-4 flex items-center gap-3">
                                    <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-50">
                                        <FileText className="h-5 w-5 text-emerald-600" />
                                    </div>

                                    <span
                                        className={`rounded-full px-3 py-1 text-xs font-semibold capitalize ${
                                            post.status === 'published'
                                                ? 'bg-emerald-50 text-emerald-700'
                                                : 'bg-yellow-50 text-yellow-700'
                                        }`}
                                    >
                                        {post.status}
                                    </span>
                                </div>

                                {/* TITLE */}
                                <h1 className="max-w-3xl text-4xl font-bold tracking-tight text-gray-950">
                                    {post.title}
                                </h1>

                                {/* EXCERPT */}
                                <p className="mt-4 max-w-3xl text-lg leading-8 text-gray-600">
                                    {post.excerpt}
                                </p>

                                {/* META */}
                                <div className="mt-6 flex flex-wrap gap-6 border-t pt-5 text-sm text-gray-500">
                                    <div className="flex items-center gap-2">
                                        <Calendar className="h-4 w-4" />

                                        {post.published_at
                                            ? new Date(
                                                  post.published_at
                                              ).toLocaleDateString()
                                            : 'Not published'}
                                    </div>

                                    <div className="flex items-center gap-2">
                                        <User className="h-4 w-4" />
                                        System Admin
                                    </div>
                                </div>
                            </div>

                            {/* CONTENT */}
                            <div className="px-8 py-10">
                                <div
                                    className="prose prose-zinc max-w-none leading-8"
                                    dangerouslySetInnerHTML={{
                                        __html: post.content || '',
                                    }}
                                />
                            </div>
                        </article>
                    </div>
                </main>
            </SidebarInset>
        </SidebarProvider>
    );
}