<?php

namespace DiamondDove\SimpleJson\Exceptions;

final class JsonTypeException extends \RuntimeException implements JsonException
{
    public static function expected(string $type): self
    {
        return new self("Expected value of type {$type}.");
    }
}
