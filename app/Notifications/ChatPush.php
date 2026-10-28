<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/** New chat message => phone push only (not in the bell list, not by email) */
class ChatPush extends Notification
{
    public function __construct(
        public string $title,
        public string $message,
        public string $url,
        public string $room,
    ) {}

    public function via(object $notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush(object $notifiable, Notification $notification): WebPushMessage
    {
        // Same tag per group: a new message replaces the old one, like WhatsApp
        return WorkflowNotification::push($this->title, $this->message, $this->url)->tag('chat-'.$this->room)->renotify();
    }
}
