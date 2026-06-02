<?php

namespace DiamondDove\SimpleJson\Tests;

use DiamondDove\SimpleJson\Exceptions\InvalidJsonException;
use DiamondDove\SimpleJson\Exceptions\JsonException;
use DiamondDove\SimpleJson\Exceptions\JsonSchemaException;
use DiamondDove\SimpleJson\Exceptions\MissingDependencyException;
use DiamondDove\SimpleJson\Json;

/**
 * Outside-in acceptance tests for Phase 4 slice 2: JSON Schema validation via
 * the opis/json-schema adapter. Exercises only the public facade
 * (Json::validateSchema / Json::matchesSchema) as a consuming developer would.
 */
final class JsonSchemaAcceptanceTest extends TestCase
{
    /**
     * A realistic person schema: an object with required name/age and typed,
     * constrained properties. Shared across the conforming/non-conforming cases.
     */
    private const PERSON_SCHEMA = '{
        "type": "object",
        "required": ["name", "age"],
        "properties": {
            "name": {"type": "string"},
            "age": {"type": "integer", "minimum": 0}
        }
    }';

    /** SC-1: a conforming document passes with no errors and matchesSchema()===true. */
    public function test_sc1_conforming_document_passes_with_no_errors(): void
    {
        $result = Json::validateSchema('{"name": "Ann", "age": 30}', self::PERSON_SCHEMA);

        $this->assertTrue($result->passes(), 'conforming document should pass');
        $this->assertFalse($result->fails());
        $this->assertSame([], $result->errors(), 'no errors for a conforming document');

        $this->assertTrue(
            Json::matchesSchema('{"name": "Ann", "age": 30}', self::PERSON_SCHEMA),
            'matchesSchema() convenience boolean should be true for a conforming document',
        );
    }

    /** SC-2: a non-conforming document fails with a non-empty list of readable messages; matchesSchema()===false. */
    public function test_sc2_non_conforming_document_fails_with_readable_errors(): void
    {
        $result = Json::validateSchema('{"name": "Ann", "age": -5}', self::PERSON_SCHEMA);

        $this->assertTrue($result->fails(), 'age below minimum should fail validation');
        $this->assertFalse($result->passes());

        $errors = $result->errors();
        $this->assertIsList($errors);
        $this->assertNotEmpty($errors, 'a failing document must surface at least one message');

        foreach ($errors as $message) {
            $this->assertIsString($message);
            $this->assertNotSame('', trim($message), 'each message should be a non-empty human-readable string');
        }

        // The message should reference the offending data location (the age path).
        $this->assertStringContainsString('age', implode("\n", $errors));

        $this->assertFalse(
            Json::matchesSchema('{"name": "Ann", "age": -5}', self::PERSON_SCHEMA),
            'matchesSchema() should be false for a non-conforming document',
        );
    }

    /** SC-2 (missing required): an absent required property is also reported as a failure. */
    public function test_sc2_missing_required_property_fails(): void
    {
        $result = Json::validateSchema('{"age": 30}', self::PERSON_SCHEMA);

        $this->assertTrue($result->fails());
        $this->assertNotEmpty($result->errors());
        $this->assertFalse(Json::matchesSchema('{"age": 30}', self::PERSON_SCHEMA));
    }

    /** SC-3: malformed DATA json raises InvalidJsonException (catchable as JsonException). */
    public function test_sc3_malformed_data_json_throws_invalid_json_exception(): void
    {
        $this->expectException(InvalidJsonException::class);

        Json::validateSchema('{not valid json', self::PERSON_SCHEMA);
    }

    public function test_sc3_malformed_data_json_is_catchable_as_json_exception(): void
    {
        try {
            Json::validateSchema('{not valid json', self::PERSON_SCHEMA);
            $this->fail('expected an exception for malformed data JSON');
        } catch (JsonException $e) {
            $this->assertInstanceOf(InvalidJsonException::class, $e);
        }
    }

    /** SC-4: an UNPARSEABLE schema string is invalid JSON → InvalidJsonException. */
    public function test_sc4_unparseable_schema_throws_invalid_json_exception(): void
    {
        $this->expectException(InvalidJsonException::class);

        Json::validateSchema('{"name": "Ann"}', '{not a schema');
    }

    /**
     * SC-4: a schema string that PARSES as JSON but is structurally invalid as a
     * schema (here, an object whose "type" keyword is a number) is rejected by
     * opis and surfaced as JsonSchemaException, catchable as JsonException.
     */
    public function test_sc4_parseable_but_invalid_schema_throws_json_schema_exception(): void
    {
        try {
            Json::validateSchema('{"name": "Ann"}', '{"type": 12345}');
            $this->fail('expected a structurally-invalid schema to throw');
        } catch (JsonException $e) {
            $this->assertInstanceOf(JsonSchemaException::class, $e);
            $this->assertStringContainsStringIgnoringCase('schema', $e->getMessage());
        }
    }

    /** SC-4 (non-object schema): a JSON number is valid JSON but not a usable schema → JsonSchemaException. */
    public function test_sc4_schema_that_is_not_an_object_or_boolean_throws_json_schema_exception(): void
    {
        $this->expectException(JsonSchemaException::class);

        Json::validateSchema('{"name": "Ann"}', '123');
    }

    /**
     * SC-5: a remote $ref must NOT silently validate-true and must NOT make a
     * network call. It surfaces quickly as a JsonSchemaException (unresolved
     * reference) — never passes()===true, and resolves in well under a second.
     */
    public function test_sc5_remote_ref_does_not_silently_pass_and_makes_no_network_call(): void
    {
        $schema = '{"$ref": "http://example.com/remote-schema.json"}';

        $start = microtime(true);

        try {
            $result = Json::validateSchema('{"name": "Ann"}', $schema);
            // If no exception was thrown, the one thing that must hold is that it
            // did NOT silently validate-true off an unfetched remote schema.
            $this->assertTrue($result->fails(), 'remote $ref must not pass()===true without fetching');
            $this->assertFalse($result->passes());
        } catch (JsonSchemaException $e) {
            // Expected path: opis reports the remote $ref as unresolvable.
            $this->assertStringContainsStringIgnoringCase('ref', $e->getMessage());
        }

        $elapsed = microtime(true) - $start;
        $this->assertLessThan(
            2.0,
            $elapsed,
            'remote $ref must resolve quickly (no network fetch / no hang)',
        );
    }

    /**
     * SC-6: missing-dependency seam. The real package is installed here, so true
     * absence cannot be exercised end-to-end (UNCOVERABLE-for-real-absence). The
     * MissingDependencyException::ensure() seam the adapter itself calls is the
     * tested substitute: ensure(false, ...) throws an actionable, catchable error.
     */
    public function test_sc6_missing_dependency_seam_throws_actionable_exception(): void
    {
        try {
            MissingDependencyException::ensure(false, 'opis/json-schema', 'JSON Schema validation');
            $this->fail('ensure(false, ...) must throw');
        } catch (JsonException $e) {
            $this->assertInstanceOf(MissingDependencyException::class, $e);
            $this->assertStringContainsString('opis/json-schema', $e->getMessage());
            $this->assertStringContainsString('composer require opis/json-schema', $e->getMessage());
        }
    }

    public function test_sc6_seam_is_a_noop_when_dependency_is_present(): void
    {
        MissingDependencyException::ensure(true, 'opis/json-schema', 'JSON Schema validation');
        $this->addToAssertionCount(1); // no throw == pass
    }

    /**
     * SC-7: marker consistency — every schema-feature exception is reachable
     * through the single JsonException marker, so a consumer can catch them all
     * with one catch block.
     */
    public function test_sc7_all_schema_exceptions_are_catchable_via_json_exception_marker(): void
    {
        $throwers = [
            'malformed data'           => fn () => Json::validateSchema('{bad', self::PERSON_SCHEMA),
            'unparseable schema'       => fn () => Json::validateSchema('{}', '{bad'),
            'structurally bad schema'  => fn () => Json::validateSchema('{}', '{"type": 12345}'),
            'remote $ref'              => fn () => Json::validateSchema('{}', '{"$ref": "http://example.com/x.json"}'),
            'missing dependency seam'  => fn () => MissingDependencyException::ensure(false, 'opis/json-schema', 'JSON Schema validation'),
        ];

        foreach ($throwers as $label => $thrower) {
            try {
                $thrower();
                $this->fail("expected [$label] to throw");
            } catch (JsonException $e) {
                $this->assertInstanceOf(JsonException::class, $e, "[$label] should be a JsonException");
                $this->assertStringNotContainsString(
                    'Opis\\JsonSchema',
                    get_class($e),
                    "[$label] must not leak a raw opis exception type",
                );
            }
        }
    }
}
