<?php

namespace App\Models;

use App\Enums\Role;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Asset extends Model
{
    protected $fillable = ['client_id', 'calendar_item_id', 'uploaded_by', 'path', 'original_name', 'mime', 'size'];

    /** Customers: own brand only; team: all brands */
    public static function canAccessBrand(User $user, int $clientId): bool
    {
        return $user->role === Role::Client ? $user->client_id === $clientId : $user->canSeeBrand($clientId);
    }

    /** Rename: the uploader, Admin, or any team member of the brand (a Customer only their own files) */
    public function canRename(User $user): bool
    {
        return self::canAccessBrand($user, $this->client_id)
            && ($this->uploaded_by === $user->id || ($user->role !== Role::Client));
    }

    /** "Diwali poster" + ".jpg" of the stored name: the extension never changes */
    public function extension(): string
    {
        $ext = pathinfo($this->original_name, PATHINFO_EXTENSION);

        return $ext !== '' ? '.'.$ext : '';
    }

    public function baseName(): string
    {
        return pathinfo($this->original_name, PATHINFO_FILENAME);
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
        // Delete the file together with the record
        static::deleted(fn (Asset $a) => Storage::disk('local')->delete(array_filter([$a->path, $a->thumb_path])));
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
