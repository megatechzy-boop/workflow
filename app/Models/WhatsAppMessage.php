<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A message in or out; "out" ones come from our inbox (source app) or from the phone / WhatsApp Web (source phone) */
class WhatsAppMessage extends Model
{
    protected $table = 'whatsapp_messages';

    protected $fillable = ['whatsapp_chat_id', 'wamid', 'direction', 'source', 'user_id', 'type', 'body', 'media_id',
        'media_path', 'file_name', 'mime', 'status', 'error', 'sent_at'];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }

    public function chat(): BelongsTo
    {
        return $this->belongsTo(WhatsAppChat::class, 'whatsapp_chat_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function hasFile(): bool
    {
        return in_array($this->type, ['image', 'document', 'audio', 'video', 'sticker'], true) && ($this->media_id || $this->media_path);
    }

    /** Who sent it, for the chat bubble */
    public function senderLabel(): string
    {
        return match (true) {
            $this->direction === 'in' => $this->chat?->title() ?? 'Customer',
            $this->source === 'phone' => 'WhatsApp app / Web',
            default => $this->user?->name ?? 'Workflow',
        };
    }
}
