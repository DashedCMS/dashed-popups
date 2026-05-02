<?php

namespace Dashed\DashedPopups\Exceptions;

use RuntimeException;

class NewsletterRateLimitException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $retryAfter = 60,
    ) {
        parent::__construct($message);
    }
}
