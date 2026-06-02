<?php

namespace DiamondDove\SimpleJson\Tests;

use DiamondDove\SimpleJson\Exceptions\InvalidJsonException;
use DiamondDove\SimpleJson\Exceptions\JsonException;
use DiamondDove\SimpleJson\Exceptions\JsonQueryException;
use DiamondDove\SimpleJson\Exceptions\MissingDependencyException;
use DiamondDove\SimpleJson\Json;

/**
 * Outside-in acceptance tests for Phase 4 slice 1: the optional JSONPath
 * adapter exposed via Json::query(), plus the MissingDependencyException seam.
 *
 * These tests deliberately consume only the PUBLIC API (the same surface a
 * downstream developer sees: `use DiamondDove\SimpleJson\Json;` then
 * Json::query(...), and the MissingDependencyException factory/seam). No
 * internal adapter (JsonPath\JsonPath) and no Flow\JSONPath vendor type is
 * referenced from the test body except to PROVE such types never leak out.
 */
class JsonQueryAcceptanceTest extends TestCase
{
    /**
     * A realistic store document: books with prices plus a bicycle. Used as the
     * inline fixture for the happy-path JSONPath expressions.
     */
    private function store(): string
    {
        return <<<'JSON'
        {
            "store": {
                "book": [
                    {"category": "reference", "title": "Sayings of the Century", "price": 8.95},
                    {"category": "fiction",   "title": "Sword of Honour",        "price": 12.99},
                    {"category": "fiction",   "title": "Moby Dick",              "price": 8.99}
                ],
                "bicycle": {"color": "red", "price": 19.95}
            }
        }
        JSON;
    }

    /**
     * Recursively assert that a query result tree contains only PHP scalars,
     * arrays and nulls — never any object (in particular no Flow\JSONPath type).
     *
     * @param  array<array-key, mixed>  $result
     */
    private function assertNoObjectsLeak(array $result): void
    {
        array_walk_recursive($result, function ($leaf): void {
            $this->assertFalse(
                is_object($leaf),
                'Query result leaked an object of type: '
                . (is_object($leaf) ? get_class($leaf) : gettype($leaf)),
            );
        });
    }

    // ---------------------------------------------------------------------
    // AC-1: JSONPath happy path (recursive descent, wildcard, filter)
    // ---------------------------------------------------------------------

    public function test_AC1_recursive_descent_returns_all_matched_values(): void
    {
        // $..price walks every level and collects each price value in document order.
        $this->assertSame(
            [8.95, 12.99, 8.99, 19.95],
            Json::query($this->store(), '$..price'),
        );
    }

    public function test_AC1_wildcard_returns_each_element_value(): void
    {
        // [*] expands the book array; we project each element's title.
        $this->assertSame(
            ['Sayings of the Century', 'Sword of Honour', 'Moby Dick'],
            Json::query($this->store(), '$.store.book[*].title'),
        );
    }

    public function test_AC1_filter_expression_returns_only_matching_values(): void
    {
        // Filter predicate selects books priced under 10; order is not contractually
        // guaranteed across the filter, so compare canonically.
        $this->assertEqualsCanonicalizing(
            ['Sayings of the Century', 'Moby Dick'],
            Json::query($this->store(), '$..book[?(@.price < 10)].title'),
        );
    }

    // ---------------------------------------------------------------------
    // AC-4: Missing dependency seam
    // ---------------------------------------------------------------------

    public function test_AC4_ensure_false_throws_missing_dependency_with_actionable_message(): void
    {
        try {
            MissingDependencyException::ensure(false, 'softcreatr/jsonpath', 'JSONPath queries');
            $this->fail('Expected MissingDependencyException when the dependency is reported absent.');
        } catch (MissingDependencyException $e) {
            $this->assertStringContainsString('softcreatr/jsonpath', $e->getMessage());
            $this->assertStringContainsString('composer require softcreatr/jsonpath', $e->getMessage());
            $this->assertInstanceOf(JsonException::class, $e);
        }
    }

    public function test_AC4_ensure_true_does_not_throw(): void
    {
        MissingDependencyException::ensure(true, 'softcreatr/jsonpath', 'JSONPath queries');

        // Reaching here without an exception is the assertion.
        $this->expectNotToPerformAssertions();
    }

    // ---------------------------------------------------------------------
    // AC-5: Malformed JSON -> InvalidJsonException (and nothing else)
    // ---------------------------------------------------------------------

    public function test_AC5_malformed_json_throws_invalid_json_exception(): void
    {
        try {
            Json::query('{bad json', '$..x');
            $this->fail('Expected InvalidJsonException for malformed JSON input.');
        } catch (\Throwable $e) {
            $this->assertInstanceOf(InvalidJsonException::class, $e);
            $this->assertNotInstanceOf(JsonQueryException::class, $e);
            $this->assertNotInstanceOf(MissingDependencyException::class, $e);
        }
    }

    // ---------------------------------------------------------------------
    // AC-6: Invalid JSONPath expression -> JsonQueryException, no vendor leak
    // ---------------------------------------------------------------------

    public function test_AC6_invalid_expression_throws_json_query_exception(): void
    {
        try {
            Json::query($this->store(), '@@');
            $this->fail('Expected JsonQueryException for an invalid JSONPath expression.');
        } catch (\Throwable $e) {
            $this->assertInstanceOf(JsonQueryException::class, $e);
            $this->assertInstanceOf(JsonException::class, $e);
            // No third-party Flow\JSONPath exception type may surface to the caller.
            $this->assertStringNotContainsString('Flow\\JSONPath', get_class($e));
        }
    }

    // ---------------------------------------------------------------------
    // AC-9: Empty match -> [] (not null, no throw)
    // ---------------------------------------------------------------------

    public function test_AC9_empty_match_returns_empty_array(): void
    {
        $result = Json::query($this->store(), '$..nonexistent');

        $this->assertIsArray($result);
        $this->assertSame([], $result);
    }

    // ---------------------------------------------------------------------
    // AC-12: No third-party type leak in the returned data
    // ---------------------------------------------------------------------

    public function test_AC12_returned_values_are_plain_scalars_and_arrays(): void
    {
        // Scalar leaves.
        $prices = Json::query($this->store(), '$..price');
        $this->assertNoObjectsLeak($prices);

        // Nested-array leaves: whole book objects come back as associative arrays.
        $books = Json::query($this->store(), '$.store.book[*]');
        $this->assertNoObjectsLeak($books);
        $this->assertSame(
            ['category' => 'reference', 'title' => 'Sayings of the Century', 'price' => 8.95],
            $books[0],
        );
    }

    // ---------------------------------------------------------------------
    // AC-13: Marker consistency — all three are catchable as JsonException
    // ---------------------------------------------------------------------

    public function test_AC13_json_query_exception_caught_via_marker(): void
    {
        $this->expectException(JsonException::class);

        Json::query($this->store(), '@@');
    }

    public function test_AC13_invalid_json_exception_caught_via_marker(): void
    {
        $this->expectException(JsonException::class);

        Json::query('{bad json', '$..x');
    }

    public function test_AC13_missing_dependency_exception_caught_via_marker(): void
    {
        $this->expectException(JsonException::class);

        MissingDependencyException::ensure(false, 'softcreatr/jsonpath', 'JSONPath queries');
    }

    // ---------------------------------------------------------------------
    // Edge cases
    // ---------------------------------------------------------------------

    public function test_edge_query_against_empty_object_returns_empty_array(): void
    {
        $this->assertSame([], Json::query('{}', '$..anything'));
    }

    public function test_edge_query_against_empty_array_returns_empty_array(): void
    {
        $this->assertSame([], Json::query('[]', '$[*]'));
    }

    public function test_edge_query_against_scalar_root_does_not_leak_or_fatal(): void
    {
        // A bare scalar is valid JSON. Querying a child path against it cannot
        // match anything; the contract is an empty list, not a throw/null.
        $result = Json::query('42', '$.foo');

        $this->assertIsArray($result);
        $this->assertSame([], $result);
    }

    public function test_edge_single_match_still_returns_a_one_element_list(): void
    {
        $result = Json::query($this->store(), '$.store.bicycle.color');

        $this->assertSame(['red'], $result);
    }
}
