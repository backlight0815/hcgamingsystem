<?php

namespace App\Support;

class BackofficeExperience
{
    public const DEALERSHIP = 'dealership';
    public const TRADING = 'trading';
    public const EXISTING = 'existing';

    public static function mode(): string
    {
        $settings = FrontendExperience::settings();

        return self::forModules($settings[FrontendExperience::ECOMMERCE], $settings[FrontendExperience::TRADING]);
    }

    public static function forModules(bool $ecommerce, bool $trading): string
    {
        if ($ecommerce && ! $trading) {
            return self::DEALERSHIP;
        }

        if ($trading && ! $ecommerce) {
            return self::TRADING;
        }

        return self::EXISTING;
    }
}
