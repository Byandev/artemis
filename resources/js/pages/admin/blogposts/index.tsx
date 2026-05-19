import { Head, Link, router } from '@inertiajs/react';
import {
    SidebarProvider,
    SidebarInset,
    SidebarTrigger,
} from '@/components/ui/sidebar';
import { AdminSidebar } from '@/components/admin-sidebar';
import { FileText, Plus } from 'lucide-react';

interface Post {
    id: number;
    title: string;
    slug: string;
    status: string;
}

export default function BlogPostsIndex({
    posts,
}: {
    posts: Post[];
}) {
    return (
        <SidebarProvider>
            <AdminSidebar />

            <SidebarInset>
                <Head title="Blog Posts" />

                <header className="flex h-16 shrink-0 items-center gap-2 border-b px-6">
                    <SidebarTrigger />

                    <div className="h-4 w-px bg-gray-300" />

                    <h1 className="text-sm font-medium">
                        Admin / Blog Posts
                    </h1>
                </header>

                <main className="p-6">
                    <div className="mb-8 flex items-center justify-between">
                        <div>
                            <h2 className="text-3xl font-bold tracking-tight">
                                Blog Post Management
                            </h2>

                            <p className="mt-1 text-sm text-gray-500">
                                Manage all blog posts, drafts, and published
                                articles.
                            </p>
                        </div>

                        <Link
                            href="/admin/posts/create"
                            className="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700"                        >
                            <Plus className="h-4 w-4" />
                            New Post
                        </Link>
                    </div>

                    <div className="overflow-hidden rounded-2xl border bg-white">
                        <div className="grid grid-cols-3 border-b bg-gray-50 px-6 py-4 text-xs font-semibold uppercase tracking-wider text-gray-500">
                            <div>Post Info</div>
                            <div>Status</div>
                            <div className="text-right">Actions</div>
                        </div>

                        {posts.length === 0 ? (
                            <div className="p-10 text-center text-sm text-gray-500">
                                No blog posts yet.
                            </div>
                        ) : (
                            posts.map((post) => (
                                <div
                                    key={post.id}
                                    className="grid grid-cols-3 items-center border-b px-6 py-5 last:border-b-0"
                                >
                                    <div className="flex items-center gap-4">
                                        <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-gray-100">
                                            <FileText className="h-5 w-5 text-gray-500" />
                                        </div>

                                        <div>
                                            <p className="font-semibold text-gray-900">
                                                {post.title}
                                            </p>

                                            <p className="text-sm text-gray-500">
                                                /{post.slug}
                                            </p>
                                        </div>
                                    </div>

                                    <div>
                                        <span className="rounded-full bg-emerald-100 px-3 py-1 text-xs font-medium text-emerald-700">
                                            {post.status}
                                        </span>
                                    </div>

                                    <div className="flex justify-end gap-4">
                                        <Link
                                            href={`/admin/posts/${post.id}/edit`}
                                            className="text-sm font-medium text-emerald-600 hover:underline"
                                        >
                                            Edit
                                        </Link>

                                        <button
                                            type="button"
                                            onClick={() => {
                                                if (
                                                    confirm(
                                                        'Delete this post?'
                                                    )
                                                ) {
                                                    router.delete(
                                                        `/admin/posts/${post.id}`
                                                    );
                                                }
                                            }}
                                            className="text-sm font-medium text-red-600 hover:underline"
                                        >
                                            Delete
                                        </button>
                                    </div>
                                </div>
                            ))
                        )}
                    </div>
                </main>
            </SidebarInset>
        </SidebarProvider>
    );
}