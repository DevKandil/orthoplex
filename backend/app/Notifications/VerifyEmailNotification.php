<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

class VerifyEmailNotification extends VerifyEmail implements ShouldQueue
{
    use Queueable;

    /**
     * Get the verification URL for the given notifiable.
     *
     * @param  mixed  $notifiable
     * @return string
     */
    protected function verificationUrl($notifiable)
    {
        $domain = tenant()->domains->first()->domain;

        URL::forceRootUrl("http://{$domain}");

        return URL::temporarySignedRoute(
            'api.v1.tenant.email.verify',
            now()->addMinutes(config('auth.verification.expire', 60)),
            [
                'user' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ]
        );
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        $verificationUrl = $this->verificationUrl($notifiable);

        return (new MailMessage)
            ->subject('Verify Your Email Address')
            ->greeting('Hello ' . $notifiable->name . '!')
            ->line('Welcome to ' . config('app.name') . '! We\'re excited to have you on board.')
            ->line('Please click the button below to verify your email address and activate your account.')
            ->action('Verify Email Address', $verificationUrl)
            ->line('This verification link will expire in ' . config('auth.verification.expire', 60) . ' minutes.')
            ->line('If you did not create an account, no further action is required.')
            ->salutation('Best regards, The ' . config('app.name') . ' Team');
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            'message' => 'Please verify your email address to complete your registration.',
            'action_url' => $this->verificationUrl($notifiable),
        ];
    }
}