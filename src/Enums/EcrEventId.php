<?php

declare(strict_types=1);

namespace QrCommunication\VivaIsv\Enums;

/**
 * Cloud Terminal API event IDs.
 *
 * Returned in the session poll response as `eventId`.
 */
enum EcrEventId: int
{
    case SUCCESS = 0;
    case TERMINAL_TIMEOUT = 1003;
    case DECLINED = 1006;
    case ABORTED = 1016;
    case INSUFFICIENT_FUNDS = 1020;
    case GENERIC_ERROR = 1099;
    case IN_PROGRESS = 1100;
    case BAD_PARAMS = 6000;

    public function isTerminal(): bool
    {
        return $this !== self::IN_PROGRESS;
    }

    public function isSuccessful(): bool
    {
        return $this === self::SUCCESS;
    }

    public function shouldPoll(): bool
    {
        return $this === self::IN_PROGRESS;
    }

    public function label(): string
    {
        return match ($this) {
            self::SUCCESS => 'Transaction successful',
            self::TERMINAL_TIMEOUT => 'Terminal timed out',
            self::DECLINED => 'Transaction declined',
            self::ABORTED => 'Transaction aborted',
            self::INSUFFICIENT_FUNDS => 'Insufficient funds',
            self::GENERIC_ERROR => 'Generic error',
            self::IN_PROGRESS => 'In progress',
            self::BAD_PARAMS => 'Bad parameters',
        };
    }
}
