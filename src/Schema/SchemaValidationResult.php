<?php

namespace DiamondDove\SimpleJson\Schema;

/**
 * Immutable outcome of validating a JSON document against a JSON Schema. An
 * empty error list means the document conforms; otherwise each entry is a
 * human-readable message naming the offending data location and keyword.
 */
final readonly class SchemaValidationResult
{
    /**
     * @param  list<string>  $errors  human-readable validation messages, empty when the document conforms
     */
    public function __construct(
        private array $errors,
    ) {
    }

    public function passes(): bool
    {
        return $this->errors === [];
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    /**
     * @return list<string>
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
