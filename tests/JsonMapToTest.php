<?php

namespace DiamondDove\SimpleJson\Tests;

use DiamondDove\SimpleJson\Exceptions\InvalidJsonException;
use DiamondDove\SimpleJson\Exceptions\JsonException;
use DiamondDove\SimpleJson\Exceptions\JsonMappingException;
use DiamondDove\SimpleJson\Exceptions\MissingDependencyException;
use DiamondDove\SimpleJson\Json;

class JsonMapToTest extends TestCase
{
    public function testMapsListOfStrings(): void
    {
        $this->assertSame(['a', 'b'], Json::mapTo('["a","b"]', 'list<string>'));
    }

    public function testMapsBoundedInteger(): void
    {
        $this->assertSame(42, Json::mapTo('42', 'int<0, 100>'));
    }

    public function testMapsNonEmptyString(): void
    {
        $this->assertSame('hello', Json::mapTo('"hello"', 'non-empty-string'));
    }

    public function testMapsShapedArray(): void
    {
        $this->assertSame(
            ['name' => 'Ana', 'age' => 30],
            Json::mapTo('{"name":"Ana","age":30}', 'array{name: string, age: int}'),
        );
    }

    public function testDataViolatingSignatureThrowsJsonMappingException(): void
    {
        $this->expectException(JsonMappingException::class);

        Json::mapTo('999', 'int<0, 100>');
    }

    public function testMappingExceptionIsCatchableAsJsonExceptionWithoutLeakingVendorType(): void
    {
        try {
            Json::mapTo('"notalist"', 'list<string>');
            $this->fail('Expected a JsonMappingException to be thrown.');
        } catch (JsonException $e) {
            $this->assertInstanceOf(JsonMappingException::class, $e);
            // The signature and a human-readable reason are surfaced.
            $this->assertStringContainsString('list<string>', $e->getMessage());
            // The raw valinor error is chained but does not leak as the public type.
            $this->assertInstanceOf(\Throwable::class, $e->getPrevious());
        }
    }

    public function testNonEmptyStringViolationThrowsJsonMappingException(): void
    {
        $this->expectException(JsonMappingException::class);

        Json::mapTo('""', 'non-empty-string');
    }

    public function testMalformedJsonThrowsInvalidJsonException(): void
    {
        $this->expectException(InvalidJsonException::class);

        Json::mapTo('[not valid json', 'list<string>');
    }

    public function testInvalidSignatureThrowsInvalidArgumentException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Json::mapTo('{"x":1}', 'this-is-not-a-valid-type');
    }

    public function testMissingDependencySeamThrowsWithActionableMessage(): void
    {
        try {
            MissingDependencyException::ensure(false, 'cuyz/valinor', 'advanced type mapping');
            $this->fail('Expected a MissingDependencyException to be thrown.');
        } catch (MissingDependencyException $e) {
            $this->assertStringContainsString('cuyz/valinor', $e->getMessage());
            $this->assertStringContainsString('composer require cuyz/valinor', $e->getMessage());
            $this->assertInstanceOf(JsonException::class, $e);
        }
    }

    public function testMissingDependencySeamDoesNotThrowWhenInstalled(): void
    {
        MissingDependencyException::ensure(true, 'cuyz/valinor', 'advanced type mapping');

        $this->expectNotToPerformAssertions();
    }
}
