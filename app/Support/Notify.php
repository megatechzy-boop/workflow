<?php

namespace App\Support;

use App\Enums\Role;
use App\Models\User;
use App\Notifications\WorkflowNotification;
use Illuminate\Support\Collection;

class Notify
{
    /**
     * Users ko notification bhejo (khud ko aur inactive ko chhod kar).
     * Email fail ho (SMTP set nahi) toh bhi request na toote: in-app pehle save hota hai, mail error sirf log hota hai.
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

    /** "@TeamMember" => us user ko (naam bina space ke, case ignore) */
    public static function mentioned(?string $text): Collection
    {
        preg_match_all('/@([\pL\pN_.]+)/u', (string) $text, $m);
        if (empty($m[1])) {
            return collect();
        }
        $handles = array_map('mb_strtolower', $m[1]);

        // shortcut: saare staff users PHP mein match karte hain; team bahut badi ho (1000+) tab DB column banana
        return User::where('role', '!=', Role::Client)->where('is_active', true)->get()
            ->filter(fn (User $u) => in_array($u->handle(), $handles, true));
    }
}
