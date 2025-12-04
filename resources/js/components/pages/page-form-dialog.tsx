import { useEffect } from 'react';
import { useForm } from '@inertiajs/react';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Page } from '@/types/models/Page';
import workspaces from '@/routes/workspaces';
import { Workspace } from '@/types/models/Workspace';

interface PageFormDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    page?: Page;
    workspace: Workspace;
}

export function PageFormDialog({ open, onOpenChange, page, workspace }: PageFormDialogProps) {
    const isEditing = !!page;

    const { data, setData, post, put, processing, errors, reset } = useForm({
        id: page?.id || '',
        shop_id: page?.shop_id || '',
        name: page?.name || '',
        pos_token: page?.pos_token || '',
        botcake_token: page?.botcake_token || '',
        infotxt_token: page?.infotxt_token || '',
        infotxt_user_id: page?.infotxt_user_id || '',
    });

    useEffect(() => {
        if (page) {
            setData({
                id: page.id || '',
                shop_id: page.shop_id || '',
                name: page.name || '',
                pos_token: page.pos_token || '',
                botcake_token: page.botcake_token || '',
                infotxt_token: page.infotxt_token || '',
                infotxt_user_id: page.infotxt_user_id || '',
            });
        } else {
            reset();
        }
    }, [page, open]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (isEditing) {
            put(workspaces.pages.update.url({ workspace, page }), {
                onSuccess: () => {
                    onOpenChange(false);
                    reset();
                },
            });
        } else {
            post(workspaces.pages.store.url({ workspace }), {
                onSuccess: () => {
                    onOpenChange(false);
                    reset();
                },
            });
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-[600px]">
                <form onSubmit={handleSubmit}>
                    <DialogHeader>
                        <DialogTitle>{isEditing ? 'Edit Page' : 'Create New Page'}</DialogTitle>
                        <DialogDescription>
                            {isEditing
                                ? 'Update the page information below.'
                                : 'Fill in the details to create a new page.'}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-4 py-4">
                        <div className="grid gap-2">
                            <Label htmlFor="shop_id">ID</Label>
                            <Input
                                id="id"
                                type="number"
                                value={data.id}
                                onChange={(e) => setData('id', e.target.value)}
                                placeholder="Enter ID"
                                aria-invalid={!!errors.id}
                            />
                            {errors.id && (
                                <p className="text-sm text-destructive">{errors.id}</p>
                            )}
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="shop_id">Shop ID</Label>
                            <Input
                                id="shop_id"
                                type="number"
                                value={data.shop_id}
                                onChange={(e) => setData('shop_id', e.target.value)}
                                placeholder="Enter shop ID"
                                aria-invalid={!!errors.shop_id}
                            />
                            {errors.shop_id && (
                                <p className="text-sm text-destructive">{errors.shop_id}</p>
                            )}
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="name">Name</Label>
                            <Input
                                id="name"
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                placeholder="Enter page name"
                                aria-invalid={!!errors.name}
                            />
                            {errors.name && (
                                <p className="text-sm text-destructive">{errors.name}</p>
                            )}
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="pos_token">POS Token</Label>
                            <Input
                                id="pos_token"
                                value={data.pos_token}
                                onChange={(e) => setData('pos_token', e.target.value)}
                                placeholder="Enter POS token"
                                aria-invalid={!!errors.pos_token}
                            />
                            {errors.pos_token && (
                                <p className="text-sm text-destructive">{errors.pos_token}</p>
                            )}
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="botcake_token">Botcake Token</Label>
                            <Input
                                id="botcake_token"
                                value={data.botcake_token}
                                onChange={(e) => setData('botcake_token', e.target.value)}
                                placeholder="Enter Botcake token"
                                aria-invalid={!!errors.botcake_token}
                            />
                            {errors.botcake_token && (
                                <p className="text-sm text-destructive">{errors.botcake_token}</p>
                            )}
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="infotxt_token">Infotxt Token</Label>
                            <Input
                                id="infotxt_token"
                                value={data.infotxt_token}
                                onChange={(e) => setData('infotxt_token', e.target.value)}
                                placeholder="Enter Infotxt token"
                                aria-invalid={!!errors.infotxt_token}
                            />
                            {errors.infotxt_token && (
                                <p className="text-sm text-destructive">{errors.infotxt_token}</p>
                            )}
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="infotxt_user_id">Infotxt User ID</Label>
                            <Input
                                id="infotxt_user_id"
                                value={data.infotxt_user_id}
                                onChange={(e) => setData('infotxt_user_id', e.target.value)}
                                placeholder="Enter Infotxt user ID"
                                aria-invalid={!!errors.infotxt_user_id}
                            />
                            {errors.infotxt_user_id && (
                                <p className="text-sm text-destructive">{errors.infotxt_user_id}</p>
                            )}
                        </div>
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                            disabled={processing}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {processing ? 'Saving...' : isEditing ? 'Update Page' : 'Create Page'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
