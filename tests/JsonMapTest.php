<?php

namespace DiamondDove\SimpleJson\Tests;

use DiamondDove\SimpleJson\Exceptions\InvalidJsonException;
use DiamondDove\SimpleJson\Exceptions\JsonException;
use DiamondDove\SimpleJson\Exceptions\JsonMappingException;
use DiamondDove\SimpleJson\Json;
use DiamondDove\SimpleJson\Tests\Fixtures\AbstractData;
use DiamondDove\SimpleJson\Tests\Fixtures\AddressData;
use DiamondDove\SimpleJson\Tests\Fixtures\EmptyData;
use DiamondDove\SimpleJson\Tests\Fixtures\MixedData;
use DiamondDove\SimpleJson\Tests\Fixtures\NonPromotedData;
use DiamondDove\SimpleJson\Tests\Fixtures\ObjectParamData;
use DiamondDove\SimpleJson\Tests\Fixtures\PrivateCtorData;
use DiamondDove\SimpleJson\Tests\Fixtures\RequiredData;
use DiamondDove\SimpleJson\Tests\Fixtures\ScalarsData;
use DiamondDove\SimpleJson\Tests\Fixtures\SomeInterface;
use DiamondDove\SimpleJson\Tests\Fixtures\Status;
use DiamondDove\SimpleJson\Tests\Fixtures\TagData;
use DiamondDove\SimpleJson\Tests\Fixtures\ThrowingCtorData;
use DiamondDove\SimpleJson\Tests\Fixtures\UntypedData;
use DiamondDove\SimpleJson\Tests\Fixtures\UserData;
use DiamondDove\SimpleJson\Tests\Fixtures\VariadicData;

class JsonMapTest extends TestCase
{
    // AC-1: scalar hydration from array
    public function testScalarHydrationFromArray(): void
    {
        $user = Json::map(['name' => 'Ana', 'age' => 30], UserData::class);

        $this->assertSame('Ana', $user->name);
        $this->assertSame(30, $user->age);
    }

    // AC-2: from JSON string equals array source
    public function testHydrationFromJsonStringEqualsArray(): void
    {
        $fromString = Json::map('{"name":"Ana","age":30}', UserData::class);
        $fromArray = Json::map(['name' => 'Ana', 'age' => 30], UserData::class);

        $this->assertEquals($fromArray, $fromString);
    }

    // AC-3: result instanceof className
    public function testResultIsInstanceOfTarget(): void
    {
        $this->assertInstanceOf(UserData::class, Json::map(['name' => 'Ana', 'age' => 1], UserData::class));
    }

    // AC-4: generic inference (asserted via PHPStan); runtime instanceof here
    public function testGenericInferenceInstanceOf(): void
    {
        $user = Json::map(['name' => 'Ana', 'age' => 1], UserData::class);
        $this->assertInstanceOf(UserData::class, $user);
    }

    // AC-5: row-wise mapping
    public function testRowWiseMapping(): void
    {
        $rows = [
            ['name' => 'Ana', 'age' => 30],
            ['name' => 'Bob', 'age' => 40],
        ];

        $users = array_map(static fn (array $r): UserData => Json::map($r, UserData::class), $rows);

        $this->assertCount(2, $users);
        $this->assertContainsOnlyInstancesOf(UserData::class, $users);
        $this->assertSame('Bob', $users[1]->name);
    }

    // AC-6: nested DTO hydrated
    public function testNestedDtoHydrated(): void
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

    // AC-7: nullable nested absent and present-null both become null
    public function testNullableNestedAbsentOrNullBecomesNull(): void
    {
        $absent = Json::map(['name' => 'Ana', 'age' => 1], UserData::class);
        $this->assertNull($absent->address);

        $null = Json::map(['name' => 'Ana', 'age' => 1, 'address' => null], UserData::class);
        $this->assertNull($null->address);
    }

    // AC-8: missing key with default uses default
    public function testMissingKeyWithDefaultUsesDefault(): void
    {
        $user = Json::map(['name' => 'Ana', 'age' => 1], UserData::class);
        $this->assertSame(Status::Active, $user->status);
        $this->assertSame([], $user->tags);
    }

    // AC-9: missing, nullable, no default -> null
    public function testMissingNullableNoDefaultBecomesNull(): void
    {
        $address = Json::map(['city' => 'SD'], AddressData::class);
        $this->assertNull($address->zip);
    }

    // AC-10: missing required -> JsonMappingException (catchable as JsonException)
    public function testMissingRequiredThrows(): void
    {
        try {
            Json::map([], RequiredData::class);
            $this->fail('Expected JsonMappingException');
        } catch (JsonMappingException $e) {
            $this->assertInstanceOf(JsonException::class, $e);
        }
    }

    // AC-11: valid enum case
    public function testValidEnumCase(): void
    {
        $user = Json::map(['name' => 'Ana', 'age' => 1, 'status' => 'inactive'], UserData::class);
        $this->assertSame(Status::Inactive, $user->status);
    }

    // AC-12: invalid enum value -> JsonMappingException (no \ValueError)
    public function testInvalidEnumValueThrows(): void
    {
        $this->expectException(JsonMappingException::class);
        Json::map(['name' => 'Ana', 'age' => 1, 'status' => 'nope'], UserData::class);
    }

    // AC-13: #[ListOf] produces list<TagData> in order
    public function testListOfProducesDtoListInOrder(): void
    {
        $user = Json::map([
            'name' => 'Ana',
            'age' => 1,
            'tags' => [['label' => 'a'], ['label' => 'b']],
        ], UserData::class);

        $this->assertCount(2, $user->tags);
        $this->assertContainsOnlyInstancesOf(TagData::class, $user->tags);
        $this->assertSame('a', $user->tags[0]->label);
        $this->assertSame('b', $user->tags[1]->label);
    }

    // AC-14: #[ListOf] empty [] -> []
    public function testListOfEmpty(): void
    {
        $user = Json::map(['name' => 'Ana', 'age' => 1, 'tags' => []], UserData::class);
        $this->assertSame([], $user->tags);
    }

    // AC-15: int rejects 5.0 and "5"
    public function testIntRejectsFloatAndString(): void
    {
        try {
            Json::map(['name' => 'x', 'count' => 5.0, 'ratio' => 1.0, 'active' => true, 'meta' => []], ScalarsData::class);
            $this->fail('Expected JsonMappingException for float into int');
        } catch (JsonMappingException $e) {
            $this->assertStringContainsString('int', $e->getMessage());
            $this->assertStringContainsString('float', $e->getMessage());
        }

        $this->expectException(JsonMappingException::class);
        Json::map(['name' => 'x', 'count' => '5', 'ratio' => 1.0, 'active' => true, 'meta' => []], ScalarsData::class);
    }

    // AC-16: float accepts int (->float) and float; rejects "5"
    public function testFloatAcceptsIntAndFloatRejectsString(): void
    {
        $fromInt = Json::map(['name' => 'x', 'count' => 1, 'ratio' => 5, 'active' => true, 'meta' => []], ScalarsData::class);
        $this->assertSame(5.0, $fromInt->ratio);

        $fromFloat = Json::map(['name' => 'x', 'count' => 1, 'ratio' => 5.5, 'active' => true, 'meta' => []], ScalarsData::class);
        $this->assertSame(5.5, $fromFloat->ratio);

        $this->expectException(JsonMappingException::class);
        Json::map(['name' => 'x', 'count' => 1, 'ratio' => '5', 'active' => true, 'meta' => []], ScalarsData::class);
    }

    // AC-17: string/bool/array strict (bool rejects 1; string rejects 5)
    public function testStrictStringBoolArray(): void
    {
        $this->assertException(static fn () => Json::map(
            ['name' => 5, 'count' => 1, 'ratio' => 1.0, 'active' => true, 'meta' => []],
            ScalarsData::class,
        ));

        $this->assertException(static fn () => Json::map(
            ['name' => 'x', 'count' => 1, 'ratio' => 1.0, 'active' => 1, 'meta' => []],
            ScalarsData::class,
        ));

        $this->assertException(static fn () => Json::map(
            ['name' => 'x', 'count' => 1, 'ratio' => 1.0, 'active' => true, 'meta' => 'nope'],
            ScalarsData::class,
        ));
    }

    // AC-18: extra keys ignored
    public function testExtraKeysIgnored(): void
    {
        $user = Json::map(['name' => 'Ana', 'age' => 1, 'unknown' => 'x'], UserData::class);
        $this->assertSame('Ana', $user->name);
    }

    // AC-19: invalid JSON string -> InvalidJsonException (NOT JsonMappingException)
    public function testInvalidJsonStringThrowsInvalidJson(): void
    {
        $this->expectException(InvalidJsonException::class);
        Json::map('{invalid}', UserData::class);
    }

    // AC-20: class-typed param with non-array source value -> JsonMappingException
    public function testClassTypedParamWithNonArrayValueThrows(): void
    {
        $this->expectException(JsonMappingException::class);
        Json::map(['name' => 'Ana', 'age' => 1, 'address' => 'oops'], UserData::class);
    }

    // resolved edge: present-null on non-nullable scalar -> JsonMappingException
    public function testPresentNullOnNonNullableScalarThrows(): void
    {
        $this->expectException(JsonMappingException::class);
        Json::map(['name' => null, 'age' => 1], UserData::class);
    }

    // resolved edge: top-level list source -> JsonMappingException
    public function testTopLevelListThrows(): void
    {
        $this->expectException(JsonMappingException::class);
        Json::map([1, 2, 3], UserData::class);
    }

    // resolved edge: top-level scalar source (via JSON string) -> JsonMappingException
    public function testTopLevelScalarThrows(): void
    {
        $this->expectException(JsonMappingException::class);
        Json::map('5', UserData::class);
    }

    // resolved edge: empty array is a valid empty object
    public function testEmptyArrayMapsEmptyObject(): void
    {
        $this->assertInstanceOf(EmptyData::class, Json::map([], EmptyData::class));
    }

    // resolved edge: abstract target -> JsonMappingException
    public function testAbstractTargetThrows(): void
    {
        $this->expectException(JsonMappingException::class);
        Json::map(['name' => 'x'], AbstractData::class);
    }

    // resolved edge: interface target -> JsonMappingException
    public function testInterfaceTargetThrows(): void
    {
        $this->expectException(JsonMappingException::class);
        /** @phpstan-ignore-next-line intentionally passing a non-instantiable type */
        Json::map(['name' => 'x'], SomeInterface::class);
    }

    // resolved edge: private constructor target -> JsonMappingException
    public function testPrivateConstructorTargetThrows(): void
    {
        $this->expectException(JsonMappingException::class);
        Json::map(['name' => 'x'], PrivateCtorData::class);
    }

    // resolved edge: malformed #[ListOf] (not a list) -> JsonMappingException
    public function testListOfNotAListThrows(): void
    {
        $this->expectException(JsonMappingException::class);
        Json::map(['name' => 'Ana', 'age' => 1, 'tags' => ['label' => 'x']], UserData::class);
    }

    // resolved edge: malformed #[ListOf] (element not object) -> JsonMappingException
    public function testListOfElementNotObjectThrows(): void
    {
        $this->expectException(JsonMappingException::class);
        Json::map(['name' => 'Ana', 'age' => 1, 'tags' => ['scalar']], UserData::class);
    }

    // resolved edge: non-promoted constructor param is still filled by name
    public function testNonPromotedParamFilledByName(): void
    {
        $data = Json::map(['name' => 'Ana', 'age' => 30], NonPromotedData::class);
        $this->assertSame('Ana', $data->name);
        $this->assertSame(30, $data->age);
    }

    // resolved edge: map from array equals map from equivalent JSON string (full DTO)
    public function testArraySourceEqualsJsonStringSource(): void
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
    }

    // Fix 1: a DTO constructor throwing a normal exception is wrapped, not leaked
    public function testThrowingConstructorIsWrappedAsJsonMappingException(): void
    {
        try {
            Json::map(['age' => -1], ThrowingCtorData::class);
            $this->fail('Expected JsonMappingException');
        } catch (JsonMappingException $e) {
            $this->assertInstanceOf(JsonException::class, $e);
            $this->assertInstanceOf(\InvalidArgumentException::class, $e->getPrevious());
            $this->assertSame('age must not be negative', $e->getPrevious()->getMessage());
        }
    }

    // Fix 1: the wrapped throwable is catchable as the JsonException marker
    public function testThrowingConstructorCatchableAsJsonException(): void
    {
        $this->expectException(JsonException::class);
        Json::map(['age' => -1], ThrowingCtorData::class);
    }

    // Fix 2: a variadic constructor param is a deliberate unsupportedType outcome
    public function testVariadicConstructorThrows(): void
    {
        try {
            Json::map(['items' => [1, 2, 3]], VariadicData::class);
            $this->fail('Expected JsonMappingException');
        } catch (JsonMappingException $e) {
            $this->assertStringContainsString('unsupported type', $e->getMessage());
        }
    }

    // Fix 3: a mixed-typed param passes a present non-null value through unchanged
    public function testMixedParamPassesPresentValueThrough(): void
    {
        $fromArray = Json::map(['value' => ['a' => 1, 'b' => 2]], MixedData::class);
        $this->assertSame(['a' => 1, 'b' => 2], $fromArray->value);

        $fromString = Json::map(['value' => 'hello'], MixedData::class);
        $this->assertSame('hello', $fromString->value);
    }

    // Fix 3: a mixed-typed param honors absent->default and present-null->null
    public function testMixedParamAbsentDefaultAndPresentNull(): void
    {
        $absent = Json::map([], MixedData::class);
        $this->assertSame('default', $absent->value);

        $null = Json::map(['value' => null], MixedData::class);
        $this->assertNull($null->value);
    }

    // Fix 3: an untyped param accepts a present value as-is
    public function testUntypedParamAcceptsPresentValue(): void
    {
        $data = Json::map(['value' => [1, 2, 3]], UntypedData::class);
        $this->assertSame([1, 2, 3], $data->value);
    }

    // Fix 3: an object-typed param with a present value -> unsupportedType
    public function testObjectTypedParamThrows(): void
    {
        try {
            Json::map(['payload' => ['x' => 1]], ObjectParamData::class);
            $this->fail('Expected JsonMappingException');
        } catch (JsonMappingException $e) {
            $this->assertStringContainsString('unsupported type', $e->getMessage());
        }
    }

    // Fix 4 (lock): #[ListOf(TagData::class)] with a null element -> JsonMappingException
    public function testListOfWithNullElementThrows(): void
    {
        $this->expectException(JsonMappingException::class);
        Json::map(['name' => 'Ana', 'age' => 1, 'tags' => [['label' => 'a'], null]], UserData::class);
    }

    private function assertException(callable $fn): void
    {
        try {
            $fn();
        } catch (JsonMappingException) {
            $this->addToAssertionCount(1);

            return;
        }

        $this->fail('Expected JsonMappingException');
    }
}
