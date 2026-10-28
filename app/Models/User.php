<?php

namespace App\Models;

use App\Enums\Permission;
use App\Enums\Role;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'client_id',
        'is_active',
    ];

    /**
     * Default attribute values.
     */
    protected $attributes = [
        'role' => 'graphic_designer',
        'is_active' => true,
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'is_active' => 'boolean',
        ];
    }

    /** Members who can get tasks: active Designers/Editors (or only the given roles) */
    public static function creators(?array $roles = null)
    {
        return static::whereIn('role', $roles ?? [Role::GraphicDesigner, Role::VideoEditor])
            ->where('is_active', true)->orderBy('name')->get(['id', 'name', 'role']);
    }

    /** Permissions loaded once per request (role + extra) */
    private ?array $permissionCache = null;

    public function hasPermission(Permission $permission): bool
    {
        if ($this->isAdmin()) {
            return true; // Admin always has everything, so nobody gets locked out
        }
        if ($this->role === Role::Client) {
            return false; // Customers never get internal permissions
        }

        $this->permissionCache ??= DB::table('role_permissions')->where('role', $this->role->value)->pluck('permission')
            ->merge(DB::table('user_permissions')->where('user_id', $this->id)->pluck('permission'))
            ->unique()->all();

        return in_array($permission->value, $this->permissionCache, true);
    }

    public function extraPermissions(): array
    {
        return DB::table('user_permissions')->where('user_id', $this->id)->pluck('permission')->all();
    }

    /** Active non-customer users that have the permission (via role or extra) */
    public static function withPermission(Permission $permission)
    {
        $roles = DB::table('role_permissions')->where('permission', $permission->value)->pluck('role')->push(Role::Admin->value);
        $ids = DB::table('user_permissions')->where('permission', $permission->value)->pluck('user_id');

        return static::where('is_active', true)->where('role', '!=', Role::Client)
            ->where(fn ($q) => $q->whereIn('role', $roles)->orWhereIn('id', $ids))
            ->orderBy('name')->get(['id', 'name', 'role']);
    }

    /** @mention handle: "Team Member" => "teammember" */
    public function handle(): string
    {
        return mb_strtolower(preg_replace('/\s+/u', '', $this->name));
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function hasRole(Role|string ...$roles): bool
    {
        foreach ($roles as $role) {
            $value = $role instanceof Role ? $role->value : $role;
            if ($this->role?->value === $value) {
                return true;
            }
        }

        return false;
    }

    public function isAdmin(): bool
    {
        return $this->role === Role::Admin;
    }
}
