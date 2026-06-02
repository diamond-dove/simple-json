<?php

namespace DiamondDove\SimpleJson\Exceptions;

/**
 * Raised when opis/json-schema cannot process a schema -- a value that parsed
 * as JSON but is not a usable schema (unknown keyword, invalid type, an
 * unresolvable remote {@code $ref}, etc.). This is distinct from a document
 * being *invalid against a valid schema*, which is reported as a
 * {@see \DiamondDove\SimpleJson\Schema\SchemaValidationResult} rather than an
 * exception. The underlying opis error is preserved as the previous exception so
 * no vendor type leaks out of this package's {@see JsonException} hierarchy.
 */
final class JsonSchemaException extends \RuntimeException implements JsonException
{
    public static function invalidSchema(\Throwable $previous): self
    {
        return new self(
            "Invalid JSON Schema: {$previous->getMessage()}",
            0,
            $previous,
        );
    }
}
