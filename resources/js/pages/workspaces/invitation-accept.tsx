import { Button } from "@/components/ui/button";
import AuthLayout from "@/layouts/auth-layout";
import { WorkspaceInvitation } from "@/types/models/WorkspaceInvitation";
import { Link, useForm } from "@inertiajs/react";
import workspaces from "@/routes/workspaces";

type PageProps = {
    invitation: WorkspaceInvitation;
    isAuthenticated: boolean;
};

export default function InvitationAccept({ invitation, isAuthenticated }: PageProps) {
    const { post, processing, errors } = useForm({});

    const handleAccept = (e: React.FormEvent) => {
        e.preventDefault();
        // Post to the accept route for this token
        post(workspaces.invitations.accept.url(invitation.token), {
            preserveScroll: true,
        });
    };

    return (
        <AuthLayout
            title={`Invitation to join "${invitation.workspace.name}"`}
            description={`You have been invited to join the workspace "${invitation.workspace.name}" as a ${invitation.role}.`}
        >
            <div className="max-w-xl mx-auto space-y-6">
                <div className="p-6 bg-white rounded-md shadow-sm">
                    <h2 className="text-lg font-semibold">{invitation.workspace.name}</h2>
                    <p className="text-sm text-muted-foreground mt-2">Invited by {invitation.inviter?.name}</p>
                    <p className="text-sm text-muted-foreground">Role: {invitation.role}</p>
                    <p className="text-sm text-muted-foreground">Expires: {new Date(invitation.expires_at).toLocaleString()}</p>

                    <div className="mt-4">
                        {isAuthenticated ? (
                            <form onSubmit={handleAccept}>
                                {errors?.error && (
                                    <p className="text-sm text-destructive">{errors.error}</p>
                                )}
                                <Button type="submit" disabled={processing}>
                                    {processing ? 'Accepting...' : 'Accept Invitation'}
                                </Button>
                            </form>
                        ) : (
                            <div className="flex space-x-2">
                                <Button asChild>
                                    <Link href={`/login?invitation=${invitation.token}`}>Log in</Link>
                                </Button>
                                <Button variant="outline" asChild>
                                    <Link href={`/register?invitation=${invitation.token}`}>Register</Link>
                                </Button>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </AuthLayout>
    );
}