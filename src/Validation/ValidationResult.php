<?php

namespace DiamondDove\SimpleJson\Validation;

use DiamondDove\SimpleJson\Exceptions\JsonValidationException;

/**
 * The immutable outcome of a validation run: the per-field error messages and
 * the validated subset of present, rule-named fields.
 */
final readonly class ValidationResult
{
    /**
     * @param  array<string, list<string>>  $errors
     * @param  array<string, mixed>  $validated
     */
    public function __construct(
        private array $errors,
        private array $validated,
    ) {
    }

    public function passes(): bool
    {
        return $this->errors === [];
    }

    public function fails(): bool
    {
        return !$this->passes();
    }

    /**
     * @return array<string, list<string>>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * The validated subset of fields, keyed by field path.
     *
     * @return array<string, mixed>
     *
     * @throws JsonValidationException when validation failed
     */
    public function validated(): array
    {
        if ($this->fails()) {
            throw new JsonValidationException($this->errors);
        }

        return $this->validated;
    }
}
