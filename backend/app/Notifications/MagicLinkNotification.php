<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MagicLinkNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public string $token;

    /**
     * Create a new notification instance.
     */
    public function __construct(string $token)
    {
        $this->token = $token;
    }

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
        $url = 'http://' . tenant()->domains->first()->domain . '/api/v1/auth/magic-link/verify/' . $this->token;

        return (new MailMessage)
            ->subject('Your Magic Login Link')
            ->greeting('Hello ' . $notifiable->name . '!')
            ->line('You requested a magic link to sign in to your account.')
            ->line('Click the button below to securely sign in:')
            ->action('Sign In', $url)
            ->line('This link will expire in ' . config('auth.magic_link_expire_minutes', 15) . ' minutes.')
            ->line('If you did not request this link, please ignore this email.')
            ->salutation('Best regards, ' . config('app.name') . ' Team');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'token' => $this->token,
            'expires_at' => now()->addMinutes(config('auth.magic_link_expire_minutes', 15)),
        ];
    }
}
