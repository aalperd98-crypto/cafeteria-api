<?php

namespace App\Exceptions;

use Exception;

class EntryValidationException extends Exception
{
    public function __construct(string $message, protected int $statusCode = 422)
    {
        parent::__construct($message);
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    public static function turnstileInactive(): self
    {
        return new self('This turnstile is not currently active.', 422);
    }

    public static function invalidQrCode(): self
    {
        return new self('The QR code is invalid, expired, or does not match this turnstile.', 422);
    }

    public static function globalLimitExceeded(): self
    {
        return new self('The global daily entry limit has been reached. Please try again tomorrow.', 429);
    }

    public static function perUserLimitExceeded(): self
    {
        return new self('You have reached your maximum of 2 entries for today.', 429);
    }
}
