<?php

namespace App\Services\RouterOs;

use Illuminate\Support\Carbon;

class RouterOsValueParser
{
    public static function boolean(mixed $value): bool
    {
        return in_array(strtolower((string) $value), ['true', 'yes', '1', 'on', 'running', 'active', 'bound'], true);
    }

    public static function integer(mixed $value): int
    {
        return max(0, (int) preg_replace('/[^0-9-]/', '', (string) $value));
    }

    public static function decimal(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! preg_match('/-?[0-9]+(?:\.[0-9]+)?/', (string) $value, $matches)) {
            return null;
        }

        return (float) $matches[0];
    }

    public static function durationSeconds(mixed $value): ?int
    {
        $value = strtolower(trim((string) $value));
        if ($value === '') {
            return null;
        }

        $units = ['w' => 604800, 'd' => 86400, 'h' => 3600, 'm' => 60, 's' => 1];
        $seconds = 0.0;
        $matched = false;
        foreach ($units as $unit => $multiplier) {
            if (preg_match('/([0-9]+(?:\.[0-9]+)?)'.$unit.'/', $value, $matches)) {
                $seconds += (float) $matches[1] * $multiplier;
                $matched = true;
            }
        }

        return $matched ? (int) round($seconds) : null;
    }

    public static function dateTime(mixed $value): ?Carbon
    {
        $value = trim((string) $value);
        if ($value === '' || $value === 'never') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
