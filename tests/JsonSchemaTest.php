<?php

namespace DiamondDove\SimpleJson\Tests;

use DiamondDove\SimpleJson\Exceptions\InvalidJsonException;
use DiamondDove\SimpleJson\Exceptions\JsonException;
use DiamondDove\SimpleJson\Exceptions\JsonSchemaException;
use DiamondDove\SimpleJson\Exceptions\MissingDependencyException;
use DiamondDove\SimpleJson\Json;
use DiamondDove\SimpleJson\Schema\SchemaValidationResult;

class JsonSchemaTest extends TestCase
{
    private function userSchema(): string
    {
        return <<<'JSON'
        {
            "type": "object",
            "required": ["name", "age"],
            "properties": {
                "name": {"type": "string"},
                "age": {"type": "integer", "minimum": 0}
            }
        }
        JSON;
    }

    public function testConformingDocumentPasses(): void
    {
        $result = Json::validateSchema('{"name":"Ana","age":30}', $this->userSchema());

        $this->assertInstanceOf(SchemaValidationResult::class, $result);
        $this->assertTrue($result->passes());
        $this->assertFalse($result->fails());
        $this->assertSame([], $result->errors());
    }

    public function testNonConformingDocumentFailsWithHumanReadableMessages(): void
    {
        $result = Json::validateSchema('{"name":123,"age":-5}', $this->userSchema());

        $this->assertTrue($result->fails());
        $this->assertFalse($result->passes());

        $errors = $result->errors();
        $this->assertNotEmpty($errors);
        $this->assertTrue(array_is_list($errors));

        foreach ($errors as $message) {
            $this->assertIsString($message);
            $this->assertNotSame('', $message);
        }

        // Messages include the data location where opis provides one.
        $joined = implode("\n", $errors);
        $this->assertStringContainsString('/name', $joined);
        $this->assertStringContainsString('/age', $joined);
    }

    public function testMissingRequiredPropertyFails(): void
    {
        $result = Json::validateSchema('{"name":"Ana"}', $this->userSchema());

        $this->assertTrue($result->fails());
        $this->assertNotEmpty($result->errors());
    }

    public function testMatchesSchemaReturnsBoolean(): void
    {
        $this->assertTrue(Json::matchesSchema('{"name":"Ana","age":30}', $this->userSchema()));
        $this->assertFalse(Json::matchesSchema('{"name":123,"age":-5}', $this->userSchema()));
    }

    public function testMalformedDataJsonThrowsInvalidJsonException(): void
    {
        $this->expectException(InvalidJsonException::class);

        Json::validateSchema('{not valid json', $this->userSchema());
    }

    public function testMalformedSchemaJsonThrowsInvalidJsonException(): void
    {
        $this->expectException(InvalidJsonException::class);

        Json::validateSchema('{"name":"Ana","age":30}', '{not valid json');
    }

    public function testStructurallyInvalidSchemaThrowsJsonSchemaException(): void
    {
        // Parses as valid JSON, but opis rejects "not-a-real-type" as a JSON type.
        $this->expectException(JsonSchemaException::class);

        Json::validateSchema('{"x":1}', '{"type":"not-a-real-type"}');
    }

    public function testNonObjectSchemaThrowsJsonSchemaException(): void
    {
        // Valid JSON, but a JSON Schema must be an object or boolean, not a list.
        $this->expectException(JsonSchemaException::class);

        Json::validateSchema('{"x":1}', '[1,2,3]');
    }

    public function testJsonSchemaExceptionIsCatchableAsJsonException(): void
    {
        try {
            Json::validateSchema('{"x":1}', '{"type":"not-a-real-type"}');
            $this->fail('Expected a JsonSchemaException to be thrown.');
        } catch (JsonException $e) {
            $this->assertInstanceOf(JsonSchemaException::class, $e);
            $this->assertInstanceOf(\Throwable::class, $e->getPrevious());
        }
    }

    public function testBooleanSchemaIsSupported(): void
    {
        // `true` schema accepts anything; `false` schema rejects everything.
        $this->assertTrue(Json::matchesSchema('{"any":"thing"}', 'true'));
        $this->assertFalse(Json::matchesSchema('{"any":"thing"}', 'false'));
    }

    public function testRemoteRefIsNotFetchedAndDoesNotSilentlyPass(): void
    {
        // A schema whose validation hinges on an http:// $ref must NOT be fetched
        // over the network. opis surfaces it as a schema error (wrapped), never a
        // silent "valid". If it ever validated true, that would mean the remote
        // was fetched and trusted -- the SSRF risk this guards against.
        $schema = '{"$ref":"http://example.com/does-not-exist.json"}';

        try {
            $result = Json::validateSchema('{"x":1}', $schema);
            // It must not silently report the document as conforming.
            $this->assertTrue($result->fails(), 'Remote $ref must not validate as passing.');
        } catch (JsonSchemaException $e) {
            // The expected path: an unresolvable remote $ref is a schema error.
            $this->assertInstanceOf(JsonException::class, $e);
        }
    }

    public function testMissingDependencySeamThrowsWithActionableMessage(): void
    {
        try {
            MissingDependencyException::ensure(false, 'opis/json-schema', 'JSON Schema validation');
            $this->fail('Expected a MissingDependencyException to be thrown.');
        } catch (MissingDependencyException $e) {
            $this->assertStringContainsString('opis/json-schema', $e->getMessage());
            $this->assertStringContainsString('composer require opis/json-schema', $e->getMessage());
            $this->assertInstanceOf(JsonException::class, $e);
        }
    }

    public function testMissingDependencySeamDoesNotThrowWhenInstalled(): void
    {
        MissingDependencyException::ensure(true, 'opis/json-schema', 'JSON Schema validation');

        $this->expectNotToPerformAssertions();
    }
}
