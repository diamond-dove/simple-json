<?php

namespace DiamondDove\SimpleJson\Tests;

use DiamondDove\SimpleJson\Exceptions\InvalidJsonException;
use DiamondDove\SimpleJson\Exceptions\JsonTypeException;
use DiamondDove\SimpleJson\Json;
use DiamondDove\SimpleJson\JsonAccessor;

class JsonTest extends TestCase
{
    private function fixture(): JsonAccessor
    {
        return Json::parse((string) file_get_contents($this->getPath('nested_user.json')));
    }

    // AC-1
    public function testParseValidReturnsAccessor(): void
    {
        $this->assertInstanceOf(JsonAccessor::class, Json::parse('{"a":1}'));
    }

    // AC-2
    public function testObjectsDecodeToAssocArrays(): void
    {
        $this->assertSame(['a' => 1], Json::parse('{"a":1}')->array());
    }

    // AC-3
    public function testTryParseValidEquivalentToParse(): void
    {
        $this->assertSame(
            Json::parse('{"a":1}')->array(),
            Json::tryParse('{"a":1}')?->array(),
        );
    }

    // AC-4
    public function testPathTraversesNestedObjects(): void
    {
        $this->assertSame('Santo Domingo', $this->fixture()->path('user.address.city')->string());
    }

    // AC-5
    public function testPathIsCaseSensitive(): void
    {
        $this->expectException(JsonTypeException::class);
        $this->fixture()->path('User')->string();
    }

    // AC-6
    public function testPathReturnsNewInstanceLeavingOriginalUnchanged(): void
    {
        $root = $this->fixture();
        $child = $root->path('user.name');

        $this->assertNotSame($root, $child);
        $this->assertSame('Ana', $child->string());
        // original still navigable from the top
        $this->assertSame('Santo Domingo', $root->path('user.address.city')->string());
    }

    // AC-7
    public function testStringOnString(): void
    {
        $this->assertSame('Ana', $this->fixture()->path('user.name')->string());
    }

    // AC-8
    public function testIntOnInt(): void
    {
        $this->assertSame(10101, $this->fixture()->path('user.address.zip')->int());
    }

    // AC-9 / AC-15
    public function testNumericCoercionRules(): void
    {
        $this->assertSame(5.0, Json::parse('5')->float());
        $this->assertSame(5.0, Json::parse('5.0')->float());

        $this->expectException(JsonTypeException::class);
        Json::parse('5.0')->int();
    }

    public function testFloatWithFractionRejectedByInt(): void
    {
        $this->expectException(JsonTypeException::class);
        Json::parse('5.5')->int();
    }

    // AC-10
    public function testBoolOnTrue(): void
    {
        $this->assertTrue($this->fixture()->path('user.active')->bool());
    }

    public function testBoolRejectsInteger(): void
    {
        $this->expectException(JsonTypeException::class);
        Json::parse('1')->bool();
    }

    // AC-11
    public function testArrayOnListAndObject(): void
    {
        $this->assertSame([1, 2], Json::parse('[1,2]')->array());
        $this->assertSame(['a' => 1], Json::parse('{"a":1}')->array());
    }

    // AC-12
    public function testStrictMismatchThrows(): void
    {
        $this->expectException(JsonTypeException::class);
        $this->fixture()->path('user.name')->int();
    }

    // AC-13
    public function testStrictMissingPathThrows(): void
    {
        $this->expectException(JsonTypeException::class);
        $this->fixture()->path('user.nope')->string();
    }

    // AC-14
    public function testStrictPresentNullThrows(): void
    {
        $this->expectException(JsonTypeException::class);
        $this->fixture()->path('missingNull')->string();
    }

    // AC-16
    public function testOrReturnsActualWhenPresentAndTyped(): void
    {
        $f = $this->fixture();
        $this->assertSame('Ana', $f->path('user.name')->stringOr('x'));
        $this->assertSame(10101, $f->path('user.address.zip')->intOr(0));
        $this->assertSame(4.5, $f->path('user.score')->floatOr(0.0));
        $this->assertTrue($f->path('user.active')->boolOr(false));
        $this->assertSame(['a', 'b'], $f->path('user.tags')->arrayOr([]));
    }

    // AC-17
    public function testOrReturnsDefaultOnMissing(): void
    {
        $f = $this->fixture();
        $this->assertSame('d', $f->path('nope')->stringOr('d'));
        $this->assertSame(9, $f->path('nope')->intOr(9));
        $this->assertSame(9.0, $f->path('nope')->floatOr(9.0));
        $this->assertTrue($f->path('nope')->boolOr(true));
        $this->assertSame([1], $f->path('nope')->arrayOr([1]));
    }

    // AC-18
    public function testOrReturnsDefaultOnMismatch(): void
    {
        $f = $this->fixture();
        $this->assertSame('d', $f->path('count')->stringOr('d'));
        $this->assertSame(9, $f->path('user.name')->intOr(9));
        $this->assertSame(9.0, $f->path('user.name')->floatOr(9.0));
        $this->assertFalse($f->path('count')->boolOr(false));
        $this->assertSame([1], $f->path('count')->arrayOr([1]));
    }

    // AC-19
    public function testOrReturnsDefaultOnPresentNull(): void
    {
        $this->assertSame('d', $this->fixture()->path('missingNull')->stringOr('d'));
    }

    // AC-20
    public function testOrNullReturnsActualWhenPresentAndTyped(): void
    {
        $f = $this->fixture();
        $this->assertSame('Ana', $f->path('user.name')->stringOrNull());
        $this->assertSame(10101, $f->path('user.address.zip')->intOrNull());
        $this->assertSame(4.5, $f->path('user.score')->floatOrNull());
        $this->assertTrue($f->path('user.active')->boolOrNull());
        $this->assertSame(['a', 'b'], $f->path('user.tags')->arrayOrNull());
    }

    // AC-21
    public function testOrNullReturnsNullOnMissing(): void
    {
        $this->assertNull($this->fixture()->path('nope')->stringOrNull());
    }

    // AC-22
    public function testOrNullReturnsNullOnMismatch(): void
    {
        $this->assertNull($this->fixture()->path('count')->stringOrNull());
    }

    // AC-23
    public function testOrNullReturnsNullOnPresentNull(): void
    {
        $this->assertNull($this->fixture()->path('missingNull')->stringOrNull());
    }

    // AC-24
    public function testParseInvalidThrows(): void
    {
        $this->expectException(InvalidJsonException::class);
        Json::parse('{invalid}');
    }

    // AC-25
    public function testInvalidExceptionWrapsJsonException(): void
    {
        try {
            Json::parse('{invalid}');
            $this->fail('Expected InvalidJsonException');
        } catch (InvalidJsonException $e) {
            $this->assertInstanceOf(\JsonException::class, $e->getPrevious());
        }
    }

    // AC-26
    public function testTryParseInvalidReturnsNull(): void
    {
        $this->assertNull(Json::tryParse('{invalid}'));
        $this->assertNull(Json::tryParse(''));
    }

    // AC-27
    public function testRootIntTerminal(): void
    {
        $this->assertSame(5, Json::parse('5')->int());
    }

    // AC-28
    public function testParseNullIsValueBearingAccessor(): void
    {
        $accessor = Json::parse('null');
        $this->assertInstanceOf(JsonAccessor::class, $accessor);
        // present-null: type terminals reject, but it is not "missing"
        $this->assertNull($accessor->stringOrNull());

        $this->expectException(JsonTypeException::class);
        $accessor->string();
    }

    // AC-29
    public function testScalarRootPathIsMissing(): void
    {
        $accessor = Json::parse('5')->path('x');
        $this->assertNull($accessor->intOrNull());

        $this->expectException(JsonTypeException::class);
        $accessor->int();
    }

    // float strict-vs-lenient contract: a numeric STRING is not a float (no coercion)
    public function testFloatRejectsNumericString(): void
    {
        $this->assertNull(Json::parse('"5"')->floatOrNull());
        $this->assertSame(1.0, Json::parse('"5"')->floatOr(1.0));

        $this->expectException(JsonTypeException::class);
        Json::parse('"5"')->float();
    }

    // float strict terminal rejects present-null; lenient null terminal returns null
    public function testFloatOnPresentNull(): void
    {
        $this->assertNull(Json::parse('{"a":null}')->path('a')->floatOrNull());

        $this->expectException(JsonTypeException::class);
        Json::parse('{"a":null}')->path('a')->float();
    }

    // isPresent distinguishes present-but-null from missing and non-navigable roots
    public function testIsPresentDistinguishesPresentNullFromMissing(): void
    {
        $this->assertTrue(Json::parse('null')->isPresent());
        $this->assertFalse(Json::parse('{"a":1}')->path('missing')->isPresent());
        $this->assertFalse(Json::parse('5')->path('x')->isPresent());
        $this->assertTrue(Json::parse('{"a":null}')->path('a')->isPresent());
    }

    // limitation: empty string is invalid
    public function testEmptyStringIsInvalid(): void
    {
        $this->assertNull(Json::tryParse(''));

        $this->expectException(InvalidJsonException::class);
        Json::parse('');
    }

    // limitation: a literal dotted key collides with dot-path segmentation.
    // Laravel's Arr::has/Arr::get match the literal "weird.key" key first
    // (array_key_exists before segmenting), so it resolves to its value and the
    // intended {"weird": {"key": ...}} nesting is what becomes unaddressable.
    public function testDottedKeyIsAmbiguousUnderDotPath(): void
    {
        $this->assertSame('dotted', $this->fixture()->path('weird.key')->stringOrNull());

        // The nested form a caller might expect ($json['weird']['key']) is not present.
        $this->assertNull($this->fixture()->path('weird')->arrayOrNull());
    }
}
