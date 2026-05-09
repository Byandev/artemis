<?php

namespace App\Policies;

use App\Models\SupportTicket;
use App\Models\User;
use App\Models\Workspace;

class SupportTicketPolicy
{
    public function create(User $user, Workspace $workspace): bool
    {
        return $user->isMemberOf($workspace);
    }

    public function viewOwn(User $user, SupportTicket $ticket): bool
    {
        return $user->isMemberOf($ticket->workspace) && (int) $ticket->user_id === (int) $user->id;
    }

    public function viewAny(User $user, Workspace $workspace): bool
    {
        return $user->ownsWorkspace($workspace) || $user->isAdminOf($workspace) || $user->hasWorkspaceRole($workspace, 'admin');
    }

    public function update(User $user, SupportTicket $ticket): bool
    {
        return $this->viewAny($user, $ticket->workspace);
    }
}
