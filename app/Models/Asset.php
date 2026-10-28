<?php

namespace App\Models;

use App\Enums\Role;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Asset extends Model
{
    protected $fillable = ['client_id', 'calendar_item_id', 'uploaded_by', 'path', 'original_name', 'mime', 'size'];

    /** Customer sirf apne brand ka; team sab */
    public static function canAccessBrand(User $user, int $clientId): bool
    {
        return $user->role !== Role::Client || $user->client_id === $clientId;
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime, 'image/');
    }

    public function isVideo(): bool
    {
        return str_starts_with($this->mime, 'video/');
    }

    public function sizeLabel(): string
    {
        return $this->size >= 1048576 ? round($this->size / 1048576, 1).' MB' : max(1, round($this->size / 1024)).' KB';
    }

    protected static function booted(): void
    {
        // Record hate toh file bhi hate
        static::deleted(fn (Asset $a) => Storage::disk('local')->delete($a->path));
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(CalendarItem::class, 'calendar_item_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
