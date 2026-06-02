<?php

namespace DiamondDove\SimpleJson\Exceptions;

/**
 * Raised when decoded JSON cannot be hydrated into the requested DTO. The
 * messages name the target class, the offending parameter, and (where relevant)
 * the expected/actual types or list index, so failures are self-describing.
 */
final class JsonMappingException extends \RuntimeException implements JsonException
{
    public static function topLevelNotObject(string $class): self
    {
        return new self(
            "Cannot map {$class}: the top-level value must be an object-shaped array. "
            . 'Got a list or scalar; map rows individually for list input.',
        );
    }

    public static function notMappable(string $class): self
    {
        return new self(
            "Cannot map {$class}: the class must exist and be a concrete, instantiable "
            . 'class with a public constructor.',
        );
    }

    public static function missingRequired(string $class, string $param): self
    {
        return new self(
            "Cannot map {$class}: required parameter \${$param} is absent and has no "
            . 'default value and is not nullable.',
        );
    }

    public static function typeMismatch(string $class, string $param, string $expected, string $actual): self
    {
        return new self(
            "Cannot map {$class}: parameter \${$param} expected {$expected}, got {$actual}.",
        );
    }

    public static function nestedNotObject(string $class, string $param): self
    {
        return new self(
            "Cannot map {$class}: parameter \${$param} expected a nested object, "
            . 'got a non-object value.',
        );
    }

    public static function invalidEnum(string $class, string $param, string $enum, int|string $value): self
    {
        $printable = is_int($value) ? (string) $value : "'{$value}'";

        return new self(
            "Cannot map {$class}: parameter \${$param} value {$printable} is not a valid "
            . "case of enum {$enum}.",
        );
    }

    public static function notAList(string $class, string $param): self
    {
        return new self(
            "Cannot map {$class}: parameter \${$param} marked #[ListOf] expected a list, "
            . 'got a non-list value.',
        );
    }

    public static function listElementInvalid(string $class, string $param, int $index): self
    {
        return new self(
            "Cannot map {$class}: parameter \${$param} list element at index {$index} is "
            . 'not a valid object for the declared element type.',
        );
    }

    public static function unsupportedType(string $class, string $param): self
    {
        return new self(
            "Cannot map {$class}: parameter \${$param} has an unsupported type "
            . '(union, intersection, variadic, or non-backed enum).',
        );
    }

    public static function constructionFailed(string $class, \Throwable $previous): self
    {
        return new self(
            "Failed to construct {$class}: {$previous->getMessage()}",
            0,
            $previous,
        );
    }
}
