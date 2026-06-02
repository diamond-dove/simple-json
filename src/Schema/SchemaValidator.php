<?php

namespace DiamondDove\SimpleJson\Schema;

use DiamondDove\SimpleJson\Exceptions\InvalidJsonException;
use DiamondDove\SimpleJson\Exceptions\JsonSchemaException;
use DiamondDove\SimpleJson\Exceptions\MissingDependencyException;
use DiamondDove\SimpleJson\Json;

/**
 * Optional adapter wrapping opis/json-schema. It validates a JSON document
 * against a JSON Schema (both supplied as strings) and returns a structured
 * {@see SchemaValidationResult} of human-readable messages.
 *
 * The third-party class is only referenced inside the guarded method body, so
 * autoloading this adapter never fails when the optional package is absent. No
 * remote schema resolver is registered, so an {@code http(s)} {@code $ref}
 * never triggers a network fetch (no SSRF) -- it surfaces as a schema error.
 */
final class SchemaValidator
{
    /**
     * Validate $json against the JSON Schema $schema. A document that conforms
     * yields a passing result with no errors; a non-conforming document yields a
     * failing result whose messages name the offending data path and keyword.
     *
     * @throws MissingDependencyException when opis/json-schema is not installed
     * @throws InvalidJsonException when $json or $schema is not valid JSON
     * @throws JsonSchemaException when $schema parses as JSON but opis rejects it as a schema
     */
    public function validate(string $json, string $schema): SchemaValidationResult
    {
        MissingDependencyException::ensure(
            class_exists(\Opis\JsonSchema\Validator::class),
            'opis/json-schema',
            'JSON Schema validation',
        );

        // Decode the data via the facade so malformed data JSON raises
        // InvalidJsonException, then normalise to the object/array shape opis
        // expects (associative arrays become stdClass, lists stay arrays).
        $data = \Opis\JsonSchema\Helper::toJSON(Json::parse($json)->value());

        // Decode the schema ourselves to an object. Passing a raw string to opis
        // would make it treat the string as a URI first (a remote-fetch vector);
        // decoding here also means a malformed schema string is reported as
        // InvalidJsonException, since it genuinely is invalid JSON.
        $decodedSchema = $this->decodeSchema($schema);

        // maxErrors high + stopAtFirstError false so we collect every violation,
        // not just the first. The default resolver registers no remote protocols.
        $validator = new \Opis\JsonSchema\Validator(null, 25, false);

        try {
            $result = $validator->validate($data, $decodedSchema);
        } catch (\Opis\JsonSchema\Exceptions\SchemaException $e) {
            throw JsonSchemaException::invalidSchema($e);
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            // opis raises plain Invalid/RuntimeException for some malformed or
            // unresolvable schemas (e.g. an unresolvable remote $ref).
            throw JsonSchemaException::invalidSchema($e);
        }

        if ($result->isValid()) {
            return new SchemaValidationResult([]);
        }

        return new SchemaValidationResult($this->formatErrors($result));
    }

    private function decodeSchema(string $schema): \stdClass | bool
    {
        try {
            $decoded = json_decode($schema, false, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new InvalidJsonException('Invalid JSON schema: ' . $e->getMessage(), 0, $e);
        }

        // A JSON Schema is an object or a boolean (true/false schema). Anything
        // else parsed as valid JSON but is not a schema opis can use.
        if (!$decoded instanceof \stdClass && !is_bool($decoded)) {
            throw JsonSchemaException::invalidSchema(
                new \InvalidArgumentException('A JSON Schema must be an object or a boolean.'),
            );
        }

        return $decoded;
    }

    /**
     * Flatten opis's leaf errors into human-readable strings, prefixing each
     * with the data location it applies to where opis reports one.
     *
     * @return list<string>
     */
    private function formatErrors(\Opis\JsonSchema\ValidationResult $result): array
    {
        $error = $result->error();

        if ($error === null) {
            return [];
        }

        $keyed = (new \Opis\JsonSchema\Errors\ErrorFormatter())->formatKeyed($error);

        $messages = [];

        foreach ($keyed as $path => $pathMessages) {
            $location = $path === '' ? '/' : (string) $path;

            foreach ($pathMessages as $message) {
                $messages[] = "{$location}: {$message}";
            }
        }

        return $messages;
    }
}
