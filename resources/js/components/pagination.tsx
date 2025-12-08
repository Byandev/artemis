import React from 'react'
import { Button } from '@/components/ui/button'
import { router } from '@inertiajs/react'

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface Props {
    total: number;
    from: number;
    to: number;
    last_page: number;
    links: PaginationLink[];
}

export default function Pagination({ data }: { data: Props }) {
    return (
        <div className="mt-4 flex items-center justify-between">
            <div className="text-sm text-muted-foreground">
                {data.total > 0 ? (
                    <>Showing {data.from} to {data.to} of {data.total} results</>
                ) : (
                    <>No results found</>
                )}
            </div>

            {data.last_page > 1 && (
                <div className="flex gap-2">
                    {data.links.map((link, index) => (
                        <Button
                            className='hover:cursor-pointer'
                            key={index}
                            variant={link.active ? 'default' : 'outline'}
                            size="sm"
                            disabled={!link.url}
                            onClick={() => {
                                if (link.url) {
                                    router.get(link.url, {}, { preserveState: true, preserveScroll: true });
                                }
                            }}
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    ))}
                </div>
            )}
        </div>
    )
}
