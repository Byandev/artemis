import { Head, Link, useForm } from '@inertiajs/react';
import {
    SidebarProvider,
    SidebarInset,
    SidebarTrigger,
} from '@/components/ui/sidebar';
import { AdminSidebar } from '@/components/admin-sidebar';

import { useEditor, EditorContent } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';

export default function CreatePost() {
    const { data, setData, post, processing } = useForm({
        title: '',
        excerpt: '',
        content: '',
        status: 'draft',
        seo_title: '',
        seo_description: '',
    });

    const editor = useEditor({
        extensions: [StarterKit],
        content: '',
        immediatelyRender: false,
        editorProps: {
            attributes: {
                class:
                    'min-h-[300px] outline-none text-sm prose max-w-none',
            },
        },
        onUpdate: ({ editor }) => {
            setData('content', editor.getHTML());
        },
    });

    function submit(e: React.FormEvent) {
        e.preventDefault();

        post('/admin/posts');
    }

    return (
        <SidebarProvider>
            <AdminSidebar />

            <SidebarInset>
                <Head title="Create Blog Post" />

                <header className="flex h-16 shrink-0 items-center gap-2 border-b px-6">
                    <SidebarTrigger />
                </header>

                <main className="p-6">
                    <div className="mb-6">
                        <h1 className="text-2xl font-semibold text-gray-900">
                            Create Blog Post
                        </h1>

                        <p className="text-sm text-gray-500">
                            Add a new blog article to your platform.
                        </p>
                    </div>

                    <form
                        onSubmit={submit}
                        className="rounded-2xl border bg-white p-6"
                    >
                        <div className="grid gap-5">
                            {/* POST TITLE */}
                            <div className="grid gap-2">
                                <label className="text-sm font-semibold text-gray-900">
                                    Post Title
                                </label>

                                <input
                                    type="text"
                                    value={data.title}
                                    onChange={(e) =>
                                        setData('title', e.target.value)
                                    }
                                    placeholder="Post title"
                                    className="rounded-lg border px-4 py-3 text-sm"
                                />
                            </div>

                            {/* SHORT EXCERPT */}
                            <div className="grid gap-2">
                                <label className="text-sm font-semibold text-gray-900">
                                    Short Excerpt
                                </label>

                                <textarea
                                    value={data.excerpt}
                                    onChange={(e) =>
                                        setData('excerpt', e.target.value)
                                    }
                                    placeholder="Short excerpt"
                                    className="min-h-24 rounded-lg border px-4 py-3 text-sm"
                                />
                            </div>

                            {/* POST CONTENT */}
                            <div className="grid gap-2">
                                <label className="text-sm font-semibold text-gray-900">
                                    Post Content
                                </label>

                                <div className="rounded-xl border bg-white p-4">
                                    <EditorContent editor={editor} />
                                </div>
                            </div>

                            {/* STATUS */}
                            <div className="grid gap-2">
                                <label className="text-sm font-semibold text-gray-900">
                                    Status
                                </label>

                                <select
                                    value={data.status}
                                    onChange={(e) =>
                                        setData('status', e.target.value)
                                    }
                                    className="rounded-lg border px-4 py-3 text-sm"
                                >
                                    <option value="draft">
                                        Draft
                                    </option>

                                    <option value="published">
                                        Published
                                    </option>
                                </select>
                            </div>

                            {/* SEO TITLE */}
                            <div className="grid gap-2">
                                <label className="text-sm font-semibold text-gray-900">
                                    SEO Title
                                </label>

                                <input
                                    type="text"
                                    value={data.seo_title}
                                    onChange={(e) =>
                                        setData(
                                            'seo_title',
                                            e.target.value
                                        )
                                    }
                                    placeholder="SEO title"
                                    className="rounded-lg border px-4 py-3 text-sm"
                                />
                            </div>

                            {/* SEO DESCRIPTION */}
                            <div className="grid gap-2">
                                <label className="text-sm font-semibold text-gray-900">
                                    SEO Description
                                </label>

                                <textarea
                                    value={data.seo_description}
                                    onChange={(e) =>
                                        setData(
                                            'seo_description',
                                            e.target.value
                                        )
                                    }
                                    placeholder="SEO description"
                                    className="min-h-20 rounded-lg border px-4 py-3 text-sm"
                                />
                            </div>
                        </div>

                        {/* ACTION BUTTONS */}
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
                                Create Post
                            </button>
                        </div>
                    </form>
                </main>
            </SidebarInset>
        </SidebarProvider>
    );
}