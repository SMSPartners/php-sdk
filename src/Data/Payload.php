<?php

namespace SmsPartners\Data;

use DateTimeImmutable;
use SmsPartners\Exceptions\MalformedResponseException;

/**
 * Defensive accessors for API response payloads.
 *
 * Why: production 500s have been caused by raw $data['x'] reads assigning
 * null into typed readonly properties. These helpers fail loudly with a
 * named-key error for required fields and return safe defaults for
 * optional ones, so a missing field gives a clear, catchable exception
 * instead of a TypeError or a silent zero.
 */
final class Payload
{
    /**
     * @param  array<int|string, mixed>  $data
     *
     * @throws MalformedResponseException
     */
    public static function requireInt(array $data, string $key): int
    {
        if (! isset($data[$key])) {
            throw new MalformedResponseException($key, $data);
        }

        return (int) $data[$key];
    }

    /**
     * @param  array<int|string, mixed>  $data
     *
     * @throws MalformedResponseException
     */
    public static function requireString(array $data, string $key): string
    {
        if (! isset($data[$key])) {
            throw new MalformedResponseException($key, $data);
        }

        return (string) $data[$key];
    }

    /**
     * @param  array<int|string, mixed>  $data
     *
     * @throws MalformedResponseException
     */
    public static function requireDateTime(array $data, string $key): DateTimeImmutable
    {
        if (empty($data[$key])) {
            throw new MalformedResponseException($key, $data);
        }

        return new DateTimeImmutable((string) $data[$key]);
    }

    /**
     * @param  array<int|string, mixed>  $data
     */
    public static function optionalString(array $data, string $key): ?string
    {
        return isset($data[$key]) ? (string) $data[$key] : null;
    }

    /**
     * @param  array<int|string, mixed>  $data
     */
    public static function optionalInt(array $data, string $key, int $default = 0): int
    {
        return isset($data[$key]) ? (int) $data[$key] : $default;
    }

    /**
     * @param  array<int|string, mixed>  $data
     */
    public static function optionalBool(array $data, string $key, bool $default = false): bool
    {
        return isset($data[$key]) ? (bool) $data[$key] : $default;
    }

    /**
     * @param  array<int|string, mixed>  $data
     */
    public static function optionalDateTime(array $data, string $key): ?DateTimeImmutable
    {
        return empty($data[$key]) ? null : new DateTimeImmutable((string) $data[$key]);
    }

    /**
     * @param  array<int|string, mixed>  $data
     *
     * @return array<int|string, mixed>
     */
    public static function optionalArray(array $data, string $key): array
    {
        return isset($data[$key]) && is_array($data[$key]) ? $data[$key] : [];
    }
}
