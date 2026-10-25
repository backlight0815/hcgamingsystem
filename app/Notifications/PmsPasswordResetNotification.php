<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class PmsPasswordResetNotification extends ResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        $url = route('pms.password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);

        return (new MailMessage)
            ->subject('Reset your Project Management password')
            ->line('Use this link to reset your Project Management workspace password.')
            ->action('Reset password', $url)
            ->line('If you did not request this, you can ignore this email.');
    }
}
