import { Button } from "@/components/ui/button";
import AuthLayout from "@/layouts/auth-layout";
import { WorkspaceInvitation } from "@/types/models/WorkspaceInvitation";
import { Link } from "@inertiajs/react";

type PageProps = {
    invitation: WorkspaceInvitation;
    isAuthenticated: boolean;
};

export default function InvitationAccept({ invitation, isAuthenticated }: PageProps) {
    return (
        <AuthLayout
            title="Invitation Accepted"
            description={`You have successfully accepted the invitation to join the workspace "${invitation.workspace.name}" as a ${invitation.role}.`}
        >
            <div className="flex justify-center">
                {isAuthenticated ? (
                    <Button asChild>
                        <Link href={`/workspaces/${invitation.workspace.slug}/dashboard`}>
                            Go to the workspace
                        </Link>
                    </Button>
                ) : (
                    <Button asChild>
                        <Link href="/login">Log in to your account</Link>
                    </Button>
                )}
            </div>
        </AuthLayout>
    );
}