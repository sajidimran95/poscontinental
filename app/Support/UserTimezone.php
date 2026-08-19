<?php

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use DateTimeInterface;
use DateTimeZone;
use Throwable;

/**
 * POS clock follows the browser (USA → US time, Bangladesh → BD time, etc.).
 */
class UserTimezone
{
    public static function name(): string
    {
        $tz = session('pos_tz')
            ?: request()?->cookie('pos_tz')
            ?: request()?->header('X-Timezone')
            ?: (string) (config('app.fallback_timezone') ?: config('app.timezone') ?: 'UTC');

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

    /**
     * Use this workstation's timezone for now(), create/edit stamps, and date displays.
     */
    public static function apply(?string $tz = null): string
    {
        $name = self::sanitize($tz) ?: self::name();

        config(['app.timezone' => $name]);
        date_default_timezone_set($name);

        if (function_exists('session')) {
            try {
                if (session()->isStarted()) {
                    session(['pos_tz' => $name]);
                }
            } catch (Throwable) {
                // Console / early boot has no session.
            }
        }

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
