<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/** One notification for every event: title + message + link (in-app + email + phone push) */
class WorkflowNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $title,
        public string $message,
        public string $url,
    ) {}

    public function via(object $notifiable): array
    {
        // push last: the channel skips users without a subscribed device
        return ['database', 'mail', WebPushChannel::class];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title)
            ->greeting('Hi '.$notifiable->name.',')
            ->lines(explode("\n", $this->message)) // a list stays one item per line in the email
            ->action('Open', $this->url);
    }

    public function toWebPush(object $notifiable, Notification $notification): WebPushMessage
    {
        return self::push($this->title, $this->message, $this->url);
    }

    public static function push(string $title, string $message, string $url): WebPushMessage
    {
        return (new WebPushMessage)->title($title)->body(mb_strimwidth($message, 0, 180, '…'))
            ->icon('/icons/icon-192.png')->badge('/icons/icon-192.png')->data(['url' => $url]);
    }

    public function toArray(object $notifiable): array
    {
        return ['title' => $this->title, 'message' => $this->message, 'url' => $this->url];
    }
}
