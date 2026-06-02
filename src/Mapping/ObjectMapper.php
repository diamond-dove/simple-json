<?php

namespace DiamondDove\SimpleJson\Mapping;

use DiamondDove\SimpleJson\Exceptions\JsonMappingException;
use DiamondDove\SimpleJson\Mapping\Attributes\ListOf;

/**
 * Hydrates decoded JSON (assoc arrays) into immutable DTOs by matching
 * constructor parameters by name. Strict about builtin scalar types, recurses
 * into nested DTOs and `#[ListOf]` collections, and never lets a reflection or
 * value error escape -- everything is translated to {@see JsonMappingException}.
 */
final class ObjectMapper
{
    /**
     * @template T of object
     *
     * @param  class-string<T>  $className
     * @return T
     *
     * @throws JsonMappingException
     */
    public function map(mixed $data, string $className): object
    {
        if (!$this->isObjectShaped($data)) {
            throw JsonMappingException::topLevelNotObject($className);
        }

        /** @var array<array-key, mixed> $data */
        $reflectionClass = $this->reflectMappable($className);

        $constructor = $reflectionClass->getConstructor();
        $args = [];

        if ($constructor !== null) {
            foreach ($constructor->getParameters() as $parameter) {
                $args[] = $this->resolveParameter($data, $className, $parameter);
            }
        }

        // Argument resolution happened above, OUTSIDE this try, so a nested DTO's
        // JsonMappingException propagates unwrapped. Only the constructor
        // invocation is guarded here.
        try {
            /** @var T $instance */
            $instance = $reflectionClass->newInstanceArgs($args);
        } catch (\Throwable $e) {
            throw JsonMappingException::constructionFailed($className, $e);
        }

        return $instance;
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @param  class-string  $className
     */
    private function resolveParameter(array $data, string $className, \ReflectionParameter $parameter): mixed
    {
        $key = $parameter->getName();

        if ($parameter->isVariadic()) {
            throw JsonMappingException::unsupportedType($className, $key);
        }

        if (!array_key_exists($key, $data)) {
            if ($parameter->isDefaultValueAvailable()) {
                return $parameter->getDefaultValue();
            }

            if ($parameter->allowsNull()) {
                return null;
            }

            throw JsonMappingException::missingRequired($className, $key);
        }

        $value = $data[$key];

        if ($value === null) {
            if ($parameter->allowsNull()) {
                return null;
            }

            throw JsonMappingException::typeMismatch($className, $key, $this->expectedLabel($parameter), 'null');
        }

        return $this->resolveValue($value, $className, $parameter);
    }

    /**
     * @param  class-string  $className
     */
    private function resolveValue(mixed $value, string $className, \ReflectionParameter $parameter): mixed
    {
        $key = $parameter->getName();

        $listOf = $this->listOfAttribute($parameter);
        if ($listOf !== null) {
            return $this->resolveList($value, $className, $key, $listOf->type);
        }

        $type = $parameter->getType();

        // No type declaration: accept any present value as-is.
        if ($type === null) {
            return $value;
        }

        if (!$type instanceof \ReflectionNamedType) {
            throw JsonMappingException::unsupportedType($className, $key);
        }

        return $this->resolveTyped($value, $className, $key, $type);
    }

    /**
     * @param  class-string  $className
     */
    private function resolveTyped(mixed $value, string $className, string $key, \ReflectionNamedType $type): mixed
    {
        $typeName = $type->getName();

        // `mixed` accepts any present value as-is.
        if ($typeName === 'mixed') {
            return $value;
        }

        if ($type->isBuiltin()) {
            return $this->resolveBuiltin($value, $className, $key, $typeName);
        }

        if (is_subclass_of($typeName, \BackedEnum::class)) {
            return $this->resolveEnum($value, $className, $key, $typeName);
        }

        if (enum_exists($typeName)) {
            // Non-backed (pure) enum cannot be hydrated from a scalar.
            throw JsonMappingException::unsupportedType($className, $key);
        }

        if (class_exists($typeName)) {
            if (!$this->isObjectShaped($value)) {
                throw JsonMappingException::nestedNotObject($className, $key);
            }

            /** @var class-string $typeName */
            return $this->map($value, $typeName);
        }

        throw JsonMappingException::unsupportedType($className, $key);
    }

    /**
     * @param  class-string  $className
     */
    private function resolveBuiltin(mixed $value, string $className, string $key, string $typeName): mixed
    {
        switch ($typeName) {
            case 'int':
                if (!is_int($value)) {
                    throw JsonMappingException::typeMismatch($className, $key, 'int', get_debug_type($value));
                }

                return $value;
            case 'float':
                if (!is_int($value) && !is_float($value)) {
                    throw JsonMappingException::typeMismatch($className, $key, 'float', get_debug_type($value));
                }

                return (float) $value;
            case 'string':
                if (!is_string($value)) {
                    throw JsonMappingException::typeMismatch($className, $key, 'string', get_debug_type($value));
                }

                return $value;
            case 'bool':
                if (!is_bool($value)) {
                    throw JsonMappingException::typeMismatch($className, $key, 'bool', get_debug_type($value));
                }

                return $value;
            case 'array':
                if (!is_array($value)) {
                    throw JsonMappingException::typeMismatch($className, $key, 'array', get_debug_type($value));
                }

                return $value;
            default:
                throw JsonMappingException::unsupportedType($className, $key);
        }
    }

    /**
     * @param  class-string  $className
     */
    private function resolveEnum(mixed $value, string $className, string $key, string $enumName): \BackedEnum
    {
        if (!is_int($value) && !is_string($value)) {
            throw JsonMappingException::typeMismatch($className, $key, $enumName, get_debug_type($value));
        }

        /** @var class-string<\BackedEnum> $enumName */
        $case = $enumName::tryFrom($value);

        if ($case === null) {
            throw JsonMappingException::invalidEnum($className, $key, $enumName, $value);
        }

        return $case;
    }

    /**
     * @param  class-string  $className
     * @return list<mixed>
     */
    private function resolveList(mixed $value, string $className, string $key, string $elementType): array
    {
        if (!$this->isList($value)) {
            throw JsonMappingException::notAList($className, $key);
        }

        /** @var array<int, mixed> $value */
        $result = [];
        $index = 0;

        foreach ($value as $element) {
            $result[] = $this->resolveListElement($element, $className, $key, $index, $elementType);
            $index++;
        }

        return $result;
    }

    /**
     * @param  class-string  $className
     */
    private function resolveListElement(mixed $element, string $className, string $key, int $index, string $elementType): mixed
    {
        if (class_exists($elementType) && !enum_exists($elementType)) {
            if (!$this->isObjectShaped($element)) {
                throw JsonMappingException::listElementInvalid($className, $key, $index);
            }

            /** @var class-string $elementType */
            return $this->map($element, $elementType);
        }

        if (is_subclass_of($elementType, \BackedEnum::class)) {
            return $this->resolveEnum($element, $className, $key, $elementType);
        }

        return $this->resolveBuiltinElement($element, $className, $key, $index, $elementType);
    }

    /**
     * @param  class-string  $className
     */
    private function resolveBuiltinElement(mixed $element, string $className, string $key, int $index, string $elementType): mixed
    {
        $ok = match ($elementType) {
            'int' => is_int($element),
            'float' => is_int($element) || is_float($element),
            'string' => is_string($element),
            'bool' => is_bool($element),
            'array' => is_array($element),
            default => false,
        };

        if (!$ok) {
            throw JsonMappingException::listElementInvalid($className, $key, $index);
        }

        return $elementType === 'float' ? (float) $element : $element;
    }

    /**
     * @param  class-string  $className
     * @return \ReflectionClass<object>
     */
    private function reflectMappable(string $className): \ReflectionClass
    {
        if (!class_exists($className)) {
            throw JsonMappingException::notMappable($className);
        }

        $reflectionClass = new \ReflectionClass($className);

        if ($reflectionClass->isAbstract() || $reflectionClass->isInterface() || $reflectionClass->isEnum() || !$reflectionClass->isInstantiable()) {
            throw JsonMappingException::notMappable($className);
        }

        $constructor = $reflectionClass->getConstructor();
        if ($constructor !== null && !$constructor->isPublic()) {
            throw JsonMappingException::notMappable($className);
        }

        return $reflectionClass;
    }

    private function listOfAttribute(\ReflectionParameter $parameter): ?ListOf
    {
        $attributes = $parameter->getAttributes(ListOf::class);

        if ($attributes === []) {
            return null;
        }

        return $attributes[0]->newInstance();
    }

    private function expectedLabel(\ReflectionParameter $parameter): string
    {
        $type = $parameter->getType();

        if ($type instanceof \ReflectionNamedType) {
            return $type->getName();
        }

        return 'non-null value';
    }

    /**
     * An "object-shaped array": an associative array, or the empty array treated
     * as an empty object.
     */
    private function isObjectShaped(mixed $value): bool
    {
        return is_array($value) && ($value === [] || !array_is_list($value));
    }

    /**
     * A list: a sequential array, or the empty array treated as an empty list.
     */
    private function isList(mixed $value): bool
    {
        return is_array($value) && ($value === [] || array_is_list($value));
    }
}
