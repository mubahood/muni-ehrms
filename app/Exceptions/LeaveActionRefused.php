<?php

namespace App\Exceptions;

/**
 * A leave action that cannot be done, with a message for the person who tried.
 * forbidden() marks the cases where they are not allowed to do it at all.
 */
class LeaveActionRefused extends \RuntimeException
{
    public bool $forbidden = false;

    public static function forbidden(string $message): self
    {
        $e = new self($message);
        $e->forbidden = true;

        return $e;
    }
}
