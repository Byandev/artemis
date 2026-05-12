import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { WorkspaceInvitation } from '@/types/models/WorkspaceInvitation';
import { Link, usePage } from '@inertiajs/react';
import { AlertCircle, CheckCircle, LogIn, UserPlus } from 'lucide-react';

interface InvitationAcceptProps {
    invitation: WorkspaceInvitation;
    isAuthenticated: boolean;
    accepted?: boolean;
}

export default function InvitationAccept({
    invitation,
    isAuthenticated,
    accepted,
}: InvitationAcceptProps) {
    const { errors } = usePage().props;

    return (
        <div className="flex min-h-screen items-center justify-center bg-gray-50 p-6">
            <Card className="w-full max-w-lg rounded-2xl p-6 shadow-xl">
                <CardContent className="space-y-6 text-center">
                    {/* Show error message if present */}
                    {errors && Object.keys(errors).length > 0 && (
                        <Alert variant="destructive">
                            <AlertCircle className="h-4 w-4" />
                            <AlertDescription>
                                {Object.values(errors)[0] as string}
                            </AlertDescription>
                        </Alert>
                    )}

                    {/* If the invitation has already been accepted and we're showing
                        the post-accept state, surface a success message and a link
                        to the workspace. Otherwise show the normal accept/login
                        actions. */}
                    {/* We expect the server to pass an `accepted` prop when appropriate. */}
                    {(accepted ?? false) ? (
                        <div className="flex flex-col items-center gap-3">
                            <CheckCircle className="h-12 w-12 text-green-500" />
                            <h1 className="text-2xl font-bold">
                                Welcome to the workspace!
                            </h1>
                            <p className="text-gray-600">
                                You have successfully joined
                                <span className="font-medium">
                                    {' '}
                                    {invitation.workspace.name}
                                </span>
                                .
                            </p>

                            <div className="w-full pt-2">
                                <Link
                                    href={`/workspaces/${invitation.workspace.slug}/dashboard`}
                                >
                                    <Button className="w-full rounded-xl py-3 text-base">
                                        Go to Workspace
                                    </Button>
                                </Link>
                            </div>
                        </div>
                    ) : (
                        <>
                            <div className="flex flex-col items-center gap-3">
                                <CheckCircle className="h-12 w-12" />
                                <h1 className="text-2xl font-bold">
                                    You're Invited!
                                </h1>
                                <p className="text-gray-600">
                                    You have been invited to join the workspace
                                    <span className="font-medium">
                                        {' '}
                                        {invitation.workspace.name}
                                    </span>
                                    .
                                </p>
                            </div>

                            <div className="text-sm text-gray-500">
                                <p>
                                    Invitation sent to:{' '}
                                    <span className="font-medium">
                                        {invitation.email}
                                    </span>
                                </p>
                            </div>

                            {isAuthenticated ? (
                                <div className="w-full space-y-3">
                                    {errors &&
                                    Object.keys(errors).length > 0 ? (
                                        <>
                                            <Link
                                                href="/logout"
                                                method="post"
                                                as="button"
                                                className="w-full"
                                            >
                                                <Button
                                                    variant="destructive"
                                                    className="w-full rounded-xl py-3 text-base"
                                                >
                                                    Logout and Login with
                                                    Correct Account
                                                </Button>
                                            </Link>
                                        </>
                                    ) : (
                                        <Link
                                            href={`/workspaces/invitations/${invitation.token}/accept`}
                                        >
                                            <Button className="w-full rounded-xl py-3 text-base">
                                                Accept Invitation
                                            </Button>
                                        </Link>
                                    )}
                                </div>
                            ) : (
                                <div className="w-full space-y-3">
                                    <Link
                                        href={`/register?invitation=${invitation.token}`}
                                    >
                                        <Button className="flex w-full items-center justify-center gap-2 rounded-xl py-3 text-base">
                                            <UserPlus className="h-5 w-5" />{' '}
                                            Create Account
                                        </Button>
                                    </Link>
                                    <Link
                                        href={`/login?invitation=${invitation.token}`}
                                    >
                                        <Button
                                            variant="outline"
                                            className="flex w-full items-center justify-center gap-2 rounded-xl py-3 text-base"
                                        >
                                            <LogIn className="h-5 w-5" /> Login
                                            Instead
                                        </Button>
                                    </Link>
                                    <p className="text-center text-xs text-gray-500">
                                        Don't have an account? Create one to
                                        accept the invitation.
                                    </p>
                                </div>
                            )}
                        </>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}
