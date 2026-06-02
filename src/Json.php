<?php

namespace DiamondDove\SimpleJson;

use DiamondDove\SimpleJson\Exceptions\InvalidJsonException;
use DiamondDove\SimpleJson\Exceptions\JsonMappingException;
use DiamondDove\SimpleJson\Mapping\ObjectMapper;
use DiamondDove\SimpleJson\Validation\ValidationResult;
use DiamondDove\SimpleJson\Validation\Validator;

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

    /**
     * Validate decoded JSON against a set of field rules. The input is decoded
     * via {@see self::parse()}, so malformed JSON raises an InvalidJsonException.
     *
     * @param  array<string, string|list<string>>  $rules
     *
     * @throws InvalidJsonException when the input is not valid JSON
     */
    public static function validate(string $json, array $rules): ValidationResult
    {
        return (new Validator($rules))->validate(self::parse($json));
    }

    /**
     * Hydrate a DTO from decoded JSON data or a raw JSON string. A string source
     * is parsed first (malformed JSON raises an InvalidJsonException), then the
     * decoded value is mapped onto the constructor of $className by parameter name.
     *
     * @template T of object
     *
     * @param  array<array-key, mixed>|string  $source
     * @param  class-string<T>  $className
     * @return T
     *
     * @throws InvalidJsonException when a string source is not valid JSON
     * @throws JsonMappingException when the data cannot be mapped onto $className
     */
    public static function map(array|string $source, string $className): object
    {
        $data = is_string($source) ? self::parse($source)->value() : $source;

        return (new ObjectMapper())->map($data, $className);
    }

    /**
     * Evaluate a full JSONPath expression against a JSON string and return the
     * list of matched values. This is an optional escape hatch backed by the
     * softcreatr/jsonpath package (a dev/suggest dependency, not a hard one):
     * calling it without that package installed raises a MissingDependencyException.
     *
     * @return array<array-key, mixed>
     *
     * @throws \DiamondDove\SimpleJson\Exceptions\MissingDependencyException when softcreatr/jsonpath is not installed
     * @throws InvalidJsonException when the input is not valid JSON
     * @throws \DiamondDove\SimpleJson\Exceptions\JsonQueryException when the expression is not valid JSONPath
     */
    public static function query(string $json, string $jsonPath): array
    {
        return (new \DiamondDove\SimpleJson\JsonPath\JsonPath())->query($json, $jsonPath);
    }
}
