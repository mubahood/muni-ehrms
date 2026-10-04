<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\SystemNotice;
use Illuminate\Support\Facades\Log;

/**
 * Sends a SystemNotice to one or more people. A failure to deliver is logged
 * and never undoes the action that caused it.
 */
class Notifier
{
    /**
     * @param  User|iterable<User>|null  $people
     */
    public static function send($people, string $title, string $body, ?string $url = null, bool $email = true): void
    {
        if ($people === null) {
            return;
        }
        $people = $people instanceof User ? [$people] : $people;
        foreach ($people as $person) {
            try {
                // Demo accounts get the in-app notice only, never an e-mail.
                $person->notify(new SystemNotice($title, $body, $url, $email && !$person->is_demo));
            } catch (\Throwable $e) {
                Log::error('Notification failed', ['user_id' => $person->id, 'title' => $title, 'error' => $e->getMessage()]);
            }
        }
    }
}
