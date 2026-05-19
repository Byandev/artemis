import { Head, Link, useForm } from '@inertiajs/react';
import {
    SidebarProvider,
    SidebarInset,
    SidebarTrigger,
} from '@/components/ui/sidebar';
import { AdminSidebar } from '@/components/admin-sidebar';
import { ChevronLeft } from 'lucide-react';

interface Post {
    id: number;
    title: string;
    slug: string;
    excerpt: string;
    content: string;
    status: string;
    seo_title?: string;
    seo_description?: string;
}

export default function EditPost({ post }: { post: Post }) {
    const { data, setData, put, processing } = useForm({
        title: post.title || '',
        slug: post.slug || '',
        excerpt: post.excerpt || '',
        content: post.content || '',
        status: post.status || 'draft',
        seo_title: post.seo_title || '',
        seo_description: post.seo_description || '',
    });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        put(`/admin/posts/${post.id}`);
    }

    return (
        <SidebarProvider>
            <AdminSidebar />

            <SidebarInset>
                <Head title="Edit Blog Post" />

                <header className="flex h-16 items-center gap-2 border-b px-6">
                    <SidebarTrigger />
                    <div className="h-4 w-px bg-gray-300" />
                    <span className="text-sm font-medium">Blog Posts / Edit</span>
                </header>

                <main className="bg-gray-50 px-6 py-8">
                    <div className="mx-auto max-w-5xl">
                        <div className="mb-6 flex items-center justify-between">
                            <Link
                                href="/admin/posts"
                                className="inline-flex items-center gap-2 text-sm font-medium text-gray-500 hover:text-gray-900"
                            >
                                <ChevronLeft className="h-4 w-4" />
                                Back to posts
                            </Link>
                        </div>

                        <form
                            onSubmit={submit}
                            className="rounded-2xl border bg-white p-8 shadow-sm"
                        >
                            <h1 className="mb-6 text-3xl font-bold">
                                Edit Blog Post
                            </h1>

                            <div className="grid gap-5">
                                <input
                                    value={data.title}
                                    onChange={(e) => setData('title', e.target.value)}
                                    placeholder="Post title"
                                    className="rounded-lg border px-4 py-3 text-sm"
                                />

                                <input
                                    value={data.slug}
                                    onChange={(e) => setData('slug', e.target.value)}
                                    placeholder="Slug"
                                    className="rounded-lg border px-4 py-3 text-sm"
                                />

                                <textarea
                                    value={data.excerpt}
                                    onChange={(e) => setData('excerpt', e.target.value)}
                                    placeholder="Short excerpt"
                                    className="min-h-24 rounded-lg border px-4 py-3 text-sm"
                                />

                                <textarea
                                    value={data.content}
                                    onChange={(e) => setData('content', e.target.value)}
                                    placeholder="Post content"
                                    className="min-h-64 rounded-lg border px-4 py-3 text-sm"
                                />

                                <select
                                    value={data.status}
                                    onChange={(e) => setData('status', e.target.value)}
                                    className="rounded-lg border px-4 py-3 text-sm"
                                >
                                    <option value="draft">Draft</option>
                                    <option value="published">Published</option>
                                </select>

                                <input
                                    value={data.seo_title}
                                    onChange={(e) => setData('seo_title', e.target.value)}
                                    placeholder="SEO title"
                                    className="rounded-lg border px-4 py-3 text-sm"
                                />

                                <textarea
                                    value={data.seo_description}
                                    onChange={(e) =>
                                        setData('seo_description', e.target.value)
                                    }
                                    placeholder="SEO description"
                                    className="min-h-20 rounded-lg border px-4 py-3 text-sm"
                                />
                            </div>

                            <div className="mt-6 flex justify-end gap-3">
                                <Link
                                    href="/admin/posts"
                                    className="rounded-lg border px-4 py-2 text-sm font-medium"
                                >
                                    Cancel
                                </Link>

                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700"
                                >
                                    Save Changes
                                </button>
                            </div>
                        </form>
                    </div>
                </main>
            </SidebarInset>
        </SidebarProvider>
    );
}