<?php

namespace App\Notifications;

use App\Models\WorkspaceInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class WorkspaceInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public WorkspaceInvitation $invitation
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $workspace = $this->invitation->workspace;
        $inviter = $this->invitation->inviter;
        $acceptUrl = URL::route('workspaces.invitations.accept', ['token' => $this->invitation->token]);

        return (new MailMessage)
            ->subject("You've been invited to join {$workspace->name}")
            ->greeting('Hello!')
            ->line("{$inviter->name} has invited you to join the \"{$workspace->name}\" workspace.")
            ->line("As a {$this->invitation->role}, you'll be able to collaborate with the team.")
            ->action('Accept Invitation', $acceptUrl)
            ->line('This invitation will expire on '.$this->invitation->expires_at->format('F j, Y \a\t g:i A').'.')
            ->line('If you did not expect this invitation, no further action is required.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'workspace_id' => $this->invitation->workspace_id,
            'workspace_name' => $this->invitation->workspace->name,
            'invited_by' => $this->invitation->inviter->name,
            'role' => $this->invitation->role,
            'token' => $this->invitation->token,
        ];
    }
}
