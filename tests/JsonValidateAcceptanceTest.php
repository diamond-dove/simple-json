<?php

namespace DiamondDove\SimpleJson\Tests;

use DiamondDove\SimpleJson\Exceptions\InvalidJsonException;
use DiamondDove\SimpleJson\Exceptions\JsonException;
use DiamondDove\SimpleJson\Exceptions\JsonValidationException;
use DiamondDove\SimpleJson\Json;
use DiamondDove\SimpleJson\Validation\ValidationResult;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Outside-in acceptance tests for Phase 2: Json::validate.
 *
 * These exercise ONLY the public API exactly as a consuming developer would:
 *   use DiamondDove\SimpleJson\Json;
 *   $result = Json::validate($json, $rules);
 * and assert on the returned ValidationResult / thrown exceptions. No internal
 * collaborators (Validator, RuleParser, ParsedRule) are touched directly.
 */
class JsonValidateAcceptanceTest extends TestCase
{
    // ----- AC-1: all-pass => passes()=true, fails()=false -----------------
    public function testAc1AllPassReportsPassesTrueAndFailsFalse(): void
    {
        $result = Json::validate('{"name":"Ana","age":30}', [
            'name' => 'required|string',
            'age' => 'required|int',
        ]);

        $this->assertInstanceOf(ValidationResult::class, $result);
        $this->assertTrue($result->passes());
        $this->assertFalse($result->fails());
    }

    // ----- AC-2: all-pass => errors() === [] ------------------------------
    public function testAc2AllPassErrorsAreEmpty(): void
    {
        $result = Json::validate('{"name":"Ana"}', ['name' => 'required|string']);

        $this->assertSame([], $result->errors());
    }

    // ----- AC-3: all-pass => validated() returns rule-named present fields, no throw
    public function testAc3ValidatedReturnsRuleNamedPresentFieldsWithoutThrowing(): void
    {
        // "extra" is present but not rule-named => excluded.
        // "nickname" is rule-named but absent => excluded.
        $result = Json::validate('{"name":"Ana","age":30,"extra":"ignored"}', [
            'name' => 'required|string',
            'age' => 'required|int',
            'nickname' => 'string',
        ]);

        $this->assertSame(['name' => 'Ana', 'age' => 30], $result->validated());
    }

    // ----- AC-4: any-fail => passes()=false, fails()=true ------------------
    public function testAc4AnyFailReportsPassesFalseAndFailsTrue(): void
    {
        $result = Json::validate('{"age":"x"}', ['age' => 'int']);

        $this->assertFalse($result->passes());
        $this->assertTrue($result->fails());
    }

    // ----- AC-5: errors() is array<string,list<string>> keyed by field, non-empty lists
    public function testAc5ErrorsKeyedByFieldWithNonEmptyMessageLists(): void
    {
        $result = Json::validate('{"age":"x","email":"nope"}', [
            'age' => 'int',
            'email' => 'email',
        ]);

        $errors = $result->errors();

        $this->assertArrayHasKey('age', $errors);
        $this->assertArrayHasKey('email', $errors);
        foreach ($errors as $field => $messages) {
            $this->assertIsString($field);
            $this->assertIsList($messages);
            $this->assertNotEmpty($messages);
            foreach ($messages as $message) {
                $this->assertIsString($message);
            }
        }
    }

    // ----- AC-6: validated() throws JsonValidationException exposing same errors
    public function testAc6ValidatedThrowsValidationExceptionExposingSameErrors(): void
    {
        $result = Json::validate('{"age":"x"}', ['age' => 'int']);

        try {
            $result->validated();
            $this->fail('Expected JsonValidationException to be thrown.');
        } catch (JsonValidationException $e) {
            $this->assertSame($result->errors(), $e->getErrors());
        }
    }

    // ----- AC-7: JsonValidationException is catchable as the JsonException marker
    public function testAc7ValidationExceptionIsCatchableAsJsonExceptionMarker(): void
    {
        $result = Json::validate('{"age":"x"}', ['age' => 'int']);

        $this->expectException(JsonException::class);

        $result->validated();
    }

    // ----- AC-8: pipe string === array form -------------------------------
    public function testAc8PipeStringAndArrayRuleFormsAreEquivalent(): void
    {
        $json = '{"age":"x"}';

        $pipe = Json::validate($json, ['age' => 'required|int|min:1']);
        $array = Json::validate($json, ['age' => ['required', 'int', 'min:1']]);

        $this->assertSame($pipe->passes(), $array->passes());
        $this->assertSame($pipe->errors(), $array->errors());
    }

    // ----- AC-9: parameterized rules min:18, between:1,5, in:a,b,c ---------
    public function testAc9ParameterizedRulesAccepted(): void
    {
        $pass = Json::validate('{"age":18,"score":3,"grade":"b"}', [
            'age' => 'min:18',
            'score' => 'between:1,5',
            'grade' => 'in:a,b,c',
        ]);
        $this->assertTrue($pass->passes());

        $fail = Json::validate('{"age":17,"score":9,"grade":"z"}', [
            'age' => 'min:18',
            'score' => 'between:1,5',
            'grade' => 'in:a,b,c',
        ]);
        $this->assertTrue($fail->fails());
        $this->assertArrayHasKey('age', $fail->errors());
        $this->assertArrayHasKey('score', $fail->errors());
        $this->assertArrayHasKey('grade', $fail->errors());
    }

    // ----- AC-10: required fails on missing / null / '' / [] --------------
    /**
     * @return array<string, array{string}>
     */
    public static function requiredEmptyProvider(): array
    {
        return [
            'missing' => ['{"other":1}'],
            'null' => ['{"field":null}'],
            'empty string' => ['{"field":""}'],
            'empty array' => ['{"field":[]}'],
        ];
    }

    #[DataProvider('requiredEmptyProvider')]
    public function testAc10RequiredFailsOnEmptyVariants(string $json): void
    {
        $result = Json::validate($json, ['field' => 'required']);

        $this->assertTrue($result->fails(), "required should fail for: {$json}");
        $this->assertArrayHasKey('field', $result->errors());
    }

    public function testAc10RequiredPassesWhenPresentAndNonEmpty(): void
    {
        $result = Json::validate('{"field":"value"}', ['field' => 'required']);

        $this->assertTrue($result->passes());
    }

    // ----- AC-11: nullable + null skips remaining rules (no error); non-null has no effect
    public function testAc11NullableNullSkipsRemainingRules(): void
    {
        // Without nullable, a null would fail the string rule.
        $result = Json::validate('{"field":null}', ['field' => 'nullable|string']);

        $this->assertTrue($result->passes());
        $this->assertSame([], $result->errors());
    }

    public function testAc11NullableHasNoEffectOnNonNullValue(): void
    {
        // Non-null value still gets type-checked; an int fails string.
        $result = Json::validate('{"field":123}', ['field' => 'nullable|string']);

        $this->assertTrue($result->fails());
        $this->assertArrayHasKey('field', $result->errors());
    }

    // ----- AC-12: string strict (is_string) -------------------------------
    public function testAc12StringRuleIsStrict(): void
    {
        $this->assertTrue(Json::validate('{"v":"hi"}', ['v' => 'string'])->passes());
        $this->assertTrue(Json::validate('{"v":5}', ['v' => 'string'])->fails());
        $this->assertTrue(Json::validate('{"v":true}', ['v' => 'string'])->fails());
    }

    // ----- AC-13: int strict ("5" and 5.0 fail) ---------------------------
    public function testAc13IntRuleIsStrict(): void
    {
        $this->assertTrue(Json::validate('{"v":5}', ['v' => 'int'])->passes());
        $this->assertTrue(Json::validate('{"v":"5"}', ['v' => 'int'])->fails());
        $this->assertTrue(Json::validate('{"v":5.0}', ['v' => 'int'])->fails());
    }

    // ----- AC-14: numeric strict ("42" fails; int & float pass) -----------
    public function testAc14NumericRuleIsStrict(): void
    {
        $this->assertTrue(Json::validate('{"v":42}', ['v' => 'numeric'])->passes());
        $this->assertTrue(Json::validate('{"v":4.2}', ['v' => 'numeric'])->passes());
        $this->assertTrue(Json::validate('{"v":"42"}', ['v' => 'numeric'])->fails());
    }

    // ----- AC-15: bool rejects 0/1 (is_bool) ------------------------------
    public function testAc15BoolRuleRejectsIntegersZeroAndOne(): void
    {
        $this->assertTrue(Json::validate('{"v":true}', ['v' => 'bool'])->passes());
        $this->assertTrue(Json::validate('{"v":false}', ['v' => 'bool'])->passes());
        $this->assertTrue(Json::validate('{"v":1}', ['v' => 'bool'])->fails());
        $this->assertTrue(Json::validate('{"v":0}', ['v' => 'bool'])->fails());
    }

    // ----- AC-16: array (is_array) ----------------------------------------
    public function testAc16ArrayRule(): void
    {
        $this->assertTrue(Json::validate('{"v":[1,2,3]}', ['v' => 'array'])->passes());
        $this->assertTrue(Json::validate('{"v":{"a":1}}', ['v' => 'array'])->passes());
        $this->assertTrue(Json::validate('{"v":"x"}', ['v' => 'array'])->fails());
    }

    // ----- AC-17: email via filter_var; non-string fails ------------------
    public function testAc17EmailRule(): void
    {
        $this->assertTrue(Json::validate('{"v":"a@b.com"}', ['v' => 'email'])->passes());
        $this->assertTrue(Json::validate('{"v":"not-an-email"}', ['v' => 'email'])->fails());
        $this->assertTrue(Json::validate('{"v":123}', ['v' => 'email'])->fails());
    }

    // ----- AC-18: min type-dependent, inclusive ---------------------------
    public function testAc18MinIsTypeDependentAndInclusive(): void
    {
        // number => magnitude
        $this->assertTrue(Json::validate('{"v":5}', ['v' => 'min:5'])->passes());
        $this->assertTrue(Json::validate('{"v":4}', ['v' => 'min:5'])->fails());
        // string => mb_strlen
        $this->assertTrue(Json::validate('{"v":"abcde"}', ['v' => 'min:5'])->passes());
        $this->assertTrue(Json::validate('{"v":"abcd"}', ['v' => 'min:5'])->fails());
        // array => count
        $this->assertTrue(Json::validate('{"v":[1,2,3,4,5]}', ['v' => 'min:5'])->passes());
        $this->assertTrue(Json::validate('{"v":[1,2,3,4]}', ['v' => 'min:5'])->fails());
    }

    // ----- AC-19: max type-dependent, inclusive ---------------------------
    public function testAc19MaxIsTypeDependentAndInclusive(): void
    {
        $this->assertTrue(Json::validate('{"v":5}', ['v' => 'max:5'])->passes());
        $this->assertTrue(Json::validate('{"v":6}', ['v' => 'max:5'])->fails());
        $this->assertTrue(Json::validate('{"v":"abcde"}', ['v' => 'max:5'])->passes());
        $this->assertTrue(Json::validate('{"v":"abcdef"}', ['v' => 'max:5'])->fails());
        $this->assertTrue(Json::validate('{"v":[1,2,3,4,5]}', ['v' => 'max:5'])->passes());
        $this->assertTrue(Json::validate('{"v":[1,2,3,4,5,6]}', ['v' => 'max:5'])->fails());
    }

    // ----- AC-20: between type-dependent, inclusive -----------------------
    public function testAc20BetweenIsTypeDependentAndInclusive(): void
    {
        // inclusive lower & upper bounds
        $this->assertTrue(Json::validate('{"v":1}', ['v' => 'between:1,5'])->passes());
        $this->assertTrue(Json::validate('{"v":5}', ['v' => 'between:1,5'])->passes());
        $this->assertTrue(Json::validate('{"v":0}', ['v' => 'between:1,5'])->fails());
        $this->assertTrue(Json::validate('{"v":6}', ['v' => 'between:1,5'])->fails());
        // string length
        $this->assertTrue(Json::validate('{"v":"abc"}', ['v' => 'between:1,5'])->passes());
        $this->assertTrue(Json::validate('{"v":"abcdef"}', ['v' => 'between:1,5'])->fails());
        // array count
        $this->assertTrue(Json::validate('{"v":[1,2,3]}', ['v' => 'between:1,5'])->passes());
    }

    // ----- AC-21: in: int 5 matches in:5,6 (string compare) ----------------
    public function testAc21InMatchesIntegerAgainstStringParameters(): void
    {
        $this->assertTrue(Json::validate('{"v":5}', ['v' => 'in:5,6'])->passes());
        $this->assertTrue(Json::validate('{"v":7}', ['v' => 'in:5,6'])->fails());
        $this->assertTrue(Json::validate('{"v":"a"}', ['v' => 'in:a,b,c'])->passes());
    }

    // ----- AC-22: regex verbatim; non-string fails ------------------------
    public function testAc22RegexAppliedVerbatimAndNonStringFails(): void
    {
        $this->assertTrue(Json::validate('{"v":"abc123"}', ['v' => 'regex:/^[a-z]+[0-9]+$/'])->passes());
        $this->assertTrue(Json::validate('{"v":"ABC"}', ['v' => 'regex:/^[a-z]+[0-9]+$/'])->fails());
        // non-string fails the regex rule
        $this->assertTrue(Json::validate('{"v":123}', ['v' => 'regex:/^[0-9]+$/'])->fails());
    }

    // ----- AC-23: unknown rule => \InvalidArgumentException ----------------
    public function testAc23UnknownRuleThrowsInvalidArgumentException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Json::validate('{"v":1}', ['v' => 'bogus']);
    }

    // ----- AC-24: bare min (missing required param) => \InvalidArgumentException
    public function testAc24RuleMissingRequiredParameterThrowsInvalidArgumentException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Json::validate('{"v":1}', ['v' => 'min']);
    }

    // ----- AC-25: invalid JSON => InvalidJsonException --------------------
    public function testAc25InvalidJsonThrowsInvalidJsonException(): void
    {
        $this->expectException(InvalidJsonException::class);

        Json::validate('{not valid json', ['v' => 'string']);
    }

    // ----- AC-26: multiple rules accumulate messages on one field ---------
    public function testAc26MultipleRulesAccumulateMessagesOnOneField(): void
    {
        // "ab" is a string (passes string) but fails both min:5 and email.
        $result = Json::validate('{"v":"ab"}', ['v' => 'string|min:5|email']);

        $messages = $result->errors()['v'] ?? [];
        $this->assertGreaterThanOrEqual(2, count($messages), 'Expected accumulated messages for min and email.');
    }

    // ----- AC-27: multiple fields, only failing ones appear in errors() ---
    public function testAc27OnlyFailingFieldsAppearInErrors(): void
    {
        $result = Json::validate('{"good":"hi","bad":5}', [
            'good' => 'string',
            'bad' => 'string',
        ]);

        $errors = $result->errors();
        $this->assertArrayHasKey('bad', $errors);
        $this->assertArrayNotHasKey('good', $errors);
    }

    // ----- AC-28: nested dot path; error key is the exact dot path ---------
    public function testAc28NestedDotPathKeyedByExactDotPath(): void
    {
        $result = Json::validate('{"user":{"name":5}}', ['user.name' => 'string']);

        $this->assertTrue($result->fails());
        $this->assertArrayHasKey('user.name', $result->errors());
    }

    public function testAc28NestedDotPathResolvesValueForValidated(): void
    {
        $result = Json::validate('{"user":{"name":"Ana"}}', ['user.name' => 'string']);

        $this->assertSame(['user.name' => 'Ana'], $result->validated());
    }

    // ----- AC-29: field paths are case-sensitive --------------------------
    public function testAc29FieldPathsAreCaseSensitive(): void
    {
        // Rule addresses "Name" but JSON only has lowercase "name".
        // Path is absent + only a type rule (no required) => skipped, passes.
        $result = Json::validate('{"name":"Ana"}', ['Name' => 'string']);

        $this->assertTrue($result->passes());
        $this->assertArrayNotHasKey('Name', $result->validated());

        // With required, the case-mismatched absent path must fail.
        $required = Json::validate('{"name":"Ana"}', ['Name' => 'required']);
        $this->assertTrue($required->fails());
        $this->assertArrayHasKey('Name', $required->errors());
    }

    // ----- AC-30: messages present, human-readable, field-identifying ------
    public function testAc30MessagesAreHumanReadableAndIdentifyTheField(): void
    {
        $result = Json::validate('{"emailAddress":123}', ['emailAddress' => 'email']);

        $messages = $result->errors()['emailAddress'] ?? [];
        $this->assertNotEmpty($messages);
        $message = $messages[0];

        // Human-readable: contains words/spaces, not just a token.
        $this->assertStringContainsString(' ', $message);
        // Field-identifying: references the field name.
        $this->assertStringContainsString('emailAddress', $message);
    }

    // ----- AC-32: Json::validate is static; ValidationResult is readonly/immutable
    public function testAc32ValidateIsStaticAndResultIsReadonlyImmutable(): void
    {
        // Static, callable without an instance:
        $result = Json::validate('{"v":"x"}', ['v' => 'string']);
        $this->assertInstanceOf(ValidationResult::class, $result);

        // Reflection: method is static, class is readonly.
        $method = new \ReflectionMethod(Json::class, 'validate');
        $this->assertTrue($method->isStatic(), 'Json::validate must be static.');

        $class = new \ReflectionClass(ValidationResult::class);
        $this->assertTrue($class->isReadOnly(), 'ValidationResult must be a readonly class.');
    }

    // ----- AC-33: PHPUnit 12 plain assertions (skip cases empty rules) -----
    public function testAc33EmptyRulesPassAndValidatedIsEmpty(): void
    {
        $result = Json::validate('{"anything":1}', []);

        $this->assertTrue($result->passes());
        $this->assertSame([], $result->validated());
    }
}
