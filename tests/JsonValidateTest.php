<?php

namespace DiamondDove\SimpleJson\Tests;

use DiamondDove\SimpleJson\Exceptions\InvalidJsonException;
use DiamondDove\SimpleJson\Exceptions\JsonException;
use DiamondDove\SimpleJson\Exceptions\JsonValidationException;
use DiamondDove\SimpleJson\Json;
use DiamondDove\SimpleJson\Validation\ParsedRule;
use DiamondDove\SimpleJson\Validation\Rule;
use DiamondDove\SimpleJson\Validation\RuleParser;
use DiamondDove\SimpleJson\Validation\ValidationResult;

class JsonValidateTest extends TestCase
{
    // AC-1 / AC-2
    public function testPassingValidationReportsSuccess(): void
    {
        $result = Json::validate('{"name":"Ana"}', ['name' => 'required|string']);

        $this->assertTrue($result->passes());
        $this->assertFalse($result->fails());
        $this->assertSame([], $result->errors());
    }

    // AC-3
    public function testValidatedReturnsPresentRuleNamedFields(): void
    {
        $result = Json::validate('{"name":"Ana","extra":1}', ['name' => 'required|string']);

        $this->assertSame(['name' => 'Ana'], $result->validated());
    }

    // AC-4 / AC-5
    public function testFailingValidationReportsErrors(): void
    {
        $result = Json::validate('{"age":"x"}', ['age' => 'int']);

        $this->assertFalse($result->passes());
        $this->assertTrue($result->fails());

        $errors = $result->errors();
        $this->assertArrayHasKey('age', $errors);
        $this->assertNotEmpty($errors['age']);
        $this->assertContainsOnlyString($errors['age']);
    }

    // AC-6
    public function testValidatedThrowsOnFailureExposingErrors(): void
    {
        $result = Json::validate('{"age":"x"}', ['age' => 'int']);

        try {
            $result->validated();
            $this->fail('Expected JsonValidationException');
        } catch (JsonValidationException $e) {
            $this->assertSame($result->errors(), $e->getErrors());
        }
    }

    // AC-7
    public function testValidationExceptionImplementsMarker(): void
    {
        $this->assertInstanceOf(JsonException::class, new JsonValidationException([]));
    }

    // AC-8
    public function testPipeAndArrayFormsAreEquivalent(): void
    {
        $parser = new RuleParser();

        $this->assertEquals(
            $parser->parse('required|min:18'),
            $parser->parse(['required', 'min:18']),
        );
    }

    // AC-9
    public function testParameterParsing(): void
    {
        $parser = new RuleParser();

        $this->assertEquals(
            [new ParsedRule(Rule::Between, ['1', '5'])],
            $parser->parse('between:1,5'),
        );

        $this->assertEquals(
            [new ParsedRule(Rule::In, ['a', 'b', 'c'])],
            $parser->parse('in:a,b,c'),
        );
    }

    // AC-10
    public function testRequiredFailsOnEmptyValues(): void
    {
        foreach (['{}', '{"v":null}', '{"v":""}', '{"v":[]}'] as $json) {
            $result = Json::validate($json, ['v' => 'required']);
            $this->assertTrue($result->fails(), "expected fail for {$json}");
            $this->assertSame(['The v field is required.'], $result->errors()['v']);
        }
    }

    // AC-11
    public function testNullableSkipsRemainingRulesOnNull(): void
    {
        $this->assertTrue(Json::validate('{"v":null}', ['v' => 'nullable|string'])->passes());
        // non-null value still subject to the remaining rules
        $this->assertTrue(Json::validate('{"v":5}', ['v' => 'nullable|int'])->passes());
        $this->assertTrue(Json::validate('{"v":"x"}', ['v' => 'nullable|int'])->fails());
    }

    // AC-12
    public function testStringStrict(): void
    {
        $this->assertTrue(Json::validate('{"v":"x"}', ['v' => 'string'])->passes());
        $this->assertTrue(Json::validate('{"v":5}', ['v' => 'string'])->fails());
    }

    // AC-13
    public function testIntStrictRejectsStringAndFloat(): void
    {
        $this->assertTrue(Json::validate('{"v":5}', ['v' => 'int'])->passes());
        $this->assertTrue(Json::validate('{"v":"5"}', ['v' => 'int'])->fails());
        $this->assertTrue(Json::validate('{"v":5.0}', ['v' => 'int'])->fails());
    }

    // AC-14
    public function testNumericStrict(): void
    {
        $this->assertTrue(Json::validate('{"v":5}', ['v' => 'numeric'])->passes());
        $this->assertTrue(Json::validate('{"v":5.5}', ['v' => 'numeric'])->passes());
        $this->assertTrue(Json::validate('{"v":"42"}', ['v' => 'numeric'])->fails());
        $this->assertTrue(Json::validate('{"v":true}', ['v' => 'numeric'])->fails());
        $this->assertTrue(Json::validate('{"v":[1]}', ['v' => 'numeric'])->fails());
        $this->assertTrue(Json::validate('{"v":"abc"}', ['v' => 'numeric'])->fails());
    }

    // AC-15
    public function testBoolRejectsZeroAndOne(): void
    {
        $this->assertTrue(Json::validate('{"v":true}', ['v' => 'bool'])->passes());
        $this->assertTrue(Json::validate('{"v":0}', ['v' => 'bool'])->fails());
        $this->assertTrue(Json::validate('{"v":1}', ['v' => 'bool'])->fails());
    }

    // AC-16
    public function testArrayRule(): void
    {
        $this->assertTrue(Json::validate('{"v":[1,2]}', ['v' => 'array'])->passes());
        $this->assertTrue(Json::validate('{"v":"x"}', ['v' => 'array'])->fails());
    }

    // AC-17
    public function testEmailRule(): void
    {
        $this->assertTrue(Json::validate('{"v":"a@b.com"}', ['v' => 'email'])->passes());
        $this->assertTrue(Json::validate('{"v":"nope"}', ['v' => 'email'])->fails());
        $this->assertTrue(Json::validate('{"v":5}', ['v' => 'email'])->fails());
    }

    // AC-18 (numeric magnitude)
    public function testMinMaxBetweenOnNumbers(): void
    {
        $this->assertTrue(Json::validate('{"v":18}', ['v' => 'min:18'])->passes());
        $this->assertTrue(Json::validate('{"v":17}', ['v' => 'min:18'])->fails());
        $this->assertTrue(Json::validate('{"v":10}', ['v' => 'max:10'])->passes());
        $this->assertTrue(Json::validate('{"v":11}', ['v' => 'max:10'])->fails());
        $this->assertTrue(Json::validate('{"v":3}', ['v' => 'between:1,5'])->passes());
        $this->assertTrue(Json::validate('{"v":6}', ['v' => 'between:1,5'])->fails());
    }

    // AC-19 (string mb_strlen)
    public function testMinMaxOnStrings(): void
    {
        $this->assertTrue(Json::validate('{"v":"abc"}', ['v' => 'min:3'])->passes());
        $this->assertTrue(Json::validate('{"v":"ab"}', ['v' => 'min:3'])->fails());
        $this->assertTrue(Json::validate('{"v":"ab"}', ['v' => 'max:2'])->passes());
        $this->assertTrue(Json::validate('{"v":"abc"}', ['v' => 'max:2'])->fails());
    }

    // AC-20 (array count)
    public function testMinMaxOnArrays(): void
    {
        $this->assertTrue(Json::validate('{"v":[1,2,3]}', ['v' => 'min:3'])->passes());
        $this->assertTrue(Json::validate('{"v":[1]}', ['v' => 'min:3'])->fails());
        $this->assertTrue(Json::validate('{"v":[1,2]}', ['v' => 'max:2'])->passes());
        $this->assertTrue(Json::validate('{"v":[1,2,3]}', ['v' => 'max:2'])->fails());
    }

    public function testSizeRuleFailsForUnsupportedType(): void
    {
        $this->assertTrue(Json::validate('{"v":true}', ['v' => 'min:1'])->fails());
    }

    // AC-21
    public function testInUsesStringComparison(): void
    {
        $this->assertTrue(Json::validate('{"v":5}', ['v' => 'in:5,6'])->passes());
        $this->assertTrue(Json::validate('{"v":"a"}', ['v' => 'in:a,b'])->passes());
        $this->assertTrue(Json::validate('{"v":7}', ['v' => 'in:5,6'])->fails());
        $this->assertTrue(Json::validate('{"v":[1]}', ['v' => 'in:5,6'])->fails());
    }

    // AC-22
    public function testRegexVerbatimAndNonStringFails(): void
    {
        $this->assertTrue(Json::validate('{"v":"abc123"}', ['v' => 'regex:/^[a-z]+\d+$/'])->passes());
        $this->assertTrue(Json::validate('{"v":"ABC"}', ['v' => 'regex:/^[a-z]+\d+$/'])->fails());
        $this->assertTrue(Json::validate('{"v":5}', ['v' => 'regex:/^\d+$/'])->fails());
    }

    public function testRegexParameterIsNotCommaSplit(): void
    {
        $parser = new RuleParser();
        $parsed = $parser->parse('regex:/^a,b$/');

        $this->assertSame(['/^a,b$/'], $parsed[0]->parameters);
    }

    // AC-23
    public function testUnknownRuleThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new RuleParser())->parse('bogus');
    }

    // AC-24
    public function testMissingRequiredParameterThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new RuleParser())->parse('min');
    }

    // between arity: exactly one parameter -> throws at parse time
    public function testBetweenWithOneParameterThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Json::validate('{"v":3}', ['v' => 'between:5']);
    }

    // between arity: no parameters -> throws at parse time
    public function testBetweenWithNoParametersThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Json::validate('{"v":3}', ['v' => 'between:']);
    }

    // between arity: three parameters -> throws at parse time
    public function testBetweenWithThreeParametersThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Json::validate('{"v":3}', ['v' => 'between:1,2,3']);
    }

    // between arity regression: a valid two-parameter between still works
    public function testValidBetweenStillWorks(): void
    {
        $this->assertTrue(Json::validate('{"v":3}', ['v' => 'between:1,5'])->passes());
        $this->assertTrue(Json::validate('{"v":9}', ['v' => 'between:1,5'])->fails());
    }

    // Locks the (string) compare contract: float 5.0 stringifies to "5" and matches in:5,6
    public function testInFloatMatchesViaStringCompare(): void
    {
        $this->assertTrue(Json::validate('{"v":5.0}', ['v' => 'in:5,6'])->passes());
    }

    // AC-25
    public function testInvalidJsonThrows(): void
    {
        $this->expectException(InvalidJsonException::class);
        Json::validate('{invalid}', ['v' => 'string']);
    }

    // AC-26
    public function testMultipleRulesAccumulateOnOneField(): void
    {
        $result = Json::validate('{"v":"x"}', ['v' => 'int|min:5']);

        $this->assertTrue($result->fails());
        $this->assertCount(2, $result->errors()['v']);
    }

    // AC-27
    public function testOnlyFailingFieldsAppearInErrors(): void
    {
        $result = Json::validate(
            '{"name":"Ana","age":"x"}',
            ['name' => 'string', 'age' => 'int'],
        );

        $errors = $result->errors();
        $this->assertArrayHasKey('age', $errors);
        $this->assertArrayNotHasKey('name', $errors);
    }

    // AC-28
    public function testNestedDotPathAddressesNestedValue(): void
    {
        $result = Json::validate('{"user":{"name":5}}', ['user.name' => 'string']);

        $this->assertTrue($result->fails());
        $this->assertArrayHasKey('user.name', $result->errors());
    }

    // AC-29
    public function testFieldPathsAreCaseSensitive(): void
    {
        // 'Name' is absent (only 'name' present); with no required rule it is skipped
        $result = Json::validate('{"name":"Ana"}', ['Name' => 'string']);

        $this->assertTrue($result->passes());
    }

    // AC-30
    public function testMessagesAreHumanReadableAndFieldIdentifying(): void
    {
        $result = Json::validate('{}', ['age' => 'required']);

        $this->assertSame('The age field is required.', $result->errors()['age'][0]);
    }

    // Edge: absent field with no required + only type rules is skipped
    public function testAbsentFieldWithoutRequiredIsSkipped(): void
    {
        $this->assertTrue(Json::validate('{}', ['v' => 'string|min:3'])->passes());
    }

    // Edge: required + nullable contradiction -> required wins
    public function testRequiredBeatsNullableWhenNull(): void
    {
        $result = Json::validate('{"v":null}', ['v' => 'required|nullable']);

        $this->assertTrue($result->fails());
        $this->assertSame(['The v field is required.'], $result->errors()['v']);
    }

    // Edge: empty rules -> passes, validated empty
    public function testEmptyRulesPassWithNoValidated(): void
    {
        $result = Json::validate('{"a":1}', []);

        $this->assertTrue($result->passes());
        $this->assertSame([], $result->validated());
    }

    // Edge: between:5,5 inclusive boundary
    public function testBetweenInclusiveBoundary(): void
    {
        $this->assertTrue(Json::validate('{"v":5}', ['v' => 'between:5,5'])->passes());
    }

    // Edge: min:0
    public function testMinZero(): void
    {
        $this->assertTrue(Json::validate('{"v":0}', ['v' => 'min:0'])->passes());
        $this->assertTrue(Json::validate('{"v":""}', ['v' => 'nullable|min:0'])->passes());
    }

    public function testValidationResultIsConstructible(): void
    {
        $result = new ValidationResult([], ['a' => 1]);
        $this->assertSame(['a' => 1], $result->validated());
    }
}
