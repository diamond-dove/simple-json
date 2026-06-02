<?php

namespace DiamondDove\SimpleJson\Tests;

use DiamondDove\SimpleJson\Exceptions\InvalidJsonException;
use DiamondDove\SimpleJson\Exceptions\JsonException;
use DiamondDove\SimpleJson\Exceptions\JsonMappingException;
use DiamondDove\SimpleJson\Exceptions\MissingDependencyException;
use DiamondDove\SimpleJson\Json;

/**
 * Outside-in acceptance tests for Phase 4 slice 3: advanced typed mapping via
 * the cuyz/valinor adapter. Exercises only the public facade (Json::mapTo) as a
 * consuming developer would, asserting on returned typed values and on the
 * package's own exception hierarchy — no raw valinor type may leak out.
 */
final class JsonMapToAcceptanceTest extends TestCase
{
    /** VL-1: a JSON array maps onto list<string> as a plain PHP list of strings. */
    public function test_vl1_maps_json_array_to_list_of_string(): void
    {
        $result = Json::mapTo('["a", "b", "c"]', 'list<string>');

        $this->assertSame(['a', 'b', 'c'], $result);
    }

    /** VL-2: a bounded int signature (int<0, 100>) yields the correct int for in-range data. */
    public function test_vl2_maps_to_bounded_int_signature(): void
    {
        $result = Json::mapTo('42', 'int<0, 100>');

        $this->assertSame(42, $result);
        $this->assertIsInt($result);
    }

    /** VL-2: non-empty-string signature returns the string for valid data. */
    public function test_vl2_maps_to_non_empty_string_signature(): void
    {
        $result = Json::mapTo('"hello"', 'non-empty-string');

        $this->assertSame('hello', $result);
    }

    /** VL-2: a shaped-array signature returns a correctly-typed associative array. */
    public function test_vl2_maps_to_shaped_array_signature(): void
    {
        $result = Json::mapTo('{"name": "Ann", "age": 30}', 'array{name: string, age: int}');

        $this->assertSame(['name' => 'Ann', 'age' => 30], $result);
        $this->assertIsString($result['name']);
        $this->assertIsInt($result['age']);
    }

    /**
     * VL-3: data violating a VALID signature → JsonMappingException, catchable as
     * JsonException, with no raw CuyZ\Valinor type in the leaked class name.
     */
    public function test_vl3_data_violating_valid_signature_throws_mapping_exception(): void
    {
        try {
            Json::mapTo('["a", 1]', 'list<string>');
            $this->fail('a non-string element must not satisfy list<string>');
        } catch (JsonException $e) {
            $this->assertInstanceOf(JsonMappingException::class, $e);
            $this->assertStringNotContainsString(
                'CuyZ\\Valinor',
                get_class($e),
                'no raw valinor exception type may leak out of Json::mapTo()',
            );
            // The wrapped message names the target signature so the failure is self-describing.
            $this->assertStringContainsString('list<string>', $e->getMessage());
        }
    }

    /** VL-3: an out-of-range int for int<0, 100> is data-failure, not a developer error. */
    public function test_vl3_out_of_range_int_throws_mapping_exception(): void
    {
        try {
            Json::mapTo('200', 'int<0, 100>');
            $this->fail('200 must not satisfy int<0, 100>');
        } catch (JsonException $e) {
            $this->assertInstanceOf(JsonMappingException::class, $e);
            $this->assertStringNotContainsString('CuyZ\\Valinor', get_class($e));
        }
    }

    /** VL-4: malformed JSON input → InvalidJsonException (catchable as JsonException). */
    public function test_vl4_malformed_json_throws_invalid_json_exception(): void
    {
        $this->expectException(InvalidJsonException::class);

        Json::mapTo('[not json', 'list<string>');
    }

    public function test_vl4_malformed_json_is_catchable_as_json_exception(): void
    {
        try {
            Json::mapTo('[not json', 'list<string>');
            $this->fail('expected an exception for malformed JSON');
        } catch (JsonException $e) {
            $this->assertInstanceOf(InvalidJsonException::class, $e);
        }
    }

    /**
     * VL-5: an invalid/unresolvable type SIGNATURE is a developer error and is
     * surfaced as a plain \InvalidArgumentException — explicitly NOT a
     * JsonMappingException and NOT part of the JsonException marker hierarchy,
     * so consumers can distinguish "my data is wrong" from "my type string is wrong".
     */
    public function test_vl5_invalid_signature_throws_invalid_argument_exception(): void
    {
        try {
            Json::mapTo('"x"', 'this is not a type');
            $this->fail('a nonsense signature must throw');
        } catch (\InvalidArgumentException $e) {
            $this->assertNotInstanceOf(
                JsonMappingException::class,
                $e,
                'a bad signature is a developer error, not a data mapping failure',
            );
            $this->assertNotInstanceOf(
                JsonException::class,
                $e,
                'a developer-error signature must not be part of the JsonException marker hierarchy',
            );
            $this->assertStringContainsString('this is not a type', $e->getMessage());
        }
    }

    /**
     * VL-6: missing-dependency seam. cuyz/valinor is installed here, so true
     * absence cannot be exercised end-to-end (UNCOVERABLE-for-real-absence). The
     * MissingDependencyException::ensure() seam the adapter itself calls is the
     * tested substitute.
     */
    public function test_vl6_missing_dependency_seam_throws_actionable_exception(): void
    {
        try {
            MissingDependencyException::ensure(false, 'cuyz/valinor', 'advanced type mapping');
            $this->fail('ensure(false, ...) must throw');
        } catch (JsonException $e) {
            $this->assertInstanceOf(MissingDependencyException::class, $e);
            $this->assertStringContainsString('cuyz/valinor', $e->getMessage());
            $this->assertStringContainsString('composer require cuyz/valinor', $e->getMessage());
        }
    }

    public function test_vl6_seam_is_a_noop_when_dependency_is_present(): void
    {
        MissingDependencyException::ensure(true, 'cuyz/valinor', 'advanced type mapping');
        $this->addToAssertionCount(1); // no throw == pass
    }
}
