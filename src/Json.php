<?php

namespace DiamondDove\SimpleJson;

use DiamondDove\SimpleJson\Exceptions\InvalidJsonException;

/**
 * Static-only facade for safely decoding JSON into a navigable accessor.
 */
final class Json
{
    private function __construct()
    {
    }

    /**
     * @throws InvalidJsonException when the input is not valid JSON
     */
    public static function parse(string $json): JsonAccessor
    {
        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new InvalidJsonException('Invalid JSON: ' . $e->getMessage(), 0, $e);
        }

        return JsonAccessor::of($decoded);
    }

    public static function tryParse(string $json): ?JsonAccessor
    {
        try {
            return self::parse($json);
        } catch (InvalidJsonException) {
            return null;
        }
    }
}
