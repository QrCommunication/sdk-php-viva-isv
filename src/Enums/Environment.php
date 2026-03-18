<?php

declare(strict_types=1);

namespace QrCommunication\VivaIsv\Enums;

enum Environment: string
{
    case DEMO = 'demo';
    case PRODUCTION = 'production';

    public function accountsUrl(): string
    {
        return match ($this) {
            self::DEMO => 'https://demo-accounts.vivapayments.com',
            self::PRODUCTION => 'https://accounts.vivapayments.com',
        };
    }

    public function apiUrl(): string
    {
        return match ($this) {
            self::DEMO => 'https://demo-api.vivapayments.com',
            self::PRODUCTION => 'https://api.vivapayments.com',
        };
    }

    public function legacyUrl(): string
    {
        return match ($this) {
            self::DEMO => 'https://demo.vivapayments.com',
            self::PRODUCTION => 'https://www.vivapayments.com',
        };
    }

    public function checkoutUrl(): string
    {
        return $this->legacyUrl().'/web/checkout';
    }
}
