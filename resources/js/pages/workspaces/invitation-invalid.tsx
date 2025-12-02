import React from "react";
import { Card, CardContent } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { AlertTriangle } from "lucide-react";
import { Link } from "@inertiajs/react";
import { WorkspaceInvitation } from "@/types/models/WorkspaceInvitation";

interface InvitationInvalidProps {
    invitation: WorkspaceInvitation;
    reason: "expired" | "accepted";
}

export default function InvitationInvalid({ invitation, reason }: InvitationInvalidProps) {
    const message =
        reason === "expired"
            ? "This invitation link has expired."
            : "This invitation was already accepted.";

    return (
        <div className="min-h-screen flex items-center justify-center p-6 bg-gray-50">
            <Card className="max-w-lg w-full p-6 shadow-xl rounded-2xl">
                <CardContent className="space-y-6 text-center">
                    <div className="flex flex-col items-center gap-3">
                        <AlertTriangle className="w-12 h-12" />
                        <h1 className="text-2xl font-bold">Invitation Invalid</h1>
                        <p className="text-gray-600">{message}</p>
                    </div>

                    <div className="text-gray-500 text-sm">
                        <p>
                            Invitation sent to: <span className="font-medium">{invitation.email}</span>
                        </p>
                        <p>
                            Workspace: <span className="font-medium">{invitation.workspace.name}</span>
                        </p>
                    </div>

                    <Link href="/workspaces">
                        <Button className="w-full py-3 rounded-xl text-base">Go to Workspaces</Button>
                    </Link>
                </CardContent>
            </Card>
        </div>
    );
}
