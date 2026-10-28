<?php

namespace App\Support;

use App\Enums\Role;
use App\Models\User;
use App\Notifications\WorkflowNotification;
use Illuminate\Support\Collection;

class Notify
{
    /**
     * Notify users (skipping the actor and inactive users).
     * If email fails (SMTP not set) the request must not break: in-app is stored first, mail errors are only logged.
     */
    public static function send(iterable $users, string $title, string $message, string $url): void
    {
        $actorId = auth()->id();

        collect($users)->filter()->unique('id')
            ->reject(fn (User $u) => $u->id === $actorId || ! $u->is_active)
            ->each(function (User $user) use ($title, $message, $url) {
                try {
                    $user->notify(new WorkflowNotification($title, $message, $url));
                } catch (\Throwable $e) {
                    report($e);
                }
            });
    }

    public static function role(Role ...$roles): Collection
    {
        return User::whereIn('role', $roles)->where('is_active', true)->get();
    }

    /** "@TeamMember" => that user (name without spaces, case-insensitive) */
    public static function mentioned(?string $text): Collection
    {
        preg_match_all('/@([\pL\pN_.]+)/u', (string) $text, $m);
        if (empty($m[1])) {
            return collect();
        }
        $handles = array_map('mb_strtolower', $m[1]);

        // shortcut: matches all staff users in PHP; add a DB column if the team grows past ~1000
        return User::where('role', '!=', Role::Client)->where('is_active', true)->get()
            ->filter(fn (User $u) => in_array($u->handle(), $handles, true));
    }
}
