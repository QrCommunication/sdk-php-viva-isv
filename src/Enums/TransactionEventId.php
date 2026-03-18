<?php

declare(strict_types=1);

namespace QrCommunication\VivaIsv\Enums;

/**
 * Transaction decline reason codes.
 *
 * Returned as `transactionEventId` in session poll responses
 * when eventId indicates a decline.
 */
enum TransactionEventId: int
{
    case REFER_TO_ISSUER = 10001;
    case INVALID_MERCHANT = 10003;
    case PICKUP_CARD = 10004;
    case DO_NOT_HONOR = 10005;
    case GENERAL_ERROR = 10006;
    case INVALID_TRANSACTION = 10012;
    case INVALID_AMOUNT = 10013;
    case INVALID_CARD = 10014;
    case FORMAT_ERROR = 10030;
    case LOST_CARD = 10041;
    case STOLEN_CARD = 10043;
    case INSUFFICIENT_FUNDS = 10051;
    case EXPIRED_CARD = 10054;
    case INCORRECT_PIN = 10055;
    case NOT_PERMITTED_CARDHOLDER = 10057;
    case NOT_PERMITTED_TERMINAL = 10058;
    case WITHDRAWAL_LIMIT = 10061;
    case RESTRICTED_CARD = 10062;
    case SECURITY_VIOLATION = 10063;
    case ACTIVITY_LIMIT = 10065;
    case LATE_RESPONSE = 10068;
    case CALL_ISSUER = 10070;
    case PIN_TRIES_EXCEEDED = 10075;
    case UNMAPPED = 10200;

    public function label(): string
    {
        return match ($this) {
            self::INSUFFICIENT_FUNDS => 'Insufficient funds',
            self::EXPIRED_CARD => 'Expired card',
            self::STOLEN_CARD => 'Stolen card',
            self::NOT_PERMITTED_CARDHOLDER => 'Card not permitted',
            self::WITHDRAWAL_LIMIT => 'Withdrawal limit exceeded',
            self::GENERAL_ERROR => 'General error',
            self::INVALID_CARD => 'Invalid card',
            self::UNMAPPED => 'Unmapped decline',
            default => "Decline code {$this->value}",
        };
    }

    /**
     * Test amounts that trigger each decline in demo environment.
     *
     * @return int Amount in cents that triggers this decline
     */
    public function testAmount(): int
    {
        return match ($this) {
            self::INSUFFICIENT_FUNDS => 9951,
            self::EXPIRED_CARD => 9954,
            self::STOLEN_CARD => 9920,
            self::NOT_PERMITTED_CARDHOLDER => 9957,
            self::WITHDRAWAL_LIMIT => 9961,
            self::GENERAL_ERROR => 9906,
            self::INVALID_CARD => 9914,
            default => 0,
        };
    }
}
