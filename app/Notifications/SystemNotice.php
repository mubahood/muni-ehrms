<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A message to one person: shown under the bell, and e-mailed when e-mail
 * notifications are switched on and the person has an address.
 */
class SystemNotice extends Notification
{
    use Queueable;

    public string $title;
    public string $body;
    public ?string $url;
    public bool $email;

    public function __construct(string $title, string $body, ?string $url = null, bool $email = true)
    {
        $this->title = $title;
        $this->body = $body;
        $this->url = $url;
        $this->email = $email;
    }

    public function via($notifiable): array
    {
        $channels = ['database'];
        if ($this->email && config('ehrms.notify_email') && filter_var($notifiable->email, FILTER_VALIDATE_EMAIL)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toArray($notifiable): array
    {
        return ['title' => $this->title, 'body' => $this->body, 'url' => $this->url];
    }

    public function toMail($notifiable): MailMessage
    {
        $mail = (new MailMessage())
            ->subject($this->title . ' – Muni University EHRMS')
            ->greeting('Dear ' . ($notifiable->name ?: $notifiable->username) . ',')
            ->line($this->body);
        if ($this->url) {
            $mail->action('Open in EHRMS', $this->url);
        }

        return $mail->salutation('Human Resource Office, Muni University');
    }
}
