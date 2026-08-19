<?php

use App\Support\UserTimezone;

if (! function_exists('user_time')) {
    function user_time(mixed $dt, string $format = 'n/j/Y g:i:s A'): string
    {
        $formatted = UserTimezone::format($dt, $format);

        return $formatted !== '' ? $formatted : '—';
    }
}

if (! function_exists('user_now')) {
    function user_now(): \Carbon\CarbonInterface
    {
        return UserTimezone::now();
    }
}
