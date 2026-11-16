<?php

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use DateTimeInterface;
use DateTimeZone;
use Throwable;

/**
 * Company clock — always APP_TIMEZONE (America/New_York for Continental).
 * Browser timezone is ignored so every PC shows the same Eastern time.
 */
class UserTimezone
{
    public static function name(): string
    {
        $tz = (string) (config('app.timezone')
            ?: config('app.fallback_timezone')
            ?: 'America/New_York');

        return self::sanitize($tz) ?: 'America/New_York';
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

    /**
     * Lock PHP / Carbon to the company timezone.
     */
    public static function apply(?string $tz = null): string
    {
        $name = self::sanitize($tz) ?: self::name();

        config(['app.timezone' => $name]);
        date_default_timezone_set($name);

        return $name;
    }

    public static function now(): CarbonInterface
    {
        return Carbon::now(self::name());
    }

    public static function format(mixed $dt, string $format = 'n/j/Y g:i:s A'): string
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

    public static function toDateTimeLocal(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        try {
            $dt = $value instanceof DateTimeInterface
                ? Carbon::parse($value)
                : Carbon::parse((string) $value);

            return $dt->timezone(self::name())->format('Y-m-d\TH:i:s');
        } catch (Throwable) {
            return '';
        }
    }

    public static function fromDateTimeLocal(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        return Carbon::parse($value, self::name())->format('Y-m-d H:i:s');
    }
}
