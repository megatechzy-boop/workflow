<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** One customer number on the company WhatsApp; free replies only within 24 hours of their last message */
class WhatsAppChat extends Model
{
    protected $table = 'whatsapp_chats';

    protected $fillable = ['wa_id', 'name', 'last_message_at', 'last_inbound_at', 'unread'];

    protected function casts(): array
    {
        return ['last_message_at' => 'datetime', 'last_inbound_at' => 'datetime'];
    }

    public function messages(): HasMany
    {
        return $this->hasMany(WhatsAppMessage::class, 'whatsapp_chat_id');
    }

    public function lastMessage(): HasOne
    {
        return $this->hasOne(WhatsAppMessage::class, 'whatsapp_chat_id')->latestOfMany('sent_at');
    }

    /** Until when a normal message can be sent (null = window closed, a template is needed) */
    public function windowEndsAt(): ?\Illuminate\Support\Carbon
    {
        $end = $this->last_inbound_at?->copy()->addDay();

        return $end && $end->isFuture() ? $end : null;
    }

    public function title(): string
    {
        return $this->name ?: '+'.$this->wa_id;
    }

    /** "98908 40101" / "+91 98908-40101" / "919890840101" => "919890840101" (10 digits get India's 91) */
    public static function normalise(string $number): string
    {
        $digits = preg_replace('/\D+/', '', $number);
        $digits = ltrim($digits, '0');

        return strlen($digits) === 10 ? '91'.$digits : $digits;
    }
}
