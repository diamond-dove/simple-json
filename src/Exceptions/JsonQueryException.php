<?php

namespace DiamondDove\SimpleJson\Exceptions;

/**
 * Raised when a JSONPath expression cannot be evaluated. The underlying
 * third-party parse error is preserved as the previous exception, while the
 * public type stays inside this package's {@see JsonException} hierarchy so no
 * vendor exception type leaks out of the facade.
 */
final class JsonQueryException extends \RuntimeException implements JsonException
{
    public static function invalidExpression(string $expression, \Throwable $previous): self
    {
        return new self(
            "Invalid JSONPath expression: \"{$expression}\".",
            0,
            $previous,
        );
    }
}
