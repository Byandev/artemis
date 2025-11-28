import { Button } from "@/components/ui/button";
import AuthLayout from "@/layouts/auth-layout";
import { WorkspaceInvitation } from "@/types/models/WorkspaceInvitation";
import { Link } from "@inertiajs/react";

type PageProps = {
    invitation?: WorkspaceInvitation;
    reason?: "expired" | "accepted";
};

export default function InvitationInvalid({
    invitation,
    reason = "expired",
}: PageProps) {
    console.log({ invitation, reason });
    const workspaceName = invitation?.workspace.name ?? "this workspace";

    const title =
        reason === "accepted" ? "Invitation Already Accepted" : "Invitation Expired";

    const description =
        reason === "accepted"
            ? `This invitation to join the workspace "${workspaceName}" has already been accepted.`
            : `This invitation to join the workspace "${workspaceName}" has expired.`;

    const button =
        reason === "accepted" ? (
            <Button asChild>
                <Link
                    href={
                        invitation?.workspace?.slug
                            ? `/workspaces/${invitation.workspace.slug}/dashboard`
                            : "/workspaces"
                    }
                >
                    Go to the workspace
                </Link>
            </Button>
        ) : (
            <Button asChild>
                <Link href="/workspaces">View workspaces</Link>
            </Button>
        );

    return (
        <AuthLayout title={title} description={description}>
            <div className="flex justify-center">{button}</div>
        </AuthLayout>
    );
}
