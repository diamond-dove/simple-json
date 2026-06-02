<?php

namespace DiamondDove\SimpleJson\Tests;

use DiamondDove\SimpleJson\Exceptions\InvalidJsonException;
use DiamondDove\SimpleJson\Exceptions\JsonException;
use DiamondDove\SimpleJson\Exceptions\JsonQueryException;
use DiamondDove\SimpleJson\Exceptions\MissingDependencyException;
use DiamondDove\SimpleJson\Json;

class JsonQueryTest extends TestCase
{
    private function store(): string
    {
        return <<<'JSON'
        {
            "store": {
                "book": [
                    {"category": "reference", "title": "Sayings of the Century", "price": 8.95},
                    {"category": "fiction", "title": "Sword of Honour", "price": 12.99},
                    {"category": "fiction", "title": "Moby Dick", "price": 8.99}
                ],
                "bicycle": {"color": "red", "price": 19.95}
            }
        }
        JSON;
    }

    public function testRecursiveDescentReturnsMatchedValues(): void
    {
        $this->assertSame(
            [8.95, 12.99, 8.99, 19.95],
            Json::query($this->store(), '$..price'),
        );
    }

    public function testWildcardReturnsMatchedValues(): void
    {
        $this->assertSame(
            ['Sayings of the Century', 'Sword of Honour', 'Moby Dick'],
            Json::query($this->store(), '$.store.book[*].title'),
        );
    }

    public function testFilterExpressionReturnsMatchedValues(): void
    {
        $this->assertEqualsCanonicalizing(
            ['Sayings of the Century', 'Moby Dick'],
            Json::query($this->store(), '$..book[?(@.price < 10)].title'),
        );
    }

    public function testEmptyMatchReturnsEmptyArray(): void
    {
        $this->assertSame([], Json::query($this->store(), '$..nonexistent'));
    }

    public function testInvalidExpressionThrowsJsonQueryException(): void
    {
        $this->expectException(JsonQueryException::class);

        Json::query($this->store(), '@@');
    }

    public function testJsonQueryExceptionIsCatchableAsJsonException(): void
    {
        try {
            Json::query($this->store(), '@@');
            $this->fail('Expected a JsonQueryException to be thrown.');
        } catch (JsonException $e) {
            $this->assertInstanceOf(JsonQueryException::class, $e);
        }
    }

    public function testMalformedJsonThrowsInvalidJsonException(): void
    {
        $this->expectException(InvalidJsonException::class);

        Json::query('{not valid json', '$..price');
    }

    public function testMissingDependencySeamThrowsWithActionableMessage(): void
    {
        try {
            MissingDependencyException::ensure(false, 'softcreatr/jsonpath', 'JSONPath queries');
            $this->fail('Expected a MissingDependencyException to be thrown.');
        } catch (MissingDependencyException $e) {
            $this->assertStringContainsString('softcreatr/jsonpath', $e->getMessage());
            $this->assertStringContainsString('composer require softcreatr/jsonpath', $e->getMessage());
            $this->assertInstanceOf(JsonException::class, $e);
        }
    }

    public function testMissingDependencySeamDoesNotThrowWhenInstalled(): void
    {
        MissingDependencyException::ensure(true, 'softcreatr/jsonpath', 'JSONPath queries');

        $this->expectNotToPerformAssertions();
    }
}
