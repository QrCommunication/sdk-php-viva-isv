<?php

declare(strict_types=1);

namespace QrCommunication\VivaIsv\Exceptions;

class AuthenticationException extends VivaException
{
    public function __construct(string $message = 'Viva Wallet ISV authentication failed', ?\Throwable $previous = null)
    {
        parent::__construct($message, 401, null, $previous);
    }
}
