<?php

namespace App\Exceptions;

use RuntimeException;

class InvalidStatusTransitionException extends RuntimeException
{
    public function __construct(string $from, string $to, ?string $reason = null)
    {
        $message = "Invalid status transition from '{$from}' to '{$to}'.";

        if ($reason) {
            $message .= " {$reason}";
        }

        parent::__construct($message);
    }
}
