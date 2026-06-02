<?php

namespace DiamondDove\SimpleJson\Tests;

use DiamondDove\SimpleJson\Exceptions\InvalidJsonException;
use DiamondDove\SimpleJson\Exceptions\JsonException;
use DiamondDove\SimpleJson\Exceptions\JsonMappingException;
use DiamondDove\SimpleJson\Json;
use DiamondDove\SimpleJson\Tests\Fixtures\AbstractData;
use DiamondDove\SimpleJson\Tests\Fixtures\AddressData;
use DiamondDove\SimpleJson\Tests\Fixtures\EmptyData;
use DiamondDove\SimpleJson\Tests\Fixtures\NonPromotedData;
use DiamondDove\SimpleJson\Tests\Fixtures\PrivateCtorData;
use DiamondDove\SimpleJson\Tests\Fixtures\PureStatus;
use DiamondDove\SimpleJson\Tests\Fixtures\RequiredData;
use DiamondDove\SimpleJson\Tests\Fixtures\ScalarsData;
use DiamondDove\SimpleJson\Tests\Fixtures\SomeInterface;
use DiamondDove\SimpleJson\Tests\Fixtures\Status;
use DiamondDove\SimpleJson\Tests\Fixtures\TagData;
use DiamondDove\SimpleJson\Tests\Fixtures\UserData;

/**
 * Acceptance suite for Phase 3 (Json::map DTO hydration).
 *
 * Outside-in: every test consumes ONLY the public facade `Json::map(...)` the
 * way a consuming developer would, and asserts on the returned typed object or
 * on the caught exception type/message. No `src/` internals are touched.
 *
 * This file is deliberately independent of the builder's tests/JsonMapTest.php;
 * assertions here re-derive the expected observable behavior from the contract
 * and, where the contract demands it, assert on exception MESSAGE content
 * (expected+actual naming) so a test cannot pass for the wrong reason.
 */
final class JsonMapAcceptanceTest extends TestCase
{
    // ---------------------------------------------------------------------
    // AC-1 — scalar hydration from an array source
    // ---------------------------------------------------------------------
    public function test_AC1_scalar_hydration_from_array(): void
    {
        $user = Json::map(['name' => 'Ana', 'age' => 30], UserData::class);

        $this->assertSame('Ana', $user->name);
        $this->assertSame(30, $user->age);
    }

    // ---------------------------------------------------------------------
    // AC-2 — JSON string source produces an identical DTO to AC-1
    // ---------------------------------------------------------------------
    public function test_AC2_json_string_source_equals_array_source(): void
    {
        $fromString = Json::map('{"name":"Ana","age":30}', UserData::class);
        $fromArray = Json::map(['name' => 'Ana', 'age' => 30], UserData::class);

        $this->assertEquals($fromArray, $fromString);
        $this->assertSame('Ana', $fromString->name);
        $this->assertSame(30, $fromString->age);
    }

    // ---------------------------------------------------------------------
    // AC-3 — returned value is an instance of the requested class
    // ---------------------------------------------------------------------
    public function test_AC3_result_is_instance_of_class_name(): void
    {
        $user = Json::map(['name' => 'Ana', 'age' => 1], UserData::class);

        $this->assertInstanceOf(UserData::class, $user);
    }

    // ---------------------------------------------------------------------
    // AC-4 — generic return-type inference.
    // Cannot be proven by a runtime assertion: the static return type is a
    // PHPStan/IDE concern, verified separately via `vendor/bin/phpstan analyse`
    // at level 6. The runtime portion asserts the instance is of $className so
    // the inferred type is at least sound at runtime.
    // ---------------------------------------------------------------------
    public function test_AC4_generic_inference_runtime_soundness(): void
    {
        $user = Json::map(['name' => 'Ana', 'age' => 1], UserData::class);

        // Runtime soundness check; static inference is asserted by PHPStan.
        $this->assertInstanceOf(UserData::class, $user);
    }

    // ---------------------------------------------------------------------
    // AC-5 — row-wise (stream) mapping: an array of rows -> a list of DTOs
    // ---------------------------------------------------------------------
    public function test_AC5_row_wise_stream_mapping(): void
    {
        $rows = [
            ['name' => 'Ana', 'age' => 30],
            ['name' => 'Bob', 'age' => 40],
            ['name' => 'Cy', 'age' => 50],
        ];

        $users = array_map(
            static fn (array $row): UserData => Json::map($row, UserData::class),
            $rows,
        );

        $this->assertCount(3, $users);
        $this->assertContainsOnlyInstancesOf(UserData::class, $users);
        // order preserved
        $this->assertSame('Ana', $users[0]->name);
        $this->assertSame('Bob', $users[1]->name);
        $this->assertSame('Cy', $users[2]->name);
        $this->assertSame(50, $users[2]->age);
    }

    // ---------------------------------------------------------------------
    // AC-6 — nested DTO: object-shaped value recursively hydrated
    // ---------------------------------------------------------------------
    public function test_AC6_nested_dto_recursively_hydrated(): void
    {
        $user = Json::map([
            'name' => 'Ana',
            'age' => 30,
            'address' => ['city' => 'Santo Domingo', 'zip' => '10101'],
        ], UserData::class);

        $this->assertInstanceOf(AddressData::class, $user->address);
        $this->assertSame('Santo Domingo', $user->address->city);
        $this->assertSame('10101', $user->address->zip);
    }

    // ---------------------------------------------------------------------
    // AC-7 — nullable nested absent OR present-null -> null
    // ---------------------------------------------------------------------
    public function test_AC7_nullable_nested_absent_becomes_null(): void
    {
        $user = Json::map(['name' => 'Ana', 'age' => 1], UserData::class);

        $this->assertNull($user->address);
    }

    public function test_AC7_nullable_nested_present_null_becomes_null(): void
    {
        $user = Json::map(['name' => 'Ana', 'age' => 1, 'address' => null], UserData::class);

        $this->assertNull($user->address);
    }

    // ---------------------------------------------------------------------
    // AC-8 — missing key with a default value -> the default
    // ---------------------------------------------------------------------
    public function test_AC8_missing_key_with_default_uses_default(): void
    {
        $user = Json::map(['name' => 'Ana', 'age' => 1], UserData::class);

        $this->assertSame(Status::Active, $user->status);
        $this->assertSame([], $user->tags);
    }

    // ---------------------------------------------------------------------
    // AC-9 — missing key, nullable, NO default -> null
    // ---------------------------------------------------------------------
    public function test_AC9_missing_nullable_no_default_becomes_null(): void
    {
        // AddressData::$zip is `?string` with default null; assert the *missing*
        // (not present-null) path yields null even when the key is wholly absent.
        $address = Json::map(['city' => 'SD'], AddressData::class);

        $this->assertNull($address->zip);
    }

    // ---------------------------------------------------------------------
    // AC-10 — missing required (no default, not nullable) -> JsonMappingException,
    // also catchable through the JsonException marker.
    // ---------------------------------------------------------------------
    public function test_AC10_missing_required_throws_mapping_exception(): void
    {
        try {
            Json::map([], RequiredData::class);
            $this->fail('Expected JsonMappingException for a missing required parameter.');
        } catch (JsonMappingException $e) {
            $this->assertInstanceOf(JsonException::class, $e);
            $this->assertStringContainsString('required', $e->getMessage());
        }
    }

    public function test_AC10_missing_required_catchable_as_json_exception(): void
    {
        $this->expectException(JsonException::class);

        Json::map([], RequiredData::class);
    }

    // ---------------------------------------------------------------------
    // AC-11 — valid backing value -> the enum case
    // ---------------------------------------------------------------------
    public function test_AC11_valid_enum_backing_value_maps_to_case(): void
    {
        $user = Json::map(['name' => 'Ana', 'age' => 1, 'status' => 'inactive'], UserData::class);

        $this->assertSame(Status::Inactive, $user->status);
    }

    // ---------------------------------------------------------------------
    // AC-12 — invalid enum backing value -> JsonMappingException (no \ValueError)
    // ---------------------------------------------------------------------
    public function test_AC12_invalid_enum_value_throws_mapping_exception(): void
    {
        try {
            Json::map(['name' => 'Ana', 'age' => 1, 'status' => 'nope'], UserData::class);
            $this->fail('Expected JsonMappingException for an invalid enum backing value.');
        } catch (\ValueError $e) {
            $this->fail('A raw \\ValueError escaped the mapper: ' . $e->getMessage());
        } catch (JsonMappingException $e) {
            $this->assertStringContainsString('Status', $e->getMessage());
            $this->assertInstanceOf(JsonException::class, $e);
        }
    }

    // ---------------------------------------------------------------------
    // AC-13 — #[ListOf(X)] over object-shaped rows -> ordered list<X>
    // ---------------------------------------------------------------------
    public function test_AC13_list_of_produces_ordered_dto_list(): void
    {
        $user = Json::map([
            'name' => 'Ana',
            'age' => 1,
            'tags' => [['label' => 'first'], ['label' => 'second'], ['label' => 'third']],
        ], UserData::class);

        $this->assertCount(3, $user->tags);
        $this->assertContainsOnlyInstancesOf(TagData::class, $user->tags);
        $this->assertSame('first', $user->tags[0]->label);
        $this->assertSame('second', $user->tags[1]->label);
        $this->assertSame('third', $user->tags[2]->label);
        // ordered, sequential keys
        $this->assertSame([0, 1, 2], array_keys($user->tags));
    }

    // ---------------------------------------------------------------------
    // AC-14 — #[ListOf] empty [] -> []
    // ---------------------------------------------------------------------
    public function test_AC14_list_of_empty_yields_empty_array(): void
    {
        $user = Json::map(['name' => 'Ana', 'age' => 1, 'tags' => []], UserData::class);

        $this->assertSame([], $user->tags);
    }

    // ---------------------------------------------------------------------
    // AC-15 — int param strictly rejects 5.0 and "5"
    // ---------------------------------------------------------------------
    public function test_AC15_int_rejects_float(): void
    {
        try {
            Json::map(
                ['name' => 'x', 'count' => 5.0, 'ratio' => 1.0, 'active' => true, 'meta' => []],
                ScalarsData::class,
            );
            $this->fail('Expected JsonMappingException: int must reject float 5.0.');
        } catch (JsonMappingException $e) {
            // message names expected (int) + actual (float)
            $this->assertStringContainsString('int', $e->getMessage());
            $this->assertStringContainsString('float', $e->getMessage());
        }
    }

    public function test_AC15_int_rejects_numeric_string(): void
    {
        $this->expectException(JsonMappingException::class);

        Json::map(
            ['name' => 'x', 'count' => '5', 'ratio' => 1.0, 'active' => true, 'meta' => []],
            ScalarsData::class,
        );
    }

    // ---------------------------------------------------------------------
    // AC-16 — float accepts int(->float) and float; rejects "5"
    // ---------------------------------------------------------------------
    public function test_AC16_float_accepts_int_widened_to_float(): void
    {
        $dto = Json::map(
            ['name' => 'x', 'count' => 1, 'ratio' => 5, 'active' => true, 'meta' => []],
            ScalarsData::class,
        );

        $this->assertSame(5.0, $dto->ratio);
        $this->assertIsFloat($dto->ratio);
    }

    public function test_AC16_float_accepts_float(): void
    {
        $dto = Json::map(
            ['name' => 'x', 'count' => 1, 'ratio' => 5.5, 'active' => true, 'meta' => []],
            ScalarsData::class,
        );

        $this->assertSame(5.5, $dto->ratio);
    }

    public function test_AC16_float_rejects_numeric_string(): void
    {
        $this->expectException(JsonMappingException::class);

        Json::map(
            ['name' => 'x', 'count' => 1, 'ratio' => '5', 'active' => true, 'meta' => []],
            ScalarsData::class,
        );
    }

    // ---------------------------------------------------------------------
    // AC-17 — string / bool / array strict (string rejects 5; bool rejects 1)
    // ---------------------------------------------------------------------
    public function test_AC17_string_rejects_int(): void
    {
        $this->assertMappingFails(static fn () => Json::map(
            ['name' => 5, 'count' => 1, 'ratio' => 1.0, 'active' => true, 'meta' => []],
            ScalarsData::class,
        ), 'string', 'int');
    }

    public function test_AC17_bool_rejects_int_one(): void
    {
        $this->assertMappingFails(static fn () => Json::map(
            ['name' => 'x', 'count' => 1, 'ratio' => 1.0, 'active' => 1, 'meta' => []],
            ScalarsData::class,
        ), 'bool', 'int');
    }

    public function test_AC17_array_rejects_string(): void
    {
        $this->assertMappingFails(static fn () => Json::map(
            ['name' => 'x', 'count' => 1, 'ratio' => 1.0, 'active' => true, 'meta' => 'nope'],
            ScalarsData::class,
        ), 'array', 'string');
    }

    // ---------------------------------------------------------------------
    // AC-18 — extra/unknown source keys are ignored
    // ---------------------------------------------------------------------
    public function test_AC18_extra_keys_ignored(): void
    {
        $user = Json::map(
            ['name' => 'Ana', 'age' => 7, 'unknown' => 'ignored', 'another' => [1, 2, 3]],
            UserData::class,
        );

        $this->assertSame('Ana', $user->name);
        $this->assertSame(7, $user->age);
    }

    // ---------------------------------------------------------------------
    // AC-19 — invalid JSON string -> InvalidJsonException (NOT JsonMappingException)
    // ---------------------------------------------------------------------
    public function test_AC19_invalid_json_string_throws_invalid_json(): void
    {
        try {
            Json::map('{invalid json}', UserData::class);
            $this->fail('Expected InvalidJsonException for malformed JSON.');
        } catch (JsonMappingException $e) {
            $this->fail('Malformed JSON wrongly surfaced as JsonMappingException: ' . $e->getMessage());
        } catch (InvalidJsonException $e) {
            $this->assertInstanceOf(JsonException::class, $e);
        }
    }

    // ---------------------------------------------------------------------
    // AC-20 — class-typed param whose source value is NOT object-shaped
    //         -> JsonMappingException
    // ---------------------------------------------------------------------
    public function test_AC20_class_param_with_non_array_value_throws(): void
    {
        $this->assertMappingFails(static fn () => Json::map(
            ['name' => 'Ana', 'age' => 1, 'address' => 'oops'],
            UserData::class,
        ));
    }

    public function test_AC20_class_param_with_list_value_throws(): void
    {
        // a non-empty list is not object-shaped
        $this->assertMappingFails(static fn () => Json::map(
            ['name' => 'Ana', 'age' => 1, 'address' => [1, 2]],
            UserData::class,
        ));
    }

    // =====================================================================
    // Resolved edges
    // =====================================================================

    // EDGE — present explicit null on a NON-nullable param -> JsonMappingException
    // (type mismatch), NOT treated as missing.
    public function test_edge_present_null_on_non_nullable_throws_type_mismatch(): void
    {
        try {
            Json::map(['name' => null, 'age' => 1], UserData::class);
            $this->fail('Expected JsonMappingException for explicit null on non-nullable string.');
        } catch (JsonMappingException $e) {
            // proves it is treated as a type mismatch (names expected + null),
            // not silently dropped as "missing".
            $this->assertStringContainsString('string', $e->getMessage());
            $this->assertStringContainsString('null', $e->getMessage());
        }
    }

    // EDGE — top-level scalar source -> JsonMappingException
    public function test_edge_top_level_scalar_throws(): void
    {
        $this->assertMappingFails(static fn () => Json::map('5', UserData::class));
    }

    // EDGE — top-level non-empty list source -> JsonMappingException
    public function test_edge_top_level_list_throws(): void
    {
        $this->assertMappingFails(static fn () => Json::map([1, 2, 3], UserData::class));
    }

    // EDGE — empty array is a valid empty object (sanity boundary for "object-shaped")
    public function test_edge_empty_array_maps_to_empty_object(): void
    {
        $this->assertInstanceOf(EmptyData::class, Json::map([], EmptyData::class));
    }

    // EDGE — non-promotable target: abstract class -> JsonMappingException
    public function test_edge_abstract_target_throws(): void
    {
        $this->assertMappingFails(static fn () => Json::map(['name' => 'x'], AbstractData::class));
    }

    // EDGE — non-promotable target: interface -> JsonMappingException
    public function test_edge_interface_target_throws(): void
    {
        $this->assertMappingFails(static fn () => Json::map(
            ['name' => 'x'],
            SomeInterface::class, // intentionally passing a non-instantiable type
        ));
    }

    // EDGE — non-promotable target: private constructor -> JsonMappingException
    public function test_edge_private_constructor_target_throws(): void
    {
        $this->assertMappingFails(static fn () => Json::map(['name' => 'x'], PrivateCtorData::class));
    }

    // EDGE — malformed #[ListOf]: source value is not a list -> JsonMappingException
    public function test_edge_list_of_not_a_list_throws(): void
    {
        // an associative (object-shaped) array is not a list
        $this->assertMappingFails(static fn () => Json::map(
            ['name' => 'Ana', 'age' => 1, 'tags' => ['label' => 'x']],
            UserData::class,
        ));
    }

    // EDGE — malformed #[ListOf]: a list element is not object-shaped -> JsonMappingException
    public function test_edge_list_of_element_not_object_throws(): void
    {
        $this->assertMappingFails(static fn () => Json::map(
            ['name' => 'Ana', 'age' => 1, 'tags' => ['scalar', ['label' => 'ok']]],
            UserData::class,
        ));
    }

    // EDGE — non-promoted constructor params are still filled by name
    public function test_edge_non_promoted_params_filled_by_name(): void
    {
        $data = Json::map(['name' => 'Ana', 'age' => 30], NonPromotedData::class);

        $this->assertSame('Ana', $data->name);
        $this->assertSame(30, $data->age);
    }

    // EDGE — array source is equivalent to the JSON-string source (full DTO graph)
    public function test_edge_array_source_equivalent_to_json_string_source(): void
    {
        $payload = [
            'name' => 'Ana',
            'age' => 30,
            'address' => ['city' => 'SD', 'zip' => '10101'],
            'status' => 'inactive',
            'tags' => [['label' => 'a'], ['label' => 'b']],
        ];

        $fromArray = Json::map($payload, UserData::class);
        $fromString = Json::map((string) json_encode($payload), UserData::class);

        $this->assertEquals($fromArray, $fromString);
        $this->assertSame('SD', $fromString->address?->city);
        $this->assertSame(Status::Inactive, $fromString->status);
        $this->assertCount(2, $fromString->tags);
    }

    // EDGE (extra) — non-backed (pure) enum param cannot be hydrated -> JsonMappingException
    // Confirms the contract clause that pure enums are unsupported (no \ValueError).
    public function test_edge_pure_enum_param_is_unsupported(): void
    {
        // Build a fixture-free check by reusing PureStatus through a tiny inline DTO.
        $this->assertMappingFails(static fn () => Json::map(
            ['status' => 'On'],
            PureStatusHolder::class,
        ));
    }

    /**
     * Assert the callable throws a JsonMappingException. When $expected/$actual
     * are supplied, additionally assert the message names both (proving the
     * failure is a genuine, well-described type mismatch rather than an
     * incidental error).
     */
    private function assertMappingFails(callable $fn, ?string $expected = null, ?string $actual = null): void
    {
        try {
            $fn();
        } catch (JsonMappingException $e) {
            $this->addToAssertionCount(1);

            if ($expected !== null) {
                $this->assertStringContainsString($expected, $e->getMessage());
            }

            if ($actual !== null) {
                $this->assertStringContainsString($actual, $e->getMessage());
            }

            return;
        }

        $this->fail('Expected JsonMappingException, but none was thrown.');
    }
}

/**
 * Local fixture: a promotable DTO whose param is typed as a non-backed (pure)
 * enum. Lives here (not in tests/Fixtures) to keep the acceptance suite
 * self-contained for the "pure enum unsupported" edge.
 */
final readonly class PureStatusHolder
{
    public function __construct(
        public PureStatus $status,
    ) {
    }
}
