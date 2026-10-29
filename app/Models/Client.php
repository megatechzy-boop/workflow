<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    protected $fillable = ['name', 'contact_email', 'phone', 'address', 'notes', 'is_active', 'package_id', 'package_start', 'onboarding_token', 'onboarding', 'onboarded_at'];

    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'package_start' => 'date', 'onboarding' => 'array', 'onboarded_at' => 'datetime'];
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** Team members who work on this brand (Admin > Users > Brands) */
    public function team(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }
}
