import { useState } from 'react';
import { router } from '@inertiajs/react';
import { Page } from '@/types/models/Page';
import { Workspace } from '@/types/models/Workspace';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import workspaces from '@/routes/workspaces';

interface ArchivePageDialogProps {
    page: Page | null;
    workspace: Workspace;
    onClose: () => void;
}

export function ArchivePageDialog({ page, workspace, onClose }: ArchivePageDialogProps) {
    const [processing, setProcessing] = useState(false);

    const handleArchive = () => {
        if (!page) return;

        setProcessing(true);
        router.post(
            workspaces.pages.archive.url({ workspace, page }),
            {},
            {
                onSuccess: () => onClose(),
                onFinish: () => setProcessing(false),
            }
        );
    };

    return (
        <AlertDialog open={!!page} onOpenChange={(open) => !open && onClose()}>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>Archive Page</AlertDialogTitle>
                    <AlertDialogDescription>
                        Are you sure you want to archive "{page?.name}"?
                        You can restore it later from the Archived tab.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel disabled={processing}>Cancel</AlertDialogCancel>
                    <AlertDialogAction onClick={handleArchive} disabled={processing}>
                        {processing ? 'Archiving...' : 'Archive'}
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}
