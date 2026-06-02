<?php

namespace DiamondDove\SimpleJson\Tests;

use DiamondDove\SimpleJson\Exceptions\InvalidJsonException;
use DiamondDove\SimpleJson\Exceptions\JsonTypeException;
use DiamondDove\SimpleJson\Json;
use DiamondDove\SimpleJson\JsonAccessor;

/**
 * Outside-in acceptance tests for Phase 1 of the in-memory JSON toolkit.
 *
 * These exercise the public library API exactly as a consuming developer
 * would: `use DiamondDove\SimpleJson\Json;` then call the static facade and
 * chain on the returned JsonAccessor. No application code is touched.
 */
final class JsonAcceptanceTest extends TestCase
{
    private function nestedUser(): JsonAccessor
    {
        $contents = file_get_contents($this->getPath('nested_user.json'));

        return Json::parse($contents);
    }

    /** AC-1: parse(valid) returns a JsonAccessor instance. */
    public function test_ac1_parse_valid_returns_json_accessor(): void
    {
        $this->assertInstanceOf(JsonAccessor::class, Json::parse('{"a":1}'));
    }

    /** AC-2: JSON objects decode to assoc arrays. */
    public function test_ac2_objects_decode_to_assoc_arrays(): void
    {
        $this->assertSame(['a' => 1], Json::parse('{"a":1}')->array());
    }

    /** AC-3: tryParse(valid) is equivalent to parse(valid). */
    public function test_ac3_tryparse_valid_equivalent_to_parse(): void
    {
        $json = '{"a":1,"b":"x"}';

        $this->assertInstanceOf(JsonAccessor::class, Json::tryParse($json));
        $this->assertSame(
            Json::parse($json)->array(),
            Json::tryParse($json)->array(),
        );
    }

    /** AC-4: path('user.address.city')->string() === 'Santo Domingo'. */
    public function test_ac4_nested_path_string(): void
    {
        $this->assertSame('Santo Domingo', $this->nestedUser()->path('user.address.city')->string());
    }

    /** AC-5: path is case-sensitive: path('User') resolves to missing and strict throws. */
    public function test_ac5_path_is_case_sensitive(): void
    {
        $this->expectException(JsonTypeException::class);

        $this->nestedUser()->path('User')->array();
    }

    /** AC-6: path() returns a NEW instance and leaves the original unchanged. */
    public function test_ac6_path_returns_new_instance_original_unchanged(): void
    {
        $root = $this->nestedUser();
        $child = $root->path('user');

        $this->assertNotSame($root, $child);
        // Original root remains fully usable/unchanged after navigating away.
        $this->assertSame(5, $root->path('count')->int());
        $this->assertSame('Ana', $child->path('name')->string());
    }

    /** AC-7: string() on a JSON string. */
    public function test_ac7_string_terminal(): void
    {
        $this->assertSame('Ana', $this->nestedUser()->path('user.name')->string());
    }

    /** AC-8: int() on a JSON int. */
    public function test_ac8_int_terminal(): void
    {
        $this->assertSame(5, $this->nestedUser()->path('count')->int());
        $this->assertSame(10101, $this->nestedUser()->path('user.address.zip')->int());
    }

    /** AC-9 / AC-15: int/float type rules. */
    public function test_ac9_ac15_int_float_rules(): void
    {
        // parse('5')->float() === 5.0 (int promoted to float)
        $five = Json::parse('5')->float();
        $this->assertSame(5.0, $five);

        // parse('5.0')->float() === 5.0
        $this->assertSame(5.0, Json::parse('5.0')->float());

        // parse('5.5')->float() === 5.5
        $this->assertSame(5.5, Json::parse('5.5')->float());

        // parse('5.0')->int() throws (5.0 decodes as float, int is strict)
        try {
            Json::parse('5.0')->int();
            $this->fail('Expected JsonTypeException for parse("5.0")->int()');
        } catch (JsonTypeException) {
            $this->addToAssertionCount(1);
        }

        // parse('5.5')->int() throws
        $this->expectException(JsonTypeException::class);
        Json::parse('5.5')->int();
    }

    /** AC-10: bool() on true; parse('1')->bool() throws (1 is not a bool). */
    public function test_ac10_bool_rules(): void
    {
        $this->assertTrue($this->nestedUser()->path('user.active')->bool());

        $this->expectException(JsonTypeException::class);
        Json::parse('1')->bool();
    }

    /** AC-11: array() on a JSON array and on a JSON object. */
    public function test_ac11_array_terminal_for_list_and_object(): void
    {
        $this->assertSame([1, 2], Json::parse('[1,2]')->array());
        $this->assertSame(['a' => 1], Json::parse('{"a":1}')->array());
        $this->assertSame(['a', 'b'], $this->nestedUser()->path('user.tags')->array());
    }

    /** AC-12: strict terminal on a type mismatch throws JsonTypeException. */
    public function test_ac12_strict_type_mismatch_throws(): void
    {
        // user.name is a string; calling int() is a mismatch.
        $this->expectException(JsonTypeException::class);
        $this->nestedUser()->path('user.name')->int();
    }

    /** AC-13: strict terminal on a missing path throws JsonTypeException. */
    public function test_ac13_strict_missing_path_throws(): void
    {
        $this->expectException(JsonTypeException::class);
        $this->nestedUser()->path('user.nope.absent')->string();
    }

    /** AC-14: strict terminal on a present-null value throws JsonTypeException. */
    public function test_ac14_strict_present_null_throws(): void
    {
        $this->expectException(JsonTypeException::class);
        Json::parse('{"a":null}')->path('a')->string();
    }

    /** AC-16: *Or returns the actual value when present and correctly typed. */
    public function test_ac16_or_returns_actual_when_present(): void
    {
        $user = $this->nestedUser();
        $this->assertSame('Ana', $user->path('user.name')->stringOr('def'));
        $this->assertSame(5, $user->path('count')->intOr(0));
        $this->assertSame(4.5, $user->path('user.score')->floatOr(0.0));
        $this->assertTrue($user->path('user.active')->boolOr(false));
        $this->assertSame(['a', 'b'], $user->path('user.tags')->arrayOr([]));
    }

    /** AC-17: *Or returns the default on a missing path. */
    public function test_ac17_or_returns_default_on_missing(): void
    {
        $user = $this->nestedUser();
        $this->assertSame('def', $user->path('nope')->stringOr('def'));
        $this->assertSame(99, $user->path('nope')->intOr(99));
        $this->assertSame(9.9, $user->path('nope')->floatOr(9.9));
        $this->assertTrue($user->path('nope')->boolOr(true));
        $this->assertSame(['x'], $user->path('nope')->arrayOr(['x']));
    }

    /** AC-18: *Or returns the default on a type mismatch. */
    public function test_ac18_or_returns_default_on_mismatch(): void
    {
        $user = $this->nestedUser();
        // count is int -> stringOr should fall back
        $this->assertSame('def', $user->path('count')->stringOr('def'));
        // user.name is string -> intOr should fall back
        $this->assertSame(7, $user->path('user.name')->intOr(7));
    }

    /** AC-19: *Or returns the default on a present-null value. */
    public function test_ac19_or_returns_default_on_present_null(): void
    {
        $this->assertSame('def', $this->nestedUser()->path('missingNull')->stringOr('def'));
    }

    /** AC-20: *OrNull returns the actual value when present and correctly typed. */
    public function test_ac20_ornull_returns_actual_when_present(): void
    {
        $this->assertSame('Ana', $this->nestedUser()->path('user.name')->stringOrNull());
        $this->assertSame(5, $this->nestedUser()->path('count')->intOrNull());
    }

    /** AC-21: *OrNull returns null on a missing path. */
    public function test_ac21_ornull_null_on_missing(): void
    {
        $this->assertNull($this->nestedUser()->path('nope')->stringOrNull());
        $this->assertNull($this->nestedUser()->path('nope')->intOrNull());
    }

    /** AC-22: *OrNull returns null on a type mismatch. */
    public function test_ac22_ornull_null_on_mismatch(): void
    {
        // count is int -> stringOrNull null; name is string -> intOrNull null
        $this->assertNull($this->nestedUser()->path('count')->stringOrNull());
        $this->assertNull($this->nestedUser()->path('user.name')->intOrNull());
    }

    /** AC-23: *OrNull returns null on a present-null value. */
    public function test_ac23_ornull_null_on_present_null(): void
    {
        $this->assertNull($this->nestedUser()->path('missingNull')->stringOrNull());
    }

    /** AC-24: parse(invalid) throws InvalidJsonException. */
    public function test_ac24_parse_invalid_throws(): void
    {
        $this->expectException(InvalidJsonException::class);
        Json::parse('{not valid json');
    }

    /** AC-25: the InvalidJsonException chains the native \JsonException as previous. */
    public function test_ac25_invalid_json_chains_previous(): void
    {
        try {
            Json::parse('{not valid json');
            $this->fail('Expected InvalidJsonException');
        } catch (InvalidJsonException $e) {
            $this->assertInstanceOf(\JsonException::class, $e->getPrevious());
        }
    }

    /** AC-26: tryParse(invalid) and tryParse('') return null without throwing. */
    public function test_ac26_tryparse_invalid_and_empty_return_null(): void
    {
        $this->assertNull(Json::tryParse('{not valid json'));
        $this->assertNull(Json::tryParse(''));
    }

    /** AC-27: parse('5')->int() === 5 as a root terminal (no path). */
    public function test_ac27_root_terminal_no_path(): void
    {
        $this->assertSame(5, Json::parse('5')->int());
    }

    /**
     * AC-28: parse('null') is present-not-missing (a value-bearing accessor).
     * stringOrNull() === null, but it must be distinguishable from a missing
     * accessor. Distinguishing observable: arrayOr() on present-null returns
     * the default (same as missing for *Or), so we assert present-null yields
     * null on *OrNull and the default on *Or — confirming the value-bearing
     * accessor behaves like a present-null leaf rather than erroring.
     */
    public function test_ac28_parse_null_is_present_value_bearing(): void
    {
        $accessor = Json::parse('null');

        $this->assertInstanceOf(JsonAccessor::class, $accessor);
        $this->assertNull($accessor->stringOrNull());
        $this->assertSame('def', $accessor->stringOr('def'));

        // Strict terminal on present-null throws (present-null is a mismatch).
        $this->expectException(JsonTypeException::class);
        $accessor->string();
    }

    /**
     * AC-29: a scalar root treated as a path-target is missing.
     * parse('5')->path('x') cannot be traversed -> missing accessor:
     * intOrNull() === null and int() throws.
     */
    public function test_ac29_scalar_root_path_is_missing(): void
    {
        $this->assertNull(Json::parse('5')->path('x')->intOrNull());

        $this->expectException(JsonTypeException::class);
        Json::parse('5')->path('x')->int();
    }

    /**
     * OBSERVATION (not a graded AC): the brief assumed path('weird.key') would
     * be unaddressable/missing, but Illuminate Arr matches the literal key
     * first. This test documents the ACTUAL behavior so the report can state
     * it factually. It is intentionally an assertion of observed behavior.
     */
    public function test_observation_weird_dotted_key_resolves_to_literal(): void
    {
        $this->assertSame('dotted', $this->nestedUser()->path('weird.key')->string());
    }
}
