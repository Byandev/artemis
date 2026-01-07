import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { WorkspaceInvitation } from "@/types/models/WorkspaceInvitation";
import { Link } from "@inertiajs/react";
import { CheckCircle, LogIn, UserPlus } from "lucide-react";

interface InvitationAcceptProps {
    invitation: WorkspaceInvitation;
    isAuthenticated: boolean;
    accepted?: boolean;
}

export default function InvitationAccept({ invitation, isAuthenticated, accepted }: InvitationAcceptProps) {
    return (
        <div className="min-h-screen flex items-center justify-center p-6 bg-gray-50">
            <Card className="max-w-lg w-full p-6 shadow-xl rounded-2xl">
                <CardContent className="space-y-6 text-center">
                    {/* If the invitation has already been accepted and we're showing
                        the post-accept state, surface a success message and a link
                        to the workspace. Otherwise show the normal accept/login
                        actions. */}
                    {/* We expect the server to pass an `accepted` prop when appropriate. */}
                    {(accepted ?? false) ? (
                        <div className="flex flex-col items-center gap-3">
                            <CheckCircle className="w-12 h-12 text-green-500" />
                            <h1 className="text-2xl font-bold">Welcome to the workspace!</h1>
                            <p className="text-gray-600">
                                You have successfully joined
                                <span className="font-medium"> {invitation.workspace.name}</span>.
                            </p>

                            <div className="w-full pt-2">
                                <Link href={`/workspaces/${invitation.workspace.slug}/dashboard`}>
                                    <Button className="w-full py-3 rounded-xl text-base">Go to Workspace</Button>
                                </Link>
                            </div>
                        </div>
                    ) : (
                        <>
                            <div className="flex flex-col items-center gap-3">
                                <CheckCircle className="w-12 h-12" />
                                <h1 className="text-2xl font-bold">You're Invited!</h1>
                                <p className="text-gray-600">
                                    You have been invited to join the workspace
                                    <span className="font-medium"> {invitation.workspace.name}</span>.
                                </p>
                            </div>

                            <div className="text-gray-500 text-sm">
                                <p>
                                    Invitation sent to: <span className="font-medium">{invitation.email}</span>
                                </p>
                            </div>

                            {isAuthenticated ? (
                                <Link href={`/workspaces/invitations/${invitation.token}/accept`}>
                                    <Button className="w-full py-3 rounded-xl text-base">Accept Invitation</Button>
                                </Link>
                            ) : (
                                <div className="space-y-3 w-full">
                                    <Link href={`/register?invitation=${invitation.token}`}>
                                        <Button className="w-full py-3 rounded-xl text-base flex items-center gap-2 justify-center">
                                            <UserPlus className="w-5 h-5" /> Create Account
                                        </Button>
                                    </Link>
                                    <Link href={`/login?invitation=${invitation.token}`}>
                                        <Button variant="outline" className="w-full py-3 rounded-xl text-base flex items-center gap-2 justify-center">
                                            <LogIn className="w-5 h-5" /> Login Instead
                                        </Button>
                                    </Link>
                                    <p className="text-xs text-gray-500 text-center">
                                        Don't have an account? Create one to accept the invitation.
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
