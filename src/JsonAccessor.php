<?php

namespace DiamondDove\SimpleJson;

use DiamondDove\SimpleJson\Exceptions\JsonTypeException;
use Illuminate\Support\Arr;

/**
 * Immutable accessor over a decoded JSON value. Navigation (path) and type
 * coercion (terminals) always return new instances and never mutate state.
 */
final class JsonAccessor
{
    private function __construct(
        private readonly mixed $value,
        private readonly bool $exists,
    ) {
    }

    public static function of(mixed $value): self
    {
        return new self($value, true);
    }

    public static function missing(): self
    {
        return new self(null, false);
    }

    /**
     * Navigate into the value using a dot path. The path is passed verbatim and
     * is case-sensitive. Returns a missing accessor when the value cannot be
     * traversed or the key is absent. Always returns a new instance.
     */
    public function path(string $path): self
    {
        if (!$this->exists) {
            return self::missing();
        }

        if (!is_array($this->value)) {
            return self::missing();
        }

        return Arr::has($this->value, $path)
            ? self::of(Arr::get($this->value, $path))
            : self::missing();
    }

    /**
     * Whether the path resolved to a value, distinguishing present-but-null from
     * missing. Returns true when a value was reached (even a JSON null); returns
     * false when the path was absent or the root was non-navigable.
     */
    public function isPresent(): bool
    {
        return $this->exists;
    }

    public function string(): string
    {
        /** @var string */
        return $this->strict('string', static fn (mixed $v): bool => is_string($v), static fn (mixed $v): mixed => $v);
    }

    public function int(): int
    {
        /** @var int */
        return $this->strict('int', static fn (mixed $v): bool => is_int($v), static fn (mixed $v): mixed => $v);
    }

    public function float(): float
    {
        /** @var float */
        return $this->strict('float', static fn (mixed $v): bool => is_int($v) || is_float($v), static fn (mixed $v): mixed => (float) $v);
    }

    public function bool(): bool
    {
        /** @var bool */
        return $this->strict('bool', static fn (mixed $v): bool => is_bool($v), static fn (mixed $v): mixed => $v);
    }

    /**
     * @return array<array-key, mixed>
     */
    public function array(): array
    {
        /** @var array<array-key, mixed> */
        return $this->strict('array', static fn (mixed $v): bool => is_array($v), static fn (mixed $v): mixed => $v);
    }

    public function stringOr(string $default): string
    {
        /** @var string */
        return $this->orDefault(static fn (mixed $v): bool => is_string($v), static fn (mixed $v): mixed => $v, $default);
    }

    public function intOr(int $default): int
    {
        /** @var int */
        return $this->orDefault(static fn (mixed $v): bool => is_int($v), static fn (mixed $v): mixed => $v, $default);
    }

    public function floatOr(float $default): float
    {
        /** @var float */
        return $this->orDefault(static fn (mixed $v): bool => is_int($v) || is_float($v), static fn (mixed $v): mixed => (float) $v, $default);
    }

    public function boolOr(bool $default): bool
    {
        /** @var bool */
        return $this->orDefault(static fn (mixed $v): bool => is_bool($v), static fn (mixed $v): mixed => $v, $default);
    }

    /**
     * @param  array<array-key, mixed>  $default
     * @return array<array-key, mixed>
     */
    public function arrayOr(array $default): array
    {
        /** @var array<array-key, mixed> */
        return $this->orDefault(static fn (mixed $v): bool => is_array($v), static fn (mixed $v): mixed => $v, $default);
    }

    public function stringOrNull(): ?string
    {
        /** @var string|null */
        return $this->orDefault(static fn (mixed $v): bool => is_string($v), static fn (mixed $v): mixed => $v, null);
    }

    public function intOrNull(): ?int
    {
        /** @var int|null */
        return $this->orDefault(static fn (mixed $v): bool => is_int($v), static fn (mixed $v): mixed => $v, null);
    }

    public function floatOrNull(): ?float
    {
        /** @var float|null */
        return $this->orDefault(static fn (mixed $v): bool => is_int($v) || is_float($v), static fn (mixed $v): mixed => (float) $v, null);
    }

    public function boolOrNull(): ?bool
    {
        /** @var bool|null */
        return $this->orDefault(static fn (mixed $v): bool => is_bool($v), static fn (mixed $v): mixed => $v, null);
    }

    /**
     * @return array<array-key, mixed>|null
     */
    public function arrayOrNull(): ?array
    {
        /** @var array<array-key, mixed>|null */
        return $this->orDefault(static fn (mixed $v): bool => is_array($v), static fn (mixed $v): mixed => $v, null);
    }

    /**
     * @param  callable(mixed): bool  $accept
     * @param  callable(mixed): mixed  $transform
     */
    private function strict(string $type, callable $accept, callable $transform): mixed
    {
        if (!$this->exists || !$accept($this->value)) {
            throw JsonTypeException::expected($type);
        }

        return $transform($this->value);
    }

    /**
     * @param  callable(mixed): bool  $accept
     * @param  callable(mixed): mixed  $transform
     */
    private function orDefault(callable $accept, callable $transform, mixed $default): mixed
    {
        if (!$this->exists || !$accept($this->value)) {
            return $default;
        }

        return $transform($this->value);
    }
}
