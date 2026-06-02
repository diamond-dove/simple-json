<?php

namespace DiamondDove\SimpleJson\Exceptions;

final class JsonValidationException extends \RuntimeException implements JsonException
{
    /**
     * @param  array<string, list<string>>  $errors
     */
    public function __construct(
        private readonly array $errors,
        string $message = 'The given data was invalid.',
    ) {
        parent::__construct($message);
    }

    /**
     * @return array<string, list<string>>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
