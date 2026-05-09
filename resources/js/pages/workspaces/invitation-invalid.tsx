import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { WorkspaceInvitation } from '@/types/models/WorkspaceInvitation';
import { Link } from '@inertiajs/react';
import { AlertTriangle } from 'lucide-react';

interface InvitationInvalidProps {
    invitation: WorkspaceInvitation;
    reason: 'expired' | 'accepted';
}

export default function InvitationInvalid({
    invitation,
    reason,
}: InvitationInvalidProps) {
    const message =
        reason === 'expired'
            ? 'This invitation link has expired.'
            : 'This invitation was already accepted.';

    return (
        <div className="flex min-h-screen items-center justify-center bg-gray-50 p-6">
            <Card className="w-full max-w-lg rounded-2xl p-6 shadow-xl">
                <CardContent className="space-y-6 text-center">
                    <div className="flex flex-col items-center gap-3">
                        <AlertTriangle className="h-12 w-12" />
                        <h1 className="text-2xl font-bold">
                            Invitation Invalid
                        </h1>
                        <p className="text-gray-600">{message}</p>
                    </div>

                    <div className="text-sm text-gray-500">
                        <p>
                            Invitation sent to:{' '}
                            <span className="font-medium">
                                {invitation.email}
                            </span>
                        </p>
                        <p>
                            Workspace:{' '}
                            <span className="font-medium">
                                {invitation.workspace.name}
                            </span>
                        </p>
                    </div>

                    <Link href="/workspaces">
                        <Button className="w-full rounded-xl py-3 text-base">
                            Go to Workspaces
                        </Button>
                    </Link>
                </CardContent>
            </Card>
        </div>
    );
}
