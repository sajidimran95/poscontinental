<?php

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use DateTimeZone;
use Throwable;

/**
 * Clock for the person using the POS (browser timezone).
 * Database instants stay UTC; chat stamps keep the user's offset.
 */
class UserTimezone
{
    public static function name(): string
    {
        $tz = session('pos_tz')
            ?: request()?->cookie('pos_tz')
            ?: (string) (config('app.timezone') ?: 'UTC');

        return self::sanitize($tz) ?: 'UTC';
    }

    public static function sanitize(mixed $tz): ?string
    {
        $tz = is_string($tz) ? trim($tz) : '';
        if ($tz === '' || strlen($tz) > 64) {
            return null;
        }

        try {
            new DateTimeZone($tz);

            return $tz;
        } catch (Throwable) {
            return null;
        }
    }

    public static function now(): CarbonInterface
    {
        return Carbon::now(self::name());
    }

    public static function format(mixed $dt, string $format = 'n/j/Y g:i A'): string
    {
        if ($dt === null || $dt === '') {
            return '';
        }

        try {
            $parsed = $dt instanceof CarbonInterface
                ? $dt->copy()
                : Carbon::parse($dt);

            return $parsed->timezone(self::name())->format($format);
        } catch (Throwable) {
            return '';
        }
    }
}
